<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\LetterOfficial;
use App\Models\LetterRequest;
use App\Services\NotifikasiSuratTelegram;
use App\Services\OtorisasiApprovalTelegram;
use App\Services\SuratPengantarService;
use App\Services\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Titik masuk pembaruan dari Telegram Bot API.
 *
 * Endpoint ini publik dan tanpa sesi — Telegram tidak membawa cookie apa pun.
 * Karena itu ia berdiri di atas tiga lapis, dan lapis terakhirlah yang
 * sesungguhnya mengikat:
 *
 * 1. **Header rahasia.** `X-Telegram-Bot-Api-Secret-Token` diperiksa lebih
 *    dulu, sebelum satu pun query dijalankan. Menahan lalu lintas iseng jauh
 *    dari basis data.
 *
 * 2. **Bentuk callback_data yang sempit.** Diurai dengan regex ketat
 *    (lihat OtorisasiApprovalTelegram::bacaCallback) sehingga nilai dari luar
 *    tidak pernah masuk ke query dalam bentuk yang tak terduga.
 *
 * 3. **Chat ID pengirim.** Inilah wewenang yang sebenarnya. Tombol dapat
 *    diteruskan ke siapa saja; chat ID ditetapkan Telegram dan tidak dapat
 *    dipalsukan klien. Setiap aksi diverifikasi ulang terhadap pejabat yang
 *    dipetakan dari chat ID itu — bukan terhadap isi tombolnya.
 *
 * Selalu menjawab HTTP 200. Status galat membuat Telegram mengirim ulang
 * pembaruan yang sama berulang kali; kesalahan diberitahukan kepada pejabat
 * lewat pesan bot, bukan lewat kode status.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramBot $bot,
        private readonly OtorisasiApprovalTelegram $otorisasi,
        private readonly SuratPengantarService $surat,
        private readonly NotifikasiSuratTelegram $notifikasi,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->rahasiaCocok($request)) {
            Log::warning('Webhook Telegram ditolak: rahasia tidak cocok.');

            // 403, bukan 200: permintaan ini bukan dari Telegram, jadi tidak
            // ada pengiriman ulang yang perlu dicegah.
            return response()->json(['ok' => false], 403);
        }

        try {
            if ($request->has('callback_query')) {
                $this->tanganiTombol($request->input('callback_query'));
            } elseif ($request->has('message')) {
                $this->tanganiPesan($request->input('message'));
            }
        } catch (\Throwable $e) {
            // Ditelan dengan sengaja: melempar akan membuat Telegram
            // mengirim ulang pembaruan yang sama tanpa henti.
            Log::error('Galat memproses webhook Telegram.', ['pesan' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------
    // Penekanan tombol
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $callback
     */
    private function tanganiTombol(?array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        $data = (string) ($callback['data'] ?? '');

        if ($callbackId === '' || $chatId === '') {
            return;
        }

        // Tombol penanda pada pesan yang sudah selesai.
        if ($data === 'noop') {
            $this->bot->jawabCallback($callbackId, 'Pengajuan ini sudah diproses.');

            return;
        }

        $terurai = OtorisasiApprovalTelegram::bacaCallback($data);

        if ($terurai === null) {
            $this->bot->jawabCallback($callbackId, 'Perintah tidak dikenali.', peringatan: true);

            return;
        }

        [$aksi, $tahap, $uuid] = $terurai;

        $pejabat = $this->otorisasi->pejabat($chatId);
        $permohonan = $this->otorisasi->permohonan($uuid);

        $penolakan = $this->otorisasi->tolakDengan($pejabat, $permohonan, $tahap);

        if ($penolakan !== null) {
            $this->bot->jawabCallback($callbackId, $penolakan, peringatan: true);

            // Tombol dilepas hanya bila pengajuannya memang sudah selesai —
            // bukan ketika yang menekan adalah orang yang tidak berwenang,
            // sebab pesan milik pejabat yang sah tidak boleh ikut dilucuti.
            if ($permohonan?->selesai() && $pejabat !== null) {
                $this->bot->gantiTombol($chatId, $messageId, '— sudah diproses —');
            }

            return;
        }

        // Kedua nilai dipastikan tidak null oleh tolakDengan() di atas.
        if ($aksi === 'approve') {
            $this->setujui($callbackId, $chatId, $messageId, $permohonan, $pejabat, $tahap);
        } else {
            $this->mintaAlasan($callbackId, $chatId, $messageId, $permohonan, $tahap);
        }
    }

    private function setujui(
        string $callbackId,
        string $chatId,
        int $messageId,
        LetterRequest $permohonan,
        LetterOfficial $pejabat,
        string $tahap,
    ): void {
        $hasil = $tahap === 'RT'
            ? $this->surat->setujuiRt($permohonan, $pejabat)
            : $this->surat->setujuiKadus($permohonan, $pejabat);

        // Pemeriksaan kedua di dalam service, di bawah kunci baris: dua
        // penekanan yang tiba bersamaan sama-sama lolos pemeriksaan pertama,
        // dan hanya di sanalah salah satunya benar-benar dapat ditolak.
        if ($hasil !== 'ok') {
            $this->bot->jawabCallback(
                $callbackId,
                $hasil === 'sudah_diproses'
                    ? 'Pengajuan ini sudah diproses sebelumnya.'
                    : 'Anda tidak berwenang atas pengajuan ini.',
                peringatan: true
            );
            $this->bot->gantiTombol($chatId, $messageId, '— sudah diproses —');

            return;
        }

        $this->bot->jawabCallback($callbackId, '✅ Persetujuan tercatat.');
        $this->bot->gantiTombol($chatId, $messageId, '✅ Anda menyetujui');

        $permohonan->refresh();

        $this->notifikasi->konfirmasiKeputusan(
            $pejabat,
            $permohonan,
            $tahap === 'RT'
                ? '✅ Anda menyetujui pengajuan ini. Diteruskan ke Kepala Dusun.'
                : '✅ Anda menyetujui pengajuan ini. Surat telah terbit dengan nomor '
                    .($permohonan->nomor_surat ?? '—').'.'
        );
    }

    /**
     * Meminta alasan penolakan.
     *
     * Penolakan TIDAK dicatat pada tahap ini: statusnya baru berubah setelah
     * alasannya benar-benar diterima. Bila pejabat berubah pikiran dan tidak
     * membalas, pengajuan tetap berjalan seperti semula — bukan tertahan
     * sebagai "ditolak tanpa alasan" yang tak dapat dijelaskan ke warga.
     */
    private function mintaAlasan(
        string $callbackId,
        string $chatId,
        int $messageId,
        LetterRequest $permohonan,
        string $tahap,
    ): void {
        $this->bot->jawabCallback($callbackId, 'Silakan tulis alasan penolakan.');

        $prompt = $this->bot->mintaBalasan($chatId, implode("\n", [
            '❌ <b>Penolakan pengajuan</b> <code>'.e($permohonan->ticket_number).'</code>',
            '',
            'Silakan masukkan alasan penolakan pengajuan atas nama <b>'.e($permohonan->nama).'</b>.',
            '',
            '<i>Alasan ini akan dibaca warga pada halaman cek status surat.</i>',
        ]));

        if ($prompt === null) {
            return;
        }

        /*
         * Menautkan pesan permintaan ke pengajuan yang bersangkutan.
         *
         * Balasan warga nanti membawa `reply_to_message.message_id`; itulah
         * kunci yang dipakai untuk mengetahui alasan tersebut milik pengajuan
         * mana — tanpa perlu menyimpan state percakapan per pengguna.
         *
         * Cache, bukan tabel: nilainya berumur pendek dan tidak perlu
         * bertahan. Bila kedaluwarsa sebelum dibalas, pejabat cukup menekan
         * tombol TOLAK sekali lagi.
         */
        Cache::put(
            $this->kunciBalasan($chatId, (int) $prompt['message_id']),
            ['uuid' => $permohonan->uuid, 'tahap' => $tahap, 'pesan_asal' => $messageId],
            now()->addHours(6)
        );
    }

    // -----------------------------------------------------------------
    // Balasan teks (alasan penolakan)
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>|null  $pesan
     */
    private function tanganiPesan(?array $pesan): void
    {
        $chatId = (string) ($pesan['chat']['id'] ?? '');
        $balasanKe = (int) ($pesan['reply_to_message']['message_id'] ?? 0);
        $teks = trim((string) ($pesan['text'] ?? ''));

        if ($chatId === '' || $balasanKe === 0) {
            return;
        }

        $konteks = Cache::get($this->kunciBalasan($chatId, $balasanKe));

        if (! is_array($konteks)) {
            return;
        }

        if ($teks === '') {
            $this->bot->kirimPesan($chatId, 'Alasan penolakan tidak boleh kosong. '
                .'Silakan balas pesan permintaan alasan sekali lagi.');

            return;
        }

        // Alasan ini tercetak pada halaman publik warga; dibatasi panjangnya
        // agar tidak menjadi jalur menempelkan teks sepanjang apa pun ke
        // halaman yang dapat dilihat orang lain.
        $alasan = mb_substr($teks, 0, 500);

        $pejabat = $this->otorisasi->pejabat($chatId);
        $permohonan = $this->otorisasi->permohonan((string) $konteks['uuid']);
        $tahap = (string) $konteks['tahap'];

        $penolakan = $this->otorisasi->tolakDengan($pejabat, $permohonan, $tahap);

        if ($penolakan !== null) {
            $this->bot->kirimPesan($chatId, '⚠️ '.$penolakan);
            Cache::forget($this->kunciBalasan($chatId, $balasanKe));

            return;
        }

        $hasil = $this->surat->tolak($permohonan, $pejabat, $tahap, $alasan);

        Cache::forget($this->kunciBalasan($chatId, $balasanKe));

        if ($hasil !== 'ok') {
            $this->bot->kirimPesan($chatId, '⚠️ Pengajuan ini sudah diproses sebelumnya.');

            return;
        }

        // Melepas tombol pada pesan notifikasi yang asli, bukan pada pesan
        // permintaan alasan — itulah yang masih terlihat menunggu tindakan.
        if (! empty($konteks['pesan_asal'])) {
            $this->bot->gantiTombol($chatId, (int) $konteks['pesan_asal'], '❌ Anda menolak');
        }

        $this->bot->kirimPesan($chatId, implode("\n", [
            '❌ <b>Penolakan tercatat.</b>',
            '',
            'Nomor Tiket: <code>'.e($permohonan->ticket_number).'</code>',
            'Alasan: <i>'.e($alasan).'</i>',
            '',
            'Warga dapat melihat alasan ini pada halaman cek status surat.',
        ]));
    }

    // -----------------------------------------------------------------
    // Pendukung
    // -----------------------------------------------------------------

    private function rahasiaCocok(Request $request): bool
    {
        $rahasia = (string) config('telegram.webhook_secret');

        // Rahasia kosong = webhook belum dikonfigurasi. Menolak, bukan
        // meloloskan: endpoint terbuka lebih berbahaya daripada bot yang
        // belum berfungsi.
        if ($rahasia === '') {
            return false;
        }

        return hash_equals(
            $rahasia,
            (string) $request->header('X-Telegram-Bot-Api-Secret-Token')
        );
    }

    private function kunciBalasan(string $chatId, int $messageId): string
    {
        return "telegram:balasan:{$chatId}:{$messageId}";
    }
}
