<?php

namespace App\Services;

use App\Models\LetterApprovalLog;
use App\Models\LetterOfficial;
use App\Models\LetterRequest;

/**
 * Penyusun pesan & tombol Telegram untuk alur Surat Pengantar.
 *
 * Dipisahkan dari TelegramBot (yang hanya tahu cara bicara dengan Bot API)
 * dan dari SuratPengantarService (yang hanya tahu aturan alurnya), supaya
 * susunan kalimat notifikasi dapat diubah desa tanpa menyentuh keduanya.
 *
 * Satu batasan yang dijaga ketat di sini: **NIK utuh tidak pernah dikirim ke
 * Telegram.** Pesan bot tersimpan di server Telegram, tersalin ke perangkat
 * pejabat, dan ikut ter-backup ke cloud mereka. Yang dikirim hanya bentuk
 * tersamar; NIK utuh baru muncul pada PDF, yang hanya dapat diambil lewat
 * endpoint bertiket.
 */
class NotifikasiSuratTelegram
{
    public function __construct(
        private readonly TelegramBot $bot,
        private readonly PiiCipher $pii,
    ) {}

    /** Notifikasi tahap pertama — ke Ketua RT. */
    public function keKetuaRt(LetterRequest $p, LetterOfficial $rt): bool
    {
        $teks = implode("\n", [
            '🔔 <b>PENGAJUAN SURAT PENGANTAR</b>',
            '',
            'Nomor Tiket: <code>'.e($p->ticket_number).'</code>',
            '',
            'Nama: <b>'.e($p->nama).'</b>',
            'NIK: '.e($this->pii->samarkan($p->nik) ?? '—'),
            'Tempat, Tgl Lahir: '.e($p->tempat_lahir).', '.$p->tanggal_lahir->format('d-m-Y'),
            'Pekerjaan: '.e($p->pekerjaan),
            'Alamat: '.e($p->alamat),
            'RT: '.e($p->rt?->nomor ?? '—').' · Dusun: '.e($p->dusun?->nama ?? '—'),
            '',
            'Keperluan:',
            '<i>'.e($p->maksud_keperluan).'</i>',
            '',
            'Silakan periksa data pengajuan.',
        ]);

        return $this->kirim($p, $rt, $teks, $this->tombol('rt', $p->uuid));
    }

    /** Notifikasi tahap kedua — ke Kepala Dusun, setelah RT menyetujui. */
    public function keKepalaDusun(LetterRequest $p, LetterOfficial $kadus): bool
    {
        $namaRt = $p->penyetujuRt?->nama ?? 'Ketua RT';

        $teks = implode("\n", [
            '🔔 <b>PENGAJUAN SURAT PENGANTAR</b>',
            '',
            'Nomor Tiket: <code>'.e($p->ticket_number).'</code>',
            '',
            'Nama: <b>'.e($p->nama).'</b>',
            'NIK: '.e($this->pii->samarkan($p->nik) ?? '—'),
            'RT: '.e($p->rt?->nomor ?? '—').' · Dusun: '.e($p->dusun?->nama ?? '—'),
            '',
            'Keperluan:',
            '<i>'.e($p->maksud_keperluan).'</i>',
            '',
            'Ketua RT: ✅ Disetujui ('.e($namaRt).')',
            $p->approved_rt_at ? 'Pada: '.$p->approved_rt_at->format('d M Y H:i') : '',
            '',
            'Silakan periksa pengajuan.',
        ]);

        return $this->kirim($p, $kadus, $teks, $this->tombol('kadus', $p->uuid));
    }

    /** Kabar ke Ketua RT bahwa Kepala Dusun menolak surat yang ia setujui. */
    public function kabarPenolakanKeRt(LetterRequest $p): void
    {
        $rt = $p->penyetujuRt;

        if (! $rt || blank($rt->telegram_chat_id)) {
            return;
        }

        $this->bot->kirimPesan($rt->telegram_chat_id, implode("\n", [
            'ℹ️ <b>Pengajuan ditolak Kepala Dusun</b>',
            '',
            'Nomor Tiket: <code>'.e($p->ticket_number).'</code>',
            'Nama: '.e($p->nama),
            '',
            'Alasan: <i>'.e((string) $p->rejection_reason).'</i>',
        ]));
    }

