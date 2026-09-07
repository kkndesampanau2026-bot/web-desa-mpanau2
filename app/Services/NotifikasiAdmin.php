<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\LetterRequest;
use App\Models\PpidRequest;
use App\Models\User;

/**
 * Pekerjaan warga yang menunggu ditangani operator — isi lonceng notifikasi.
 *
 * Semula lonceng hanya menghitung pengaduan, dan logikanya menumpang sebagai
 * method privat di `HandleInertiaRequests`. Sejak tiga layanan warga
 * (pengaduan, permohonan informasi PPID, dan pengajuan surat pengantar)
 * sama-sama menghasilkan antrean, logikanya diangkat ke sini — middleware
 * bukan tempat menaruh tiga kueri beserta aturan izinnya.
 *
 * Setiap grup DISARING PER IZIN, bukan sekadar disembunyikan di antarmuka.
 * Operator PPID tidak boleh mengetahui ada berapa pengaduan masuk — nama dan
 * potongan isi aduan warga ikut terbawa pada pratinjaunya, dan itu di luar
 * kewenangannya (PRD 12.2).
 */
class NotifikasiAdmin
{
    /** Pratinjau per layanan. Lonceng adalah ringkasan, bukan daftar kerja. */
    private const MAKS_PRATINJAU = 5;

    public function __construct(private readonly CurrentVillage $village) {}

    /**
     * @return array{jumlah: int, grup: list<array<string, mixed>>}|null
     *     null bila operator tidak berwenang atas satu layanan pun — loncengnya
     *     ikut hilang, bukan tampil kosong tanpa penjelasan.
     */
    public function untuk(?User $pengguna): ?array
    {
        if ($pengguna === null) {
            return null;
        }

        $grup = array_values(array_filter([
            $pengguna->can('respond-complaint') ? $this->pengaduan() : null,
            $pengguna->can('respond-ppid-request') ? $this->ppid() : null,
            $pengguna->can('manage-letter-request') ? $this->surat() : null,
        ]));

        if ($grup === []) {
            return null;
        }

        return [
            'jumlah' => array_sum(array_column($grup, 'jumlah')),
            'grup' => $grup,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pengaduan(): array
    {
        $kueri = Complaint::where('village_id', $this->village->id())
            ->where('status', 'baru');

        return $this->grup(
            'pengaduan',
            'Pengaduan Baru',
            'Belum ditanggapi',
            '/admin/pengaduan',
            (clone $kueri)->count(),
            fn () => (clone $kueri)
                ->orderByDesc('created_at')
                ->limit(self::MAKS_PRATINJAU)
                ->get(['id', 'nomor_tiket', 'nama', 'kategori_pengaduan', 'isi_pengaduan', 'created_at'])
                ->map(fn (Complaint $p) => [
                    'judul' => $p->nama,
                    'ringkas' => $p->kategori_pengaduan.' · '.mb_substr($p->isi_pengaduan, 0, 80),
                    'waktu' => $p->created_at->diffForHumans(),
                    // Hanya layar Pengaduan yang menerima pintasan `?buka=`;
                    // dua layanan lain menaut ke halamannya saja agar tidak
                    // menjanjikan lompatan yang tidak ada.
                    'ke' => "/admin/pengaduan?buka={$p->id}",
                ])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ppid(): array
    {
        $kueri = PpidRequest::where('village_id', $this->village->id())
            ->where('status', 'diajukan');

        return $this->grup(
            'ppid',
            'Permohonan Informasi',
            'Menunggu diverifikasi',
            '/admin/ppid',
            (clone $kueri)->count(),
            fn () => (clone $kueri)
                ->orderByDesc('created_at')
                ->limit(self::MAKS_PRATINJAU)
                ->get(['id', 'nomor_registrasi', 'nama_pemohon', 'informasi_diminta', 'created_at'])
                ->map(fn (PpidRequest $p) => [
                    'judul' => $p->nama_pemohon,
                    'ringkas' => $p->nomor_registrasi.' · '.mb_substr((string) $p->informasi_diminta, 0, 80),
                    'waktu' => $p->created_at->diffForHumans(),
                    'ke' => '/admin/ppid',
                ])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function surat(): array
    {
        /*
         * Persetujuannya dilakukan Ketua RT & Kepala Dusun lewat Telegram,
         * bukan oleh operator. Yang dihitung di sini karena itu bukan "tugas
         * Anda", melainkan pengajuan yang masih menggantung — informasi yang
         * tetap perlu diketahui operator agar dapat mengingatkan pejabat yang
         * belum menekan tombol.
         */
        $kueri = LetterRequest::where('village_id', $this->village->id())
            ->whereIn('status', [LetterRequest::MENUNGGU_RT, LetterRequest::MENUNGGU_KADUS]);

        return $this->grup(
            'surat',
            'Pengajuan Surat',
            'Menunggu persetujuan RT/Dusun',
            '/admin/surat',
            (clone $kueri)->count(),
            fn () => (clone $kueri)
                ->orderByDesc('created_at')
                ->limit(self::MAKS_PRATINJAU)
                ->get(['id', 'ticket_number', 'nama', 'maksud_keperluan', 'status', 'created_at'])
                ->map(fn (LetterRequest $s) => [
                    'judul' => $s->nama,
                    'ringkas' => $s->ticket_number.' · '.mb_substr((string) $s->maksud_keperluan, 0, 80),
                    'waktu' => $s->created_at->diffForHumans(),
                    'ke' => '/admin/surat',
                ])
                ->all(),
        );
    }

    /**
     * Pratinjau HANYA diambil bila grupnya memang berisi.
     *
     * Lonceng ini menempel pada setiap respons halaman dashboard. Mengambil
     * lima baris untuk tiga layanan pada setiap perpindahan halaman berarti
     * enam kueri, dan tiga di antaranya menjawab "tidak ada apa-apa".
     *
     * @param  callable(): list<array<string, string>>  $pratinjau
     * @return array<string, mixed>
     */
    private function grup(
        string $kunci,
        string $label,
        string $keterangan,
        string $ke,
        int $jumlah,
        callable $pratinjau,
    ): array {
        return [
            'kunci' => $kunci,
            'label' => $label,
            'keterangan' => $keterangan,
            'ke' => $ke,
            'jumlah' => $jumlah,
            'item' => $jumlah > 0 ? $pratinjau() : [],
        ];
    }
}
