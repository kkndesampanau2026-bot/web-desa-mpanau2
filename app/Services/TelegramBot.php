<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pembungkus tipis Telegram Bot API.
 *
 * Satu aturan yang menentukan seluruh bentuk kelas ini: **tidak pernah
 * melempar exception ke pemanggil.** Setiap method mengembalikan bool/array
 * dan mencatat kegagalannya sendiri.
 *
 * Alasannya, pemanggilnya adalah alur yang sudah mengubah basis data —
 * pengajuan tersimpan, status berpindah, PDF terbit. Bila Telegram sedang
 * tidak dapat dihubungi lalu kelas ini melempar, transaksi ikut batal dan
 * warga melihat "pengajuan gagal" padahal yang gagal hanya notifikasinya.
 * Kegagalan pengiriman ditangani sebagai peristiwa yang dicatat, bukan
 * sebagai galat yang membatalkan.
 */
class TelegramBot
{
    public function aktif(): bool
    {
        return filled(config('telegram.token'));
    }

    /**
     * @param  array<int, array<int, array<string, string>>>|null  $tombol  inline keyboard
     */
    public function kirimPesan(
        string $chatId,
        string $teks,
        ?array $tombol = null,
    ): ?array {
        $payload = [
            'chat_id' => $chatId,
            'text' => $teks,
            'parse_mode' => 'HTML',
            // Pratinjau tautan akan membuat kartu besar di bawah setiap
            // notifikasi dan mendorong tombol persetujuan keluar layar ponsel.
            'link_preview_options' => ['is_disabled' => true],
        ];

        if ($tombol !== null) {
            $payload['reply_markup'] = ['inline_keyboard' => $tombol];
        }

        return $this->sebagaiArray($this->panggil('sendMessage', $payload));
    }

    /**
     * Meminta jawaban teks dari pejabat (alasan penolakan).
     *
     * ForceReply memunculkan kolom balasan yang sudah tertaut ke pesan ini,
     * sehingga jawabannya membawa `reply_to_message` — itulah yang dipakai
     * backend untuk mengetahui alasan tersebut milik pengajuan yang mana.
     * Tanpanya, bot harus menyimpan state percakapan per pengguna.
     */
    public function mintaBalasan(string $chatId, string $teks): ?array
    {
        return $this->sebagaiArray($this->panggil('sendMessage', [
            'chat_id' => $chatId,
            'text' => $teks,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'force_reply' => true,
                'input_field_placeholder' => 'Tulis alasan penolakan…',
            ],
        ]));
    }

    /**
     * Menjawab penekanan tombol.
     *
     * WAJIB dipanggil untuk setiap callback: selama belum dijawab, Telegram
     * menampilkan indikator memuat pada tombol dan pejabat akan menekannya
     * berulang kali karena mengira tidak berfungsi.
     */
    public function jawabCallback(string $callbackId, string $teks = '', bool $peringatan = false): void
    {
        $this->panggil('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => mb_substr($teks, 0, 200),
            'show_alert' => $peringatan,
        ]);
    }

    /**
     * Mengganti tombol pada pesan yang sudah terkirim.
     *
     * Dipakai untuk MELEPAS tombol setelah keputusan diambil, sehingga pesan
     * lama di riwayat chat tidak lagi tampak seperti menunggu tindakan.
     * Pelepasan ini murni kosmetik — penolakan penekanan ulang tetap
     * ditegakkan server, bukan oleh hilangnya tombol.
     */
    public function gantiTombol(string $chatId, int $messageId, ?string $catatan = null): void
    {
        $this->panggil('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => [
                'inline_keyboard' => $catatan === null
                    ? []
                    : [[['text' => $catatan, 'callback_data' => 'noop']]],
            ],
        ]);
    }

    public function setWebhook(string $url, string $secret): bool
    {
        return $this->panggil('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            // Hanya dua jenis pembaruan yang dipakai: penekanan tombol dan
            // balasan alasan penolakan. Membatasinya mengurangi lalu lintas
            // sekaligus permukaan yang harus divalidasi.
            'allowed_updates' => ['callback_query', 'message'],
            /*
             * Pembaruan yang tertunda TIDAK dibuang.
             *
             * Perintah ini kini dijalankan ulang pada setiap boot kontainer
             * (lihat docker/entrypoint.sh), dan setiap deploy berarti satu
             * boot. Membuang antrean di situ berarti setiap penekanan tombol
             * APPROVE yang belum sempat terkirim — termasuk yang menumpuk
             * justru karena webhook-nya sedang salah — lenyap tanpa jejak,
             * sementara pejabatnya sudah merasa menekan.
             *
             * Memutarnya kembali aman: setiap pembaruan diverifikasi ulang
             * dari chat ID pengirim dan status pengajuan saat itu, sehingga
             * yang sudah kedaluwarsa ditolak dengan sendirinya.
             */
            'drop_pending_updates' => false,
        ]) !== null;
    }

    public function hapusWebhook(): bool
    {
        return $this->panggil('deleteWebhook', ['drop_pending_updates' => true]) !== null;
    }

    public function infoWebhook(): ?array
    {
        return $this->sebagaiArray($this->panggil('getWebhookInfo', []));
    }

    /**
     * Menyaring hasil panggilan yang memang diharapkan berupa objek.
     *
     * @return array<string, mixed>|null
     */
    private function sebagaiArray(mixed $hasil): ?array
    {
        return is_array($hasil) ? $hasil : null;
    }

    /**
     * Tipe kembaliannya `mixed`, BUKAN `?array`.
     *
     * Bot API tidak selalu menjawab dengan objek: sendMessage mengembalikan
     * Message, sedangkan setWebhook, deleteWebhook, dan answerCallbackQuery
     * mengembalikan `true` begitu saja. Menuntut array di sini membuat
     * ketiganya gagal dengan TypeError justru ketika Telegram menjawab
     * BERHASIL — dan karena panggil() menelan seluruh Throwable, kegagalannya
     * muncul sebagai "Telegram tidak dapat dihubungi" yang menyesatkan.
     *
     * @param  array<string, mixed>  $payload
     * @return mixed isi `result`, atau null bila gagal
     */
    private function panggil(string $method, array $payload): mixed
    {
        if (! $this->aktif()) {
            Log::info("Telegram dilewati: token belum diatur ({$method}).");

            return null;
        }

        $url = rtrim((string) config('telegram.api_url'), '/')
            .'/bot'.config('telegram.token').'/'.$method;

        try {
            $respons = Http::timeout((int) config('telegram.timeout', 10))
                ->asJson()
                ->post($url, $payload);

            if ($respons->successful() && $respons->json('ok') === true) {
                return $respons->json('result');
            }

            // Token TIDAK ikut tercatat: berkas log kerap dikirimkan saat
            // meminta bantuan teknis, dan token bot setara kata sandi.
            Log::warning('Telegram menolak permintaan.', [
                'method' => $method,
                'status' => $respons->status(),
                'description' => $respons->json('description'),
            ]);
        } catch (Throwable $e) {
            Log::warning('Telegram tidak dapat dihubungi.', [
                'method' => $method,
                'pesan' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