    /** Konfirmasi ke pejabat setelah keputusannya tercatat. */
    public function konfirmasiKeputusan(
        LetterOfficial $pejabat,
        LetterRequest $p,
        string $ringkas,
    ): void {
        if (blank($pejabat->telegram_chat_id)) {
            return;
        }

        $this->bot->kirimPesan(
            $pejabat->telegram_chat_id,
            $ringkas."\n\nNomor Tiket: <code>".e($p->ticket_number).'</code>'
        );
    }

    /**
     * Inline keyboard dua tombol.
     *
     * `callback_data` hanya memuat aksi + UUID pengajuan — tidak ada nama,
     * NIK, atau nomor tiket. Isinya melewati server Telegram dan tersimpan di
     * riwayat chat, jadi ia diperlakukan sebagai kanal publik.
     *
     * UUID di sini juga bukan bukti wewenang: backend tetap memeriksa ulang
     * chat ID pengirim, rolenya, wilayahnya, dan status pengajuan sebelum
     * mengubah apa pun (lihat OtorisasiApprovalTelegram).
     *
     * @return array<int, array<int, array<string, string>>>
     */
    private function tombol(string $tahap, string $uuid): array
    {
        return [[
            ['text' => '✅ APPROVE', 'callback_data' => "approve_{$tahap}:{$uuid}"],
            ['text' => '❌ TOLAK', 'callback_data' => "reject_{$tahap}:{$uuid}"],
        ]];
    }

    /**
     * @param  array<int, array<int, array<string, string>>>  $tombol
     * @return bool apakah pesan benar-benar terkirim
     */
    private function kirim(
        LetterRequest $p,
        LetterOfficial $pejabat,
        string $teks,
        array $tombol,
    ): bool {
        // Pejabat tanpa chat ID adalah kondisi yang WAJAR di awal pemakaian:
        // desa mendata RT-nya lebih dulu, chat ID menyusul. Yang penting
        // pengajuan tidak hilang diam-diam — ia tercatat sebagai langkah yang
        // gagal dinotifikasi, dan operator dapat melihatnya di /admin/surat.
        if (blank($pejabat->telegram_chat_id)) {
            LetterApprovalLog::create([
                'letter_request_id' => $p->id,
                'letter_official_id' => $pejabat->id,
                'role' => $pejabat->role === LetterOfficial::ROLE_KETUA_RT ? 'RT' : 'KADUS',
                'action' => LetterApprovalLog::NOTIFIKASI_GAGAL,
                'actor_nama' => $pejabat->nama,
                'reason' => 'Pejabat belum memiliki Telegram Chat ID.',
            ]);

            return false;
        }

        // Bot yang belum dikonfigurasi DIBEDAKAN dari pengiriman yang ditolak.
        // Keduanya sama-sama membuat kirimPesan() mengembalikan null, namun
        // penanganannya berbeda jauh: yang satu diperbaiki dengan mengisi
        // TELEGRAM_BOT_TOKEN, yang lain dengan memeriksa chat ID atau jaringan.
        // Mencatat keduanya sebagai "gagal terkirim" membuat sebab pertama —
        // yang paling sering terjadi saat pemasangan — tidak terbaca sama
        // sekali dari riwayat pengajuan.
        if (! $this->bot->aktif()) {
            $this->catatGagal(
                $p,
                $pejabat,
                'Bot Telegram belum dikonfigurasi: TELEGRAM_BOT_TOKEN masih kosong '
                .'pada berkas .env. Notifikasi dilewati.'
            );

            return false;
        }

        if ($this->bot->kirimPesan($pejabat->telegram_chat_id, $teks, $tombol) === null) {
            $this->catatGagal(
                $p,
                $pejabat,
                'Telegram menolak atau tidak dapat dihubungi. Periksa Chat ID pejabat, '
                .'dan pastikan pejabat sudah menekan /start pada bot desa — Telegram '
                .'tidak mengizinkan bot mengirim pesan lebih dulu kepada orang yang '
                .'belum pernah membuka percakapan dengannya.'
            );

            return false;
        }

        return true;
    }

    private function catatGagal(LetterRequest $p, LetterOfficial $pejabat, string $alasan): void
    {
        LetterApprovalLog::create([
            'letter_request_id' => $p->id,
            'letter_official_id' => $pejabat->id,
            'role' => $pejabat->role === LetterOfficial::ROLE_KETUA_RT ? 'RT' : 'KADUS',
            'action' => LetterApprovalLog::NOTIFIKASI_GAGAL,
            'actor_nama' => $pejabat->nama,
            'reason' => $alasan,
        ]);
    }
}
