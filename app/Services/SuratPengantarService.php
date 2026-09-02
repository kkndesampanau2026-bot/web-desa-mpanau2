<?php

namespace App\Services;

use App\Jobs\KirimNotifikasiSurat;
use App\Models\LetterApprovalLog;
use App\Models\LetterOfficial;
use App\Models\LetterRequest;
use App\Models\Rt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Aturan alur Surat Pengantar — satu-satunya tempat status berpindah.
 *
 * Controller (web maupun webhook Telegram) tidak pernah menyentuh kolom
 * `status` secara langsung; semuanya melewati kelas ini. Tanpa pemusatan itu,
 * aturan "RT tidak boleh menyetujui dua kali" harus ditulis ulang di setiap
 * pemanggil dan cepat atau lambat ada satu yang terlewat.
 *
 * Tiga pola yang dijaga di seluruh method:
 *
 * 1. **Transisi status dikunci baris.** `lockForUpdate()` di dalam transaksi
 *    menahan dua penekanan tombol yang tiba bersamaan (pejabat menekan dua
 *    kali, atau Telegram mengirim ulang callback) agar tidak menghasilkan dua
 *    persetujuan. Pemeriksaan status dilakukan SETELAH kunci didapat, bukan
 *    sebelumnya.
 *
 * 2. **Efek samping keluar dari transaksi.** Pembuatan PDF dan pengiriman
 *    Telegram terjadi setelah commit. Bila keduanya dikerjakan di dalam
 *    transaksi lalu Telegram menggantung 10 detik, kunci baris ikut tertahan
 *    selama itu.
 *
 * 3. **Kegagalan efek samping tidak membatalkan keputusan.** PDF gagal
 *    dirender atau Telegram tak dapat dihubungi dicatat sebagai peristiwa;
 *    status yang sudah sah tidak dikembalikan. Yang gagal dapat diulang,
 *    persetujuan yang hilang tidak dapat dipulihkan.
 */
