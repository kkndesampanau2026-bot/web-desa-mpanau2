<?php

namespace App\Jobs;

use App\Models\LetterOfficial;
use App\Models\LetterRequest;
use App\Services\NotifikasiSuratTelegram;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pengiriman notifikasi Telegram, di luar siklus permintaan warga.
 *
 * Dikerjakan antrean karena satu panggilan Bot API dapat memakan beberapa
 * detik saat jaringan desa sedang buruk. Bila dilakukan langsung, warga
 * menatap tombol "Mengirim…" selama itu untuk sesuatu yang sama sekali bukan
 * urusannya.
 *
 * Job ini sengaja hanya membawa ID, bukan objek model. Payload job tersimpan
 * sebagai baris di tabel `jobs`; menyerialisasi seluruh model berarti NIK
 * terenkripsi dan data pemohon ikut mengendap di sana.
 *
 * Antrean memakai koneksi `database` yang sudah menjadi bawaan project
 * (QUEUE_CONNECTION=database, tabel `jobs` sudah ada). Bila worker belum
 * dijalankan, driver `sync` di lingkungan pengembangan tetap mengeksekusinya
 * seketika — alur tidak pernah berhenti hanya karena worker mati.
 */
class KirimNotifikasiSurat implements ShouldQueue
{
    use Queueable;

    /** Percobaan ulang; Telegram sesekali menolak dengan 429/5xx. */
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly int $permohonanId,
        /** RT | KADUS | DITOLAK_RT | DITOLAK_KADUS */
        private readonly string $tahap,
    ) {}

    public function handle(NotifikasiSuratTelegram $notifikasi): void
    {
        $permohonan = LetterRequest::with(['rt.dusun', 'dusun', 'penyetujuRt'])
            ->find($this->permohonanId);

        if (! $permohonan) {
            return;
        }

        match ($this->tahap) {
            'RT' => $this->keRt($notifikasi, $permohonan),
            'KADUS' => $this->keKadus($notifikasi, $permohonan),
            'DITOLAK_KADUS' => $notifikasi->kabarPenolakanKeRt($permohonan),
            // Penolakan oleh RT tidak perlu dikabarkan ke siapa pun lewat
            // Telegram: warga membacanya di halaman cek status, dan Kepala
            // Dusun memang belum pernah menerima permohonan ini.
            default => null,
        };
    }

    private function keRt(NotifikasiSuratTelegram $notifikasi, LetterRequest $permohonan): void
    {
        $rt = LetterOfficial::aktif()
            ->where('role', LetterOfficial::ROLE_KETUA_RT)
            ->where('rt_id', $permohonan->rt_id)
            ->first();

        if (! $rt) {
            // Bukan galat yang perlu diulang: RT-nya memang belum didata.
            // Pengajuan tetap berdiri dan terlihat operator di /admin/surat.
            Log::warning('Ketua RT belum terdaftar untuk pengajuan surat.', [
                'ticket' => $permohonan->ticket_number,
                'rt_id' => $permohonan->rt_id,
            ]);

            return;
        }

        $notifikasi->keKetuaRt($permohonan, $rt);
    }

    private function keKadus(NotifikasiSuratTelegram $notifikasi, LetterRequest $permohonan): void
    {
        $kadus = LetterOfficial::aktif()
            ->where('role', LetterOfficial::ROLE_KEPALA_DUSUN)
            ->where('dusun_id', $permohonan->dusun_id)
            ->first();

        if (! $kadus) {
            Log::warning('Kepala Dusun belum terdaftar untuk pengajuan surat.', [
                'ticket' => $permohonan->ticket_number,
                'dusun_id' => $permohonan->dusun_id,
            ]);

            return;
        }

        $notifikasi->keKepalaDusun($permohonan, $kadus);
    }
}
