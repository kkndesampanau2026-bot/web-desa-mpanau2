<?php

namespace App\Services;

use App\Models\PopulationStatSnapshot;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung agregat kependudukan dan menyimpannya sebagai snapshot —
 * PRD 6.3, 7.3 & 12.1.
 *
 * Kelas ini adalah satu-satunya tempat tabel `residents` diringkas. Hasilnya
 * ditulis ke `population_stat_snapshots`, dan endpoint publik hanya membaca
 * tabel snapshot itu. Pemisahan tersebut disengaja: halaman publik secara
 * struktur tidak pernah menyentuh tabel berisi data pribadi, sehingga
 * kebocoran lewat query publik menjadi mustahil — bukan sekadar tercegah
 * oleh filter yang bisa terlupa.
 *
 * Seluruh perhitungan dilakukan sebagai GROUP BY di sisi basis data, bukan
 * dengan memuat baris ke PHP, agar tetap sanggup menangani puluhan ribu
 * penduduk.
 */
class PopulationAggregator
{
    /**
     * Rentang kelompok umur (tahun, inklusif). PRD 6.3 menyebut rentang ini
     * dapat dikonfigurasi admin; sampai halaman pengaturannya dibuat, nilai
     * berikut menjadi bawaan yang lazim dipakai profil desa.
     *
     * @var array<string, array{int, int|null}>
     */
    public const KELOMPOK_UMUR = [
        'Balita (0-4)' => [0, 4],
        'Anak (5-12)' => [5, 12],
        'Remaja (13-17)' => [13, 17],
        'Dewasa (18-59)' => [18, 59],
        'Lansia (60+)' => [60, null],
    ];

    /** Membuat/memperbarui snapshot untuk sebuah periode (default: bulan ini). */
    public function buatSnapshot(int $villageId, ?string $periode = null): PopulationStatSnapshot
    {
        $periode ??= now()->format('Y-m');

        $dasar = Resident::query()->where('village_id', $villageId);

        $ringkasan = (clone $dasar)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(jenis_kelamin = 'L') AS laki")
            ->selectRaw("SUM(jenis_kelamin = 'P') AS perempuan")
            ->selectRaw('SUM(status_wajib_pilih = 1) AS wajib_pilih')
            // Dihitung dari blind index: jumlah KK unik bisa didapat tanpa
            // pernah mendekripsi satu pun nomor KK.
            ->selectRaw('COUNT(DISTINCT no_kk_hash) AS total_kk')
            ->first();

        return PopulationStatSnapshot::updateOrCreate(
            ['village_id' => $villageId, 'periode' => $periode],
            [
                'total_penduduk' => (int) $ringkasan->total,
                'total_kk' => (int) $ringkasan->total_kk,
                'total_laki' => (int) $ringkasan->laki,
                'total_perempuan' => (int) $ringkasan->perempuan,
                'total_wajib_pilih' => (int) $ringkasan->wajib_pilih,
                'breakdown' => [
                    'kelompok_umur' => $this->kelompokUmur($villageId),
                    'dusun' => $this->perDusun($villageId),
                    'pendidikan' => $this->perKolom($villageId, 'pendidikan_terakhir'),
                    'pekerjaan' => $this->perKolom($villageId, 'pekerjaan'),
                    'perkawinan' => $this->perKolom($villageId, 'status_perkawinan'),
                    'agama' => $this->perKolom($villageId, 'agama'),
                ],
            ]
        );
    }

    /**
     * Rincian per kelompok umur.
     *
     * Usia dihitung di dalam query lewat TIMESTAMPDIFF, bukan disimpan sebagai
     * kolom, supaya tidak ada data yang basi seiring bertambahnya waktu.
     *
     * @return list<array{label: string, jumlah: int}>
     */
    private function kelompokUmur(int $villageId): array
    {
        $hasil = [];

        foreach (self::KELOMPOK_UMUR as $label => [$min, $max]) {
            $query = Resident::query()
                ->where('village_id', $villageId)
                ->whereNotNull('tanggal_lahir')
                ->whereRaw('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) >= ?', [$min]);

            if ($max !== null) {
                $query->whereRaw('TIMESTAMPDIFF(YEAR, tanggal_lahir, CURDATE()) <= ?', [$max]);
            }

            $hasil[] = ['label' => $label, 'jumlah' => $query->count()];
        }

        return $hasil;
    }

    /**
     * Rincian per dusun. Memakai LEFT JOIN agar penduduk yang belum
     * ditetapkan dusunnya tetap terhitung, bukan hilang diam-diam dari
     * total — selisih angka semacam itu sulit ditelusuri di kemudian hari.
     *
     * @return list<array{label: string, jumlah: int}>
     */
    private function perDusun(int $villageId): array
    {
        return DB::table('residents')
            ->leftJoin('dusuns', 'residents.dusun_id', '=', 'dusuns.id')
            ->where('residents.village_id', $villageId)
            ->whereNull('residents.deleted_at')
            ->selectRaw('COALESCE(dusuns.nama, ?) AS label, COUNT(*) AS jumlah', ['Belum Ditentukan'])
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($b) => ['label' => $b->label, 'jumlah' => (int) $b->jumlah])
            ->all();
    }

    /**
     * Rincian menurut satu kolom enum/teks.
     *
     * @return list<array{label: string, jumlah: int}>
     */
    private function perKolom(int $villageId, string $kolom): array
    {
        return Resident::query()
            ->where('village_id', $villageId)
            ->selectRaw("COALESCE(NULLIF({$kolom}, ''), 'Tidak Diketahui') AS label, COUNT(*) AS jumlah")
            ->groupBy('label')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($b) => ['label' => $b->label, 'jumlah' => (int) $b->jumlah])
            ->all();
    }
}