class SuratPengantarService
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly NomorTiketSurat $tiket,
        private readonly PiiCipher $pii,
        private readonly PdfSuratPengantar $pdf,
        private readonly ActivityLogger $audit,
    ) {}

    // -----------------------------------------------------------------
    // Pengajuan
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data  sudah tervalidasi FormRequest
     */
    public function ajukan(array $data, ?string $ipHash = null): LetterRequest
    {
        $rt = Rt::where('village_id', $this->village->id())
            ->findOrFail($data['rt_id']);

        // Lapis terakhir. Validasi request sudah menyaringnya, tetapi service
        // ini juga dipanggil dari seeder dan perintah artisan — dan tanpa
        // penjagaan ini kegagalannya muncul sebagai galat constraint basis
        // data yang tidak menjelaskan apa pun.
        if ($rt->dusun_id === null) {
            throw new RuntimeException(
                "RT {$rt->nomor} belum terhubung ke dusun mana pun, sehingga "
                .'tidak ada Kepala Dusun yang dapat menandatangani suratnya.'
            );
        }

        $permohonan = DB::transaction(function () use ($data, $rt, $ipHash) {
            $permohonan = LetterRequest::create([
                'village_id' => $this->village->id(),
                'ticket_number' => $this->tiket->buat(),
                'uuid' => (string) Str::uuid(),

                'nik' => $data['nik'],
                'nik_hash' => $this->pii->hash($data['nik']),
                'nama' => $data['nama'],
                'tempat_lahir' => $data['tempat_lahir'],
                'tanggal_lahir' => $data['tanggal_lahir'],
                'pekerjaan' => $data['pekerjaan'],
                'agama' => $data['agama'],
                'status_perkawinan' => $data['status_perkawinan'],
                'warga_negara' => $data['warga_negara'],
                'alamat' => $data['alamat'],
                'maksud_keperluan' => $data['maksud_keperluan'],

                'rt_id' => $rt->id,
                // Dusun DISIMPULKAN dari RT, tidak diambil dari kiriman warga.
                // Selain menghapus satu isian, ini menutup celah warga memilih
                // pasangan RT-dusun yang tidak ada sehingga permohonan mendarat
                // di meja Kepala Dusun yang keliru.
                'dusun_id' => $rt->dusun_id,

                'status' => LetterRequest::MENUNGGU_RT,
                'ip_hash' => $ipHash,
            ]);

            LetterApprovalLog::create([
                'letter_request_id' => $permohonan->id,
                'role' => 'WARGA',
                'action' => LetterApprovalLog::DIAJUKAN,
                'actor_nama' => $permohonan->nama,
            ]);

            return $permohonan;
        });

        $this->audit->log(
            'created',
            $permohonan,
            "Warga mengajukan Surat Pengantar {$permohonan->ticket_number}"
        );

        // Draft dibuat di luar transaksi: rendering PDF memakan ratusan
        // milidetik dan tidak boleh menahan kunci baris.
        $this->simpanPdf($permohonan, $this->pdf->buatDraft($permohonan), LetterApprovalLog::DRAFT_DIBUAT);

        KirimNotifikasiSurat::dispatch($permohonan->id, 'RT');

        return $permohonan->refresh();
    }

    // -----------------------------------------------------------------
    // Persetujuan
    // -----------------------------------------------------------------

    /**
     * Persetujuan Ketua RT.
     *
     * @return string salah satu: ok | sudah_diproses | tidak_berwenang
     */
    public function setujuiRt(LetterRequest $permohonan, LetterOfficial $rt): string
    {
        $hasil = DB::transaction(function () use ($permohonan, $rt) {
            $terkunci = LetterRequest::lockForUpdate()->find($permohonan->id);

            if (! $terkunci || $terkunci->status !== LetterRequest::MENUNGGU_RT) {
                return 'sudah_diproses';
            }

            if (! $rt->berwenangAtas($terkunci) || $rt->role !== LetterOfficial::ROLE_KETUA_RT) {
                return 'tidak_berwenang';
            }

            $terkunci->update([
                'status' => LetterRequest::MENUNGGU_KADUS,
                'approved_rt_by' => $rt->id,
                'approved_rt_at' => now(),
            ]);

            LetterApprovalLog::create([
                'letter_request_id' => $terkunci->id,
                'letter_official_id' => $rt->id,
                'role' => 'RT',
                'action' => LetterApprovalLog::DISETUJUI_RT,
                'actor_nama' => $rt->nama,
                'telegram_chat_id' => $rt->telegram_chat_id,
            ]);

            return 'ok';
        });

        if ($hasil === 'ok') {
            $this->audit->logSebagai(
                $rt,
                'approved',
                $permohonan,
                "Ketua RT menyetujui Surat Pengantar {$permohonan->ticket_number}"
            );

            KirimNotifikasiSurat::dispatch($permohonan->id, 'KADUS');
        }

        return $hasil;
    }

    /**
     * Persetujuan Kepala Dusun — sekaligus penerbitan surat.
     *
     * @return string ok | sudah_diproses | tidak_berwenang
     */
    public function setujuiKadus(LetterRequest $permohonan, LetterOfficial $kadus): string
    {
        $hasil = DB::transaction(function () use ($permohonan, $kadus) {
            $terkunci = LetterRequest::lockForUpdate()->find($permohonan->id);

            if (! $terkunci || $terkunci->status !== LetterRequest::MENUNGGU_KADUS) {
                return 'sudah_diproses';
            }

            if (! $kadus->berwenangAtas($terkunci) || $kadus->role !== LetterOfficial::ROLE_KEPALA_DUSUN) {
                return 'tidak_berwenang';
            }

            $terkunci->update([
                'status' => LetterRequest::DISETUJUI,
                'approved_kadus_by' => $kadus->id,
                'approved_kadus_at' => now(),
                'nomor_urut' => $urut = $this->nomorUrutBerikutnya($terkunci),
                'nomor_surat' => $this->susunNomorSurat($terkunci, $urut),
            ]);

            LetterApprovalLog::create([
                'letter_request_id' => $terkunci->id,
                'letter_official_id' => $kadus->id,
                'role' => 'KADUS',
                'action' => LetterApprovalLog::DISETUJUI_KADUS,
                'actor_nama' => $kadus->nama,
                'telegram_chat_id' => $kadus->telegram_chat_id,
            ]);

            return 'ok';
        });

        if ($hasil !== 'ok') {
            return $hasil;
        }

        $this->audit->logSebagai(
            $kadus,
            'approved',
            $permohonan,
            "Kepala Dusun menyetujui Surat Pengantar {$permohonan->ticket_number}"
        );

        // PDF FINAL baru dibuat di sini — setelah seluruh persetujuan yang
        // diwajibkan tuntas. Tanda tangan kedua pejabat baru ikut tercetak
        // pada titik ini, tidak sedetik pun lebih awal.
        $permohonan->refresh();
        $lama = $permohonan->pdf_path;

        if ($this->simpanPdf($permohonan, $this->pdf->buatFinal($permohonan), LetterApprovalLog::SURAT_TERBIT)) {
            // Draft dihapus hanya setelah final benar-benar tersimpan; kalau
            // rendering gagal, warga masih memegang berkas yang dapat dibuka.
            $this->pdf->hapus($lama);
        }

        return 'ok';
    }

    // -----------------------------------------------------------------
    // Penolakan
    // -----------------------------------------------------------------

    /**
     * @param  string  $tahap  RT | KADUS
     * @return string ok | sudah_diproses | tidak_berwenang
     */
    public function tolak(
        LetterRequest $permohonan,
        LetterOfficial $pejabat,
        string $tahap,
        string $alasan,
    ): string {
        $statusDiharap = $tahap === 'RT'
            ? LetterRequest::MENUNGGU_RT
            : LetterRequest::MENUNGGU_KADUS;

        $roleDiharap = $tahap === 'RT'
            ? LetterOfficial::ROLE_KETUA_RT
            : LetterOfficial::ROLE_KEPALA_DUSUN;

        $hasil = DB::transaction(function () use ($permohonan, $pejabat, $tahap, $alasan, $statusDiharap, $roleDiharap) {
            $terkunci = LetterRequest::lockForUpdate()->find($permohonan->id);

            if (! $terkunci || $terkunci->status !== $statusDiharap) {
                return 'sudah_diproses';
            }

            if (! $pejabat->berwenangAtas($terkunci) || $pejabat->role !== $roleDiharap) {
                return 'tidak_berwenang';
            }

            $terkunci->update([
                'status' => LetterRequest::DITOLAK,
                'rejection_reason' => $alasan,
                'rejected_by' => $tahap,
                'rejected_by_official_id' => $pejabat->id,
                'rejected_at' => now(),
            ]);

            LetterApprovalLog::create([
                'letter_request_id' => $terkunci->id,
                'letter_official_id' => $pejabat->id,
                'role' => $tahap,
                'action' => $tahap === 'RT'
                    ? LetterApprovalLog::DITOLAK_RT
                    : LetterApprovalLog::DITOLAK_KADUS,
                'actor_nama' => $pejabat->nama,
                'reason' => $alasan,
                'telegram_chat_id' => $pejabat->telegram_chat_id,
            ]);

            return 'ok';
        });

        if ($hasil === 'ok') {
            $this->audit->logSebagai(
                $pejabat,
                'rejected',
                $permohonan,
                "Pengajuan {$permohonan->ticket_number} ditolak oleh {$tahap}"
            );

            // Draft tidak lagi boleh diunduh: pengajuan sudah tidak berjalan.
            $this->pdf->hapus($permohonan->pdf_path);
            $permohonan->forceFill(['pdf_path' => null])->save();

            KirimNotifikasiSurat::dispatch($permohonan->id, 'DITOLAK_'.$tahap);
        }

        return $hasil;
    }

    // -----------------------------------------------------------------
    // Pendukung
    // -----------------------------------------------------------------

    /**
     * Menyimpan path PDF yang baru dirender.
     *
     * @return bool apakah berkas berhasil dibuat
     */
    private function simpanPdf(LetterRequest $permohonan, ?string $path, string $aksi): bool
    {
        if ($path === null) {
            LetterApprovalLog::create([
                'letter_request_id' => $permohonan->id,
                'role' => 'SISTEM',
                'action' => $aksi,
                'reason' => 'Berkas PDF gagal dibuat; dapat dibuat ulang dari /admin/surat.',
            ]);

            return false;
        }

        $permohonan->forceFill(['pdf_path' => $path])->save();

        LetterApprovalLog::create([
            'letter_request_id' => $permohonan->id,
            'role' => 'SISTEM',
            'action' => $aksi,
        ]);

        return true;
    }

    /**
     * Nomor urut surat, dihitung PER RT.
     *
     * Blangko desa menulis "301/RT.20/DUSUN 04": angka pertama adalah nomor
     * agenda milik RT yang bersangkutan, bukan nomor berjalan sedesa. Karena
     * itu hitungannya dibatasi pada rt_id yang sama.
     *
     * Dipanggil di dalam transaksi yang sudah memegang kunci baris, sehingga
     * dua penerbitan bersamaan pada RT yang sama tidak menghasilkan nomor
     * kembar.
     */
    private function nomorUrutBerikutnya(LetterRequest $permohonan): int
    {
        $tertinggi = LetterRequest::where('village_id', $permohonan->village_id)
            ->where('rt_id', $permohonan->rt_id)
            ->lockForUpdate()
            ->max('nomor_urut');

        return (int) $tertinggi + 1;
    }

    private function susunNomorSurat(LetterRequest $permohonan, int $urut): string
    {
        $permohonan->loadMissing(['rt', 'dusun']);

        return sprintf(
            '%d/RT.%s/DUSUN %s',
            $urut,
            $permohonan->rt?->nomor ?? '-',
            $permohonan->dusun?->sebutanRingkas() ?? '-'
        );
    }

    /** Membuat ulang PDF — dipakai operator saat rendering pertama gagal. */
    public function buatUlangPdf(LetterRequest $permohonan): bool
    {
        $lama = $permohonan->pdf_path;

        $path = $permohonan->status === LetterRequest::DISETUJUI
            ? $this->pdf->buatFinal($permohonan)
            : ($permohonan->status === LetterRequest::DITOLAK ? null : $this->pdf->buatDraft($permohonan));

        if ($path === null) {
            return false;
        }

        $permohonan->forceFill(['pdf_path' => $path])->save();
        $this->pdf->hapus($lama);

        return true;
    }
}
