<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\BudgetItem;
use App\Models\BudgetYear;
use App\Models\IdmScore;
use App\Models\PopulationStatSnapshot;
use App\Models\SdgsScore;
use App\Models\StuntingRecord;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Infografis publik — PRD 9.2.
 *
 * Aturan yang berlaku untuk SELURUH method di kelas ini: tidak satu pun
 * menyentuh tabel berisi data pribadi (`residents`, `bansos_recipients`).
 * Kependudukan dibaca dari snapshot agregat, stunting memang sudah agregat
 * sejak disimpan. Dengan begitu tidak ada jalur teknis apa pun dari halaman
 * publik menuju data individu — bukan sekadar tersaring oleh kode.
 */
class InfografisController extends Controller
{
    private const TTL = 3600;

    public function __construct(private readonly CurrentVillage $village) {}

    /** Statistik kependudukan — PRD 6.3. */
    public function penduduk(): JsonResponse
    {
        $villageId = $this->village->id();

        $data = Cache::remember("infografis:penduduk:{$villageId}", self::TTL, function () use ($villageId) {
            $snapshot = PopulationStatSnapshot::where('village_id', $villageId)
                ->orderByDesc('periode')
                ->first();

            if (! $snapshot) {
                return null;   // empty-state (PRD 3.2)
            }

            // Riwayat antar-periode untuk grafik "dari tahun ke tahun".
            $riwayat = PopulationStatSnapshot::where('village_id', $villageId)
                ->orderBy('periode')
                ->get(['periode', 'total_penduduk', 'total_kk'])
                ->map(fn ($s) => [
                    'periode' => $s->periode,
                    'total_penduduk' => $s->total_penduduk,
                    'total_kk' => $s->total_kk,
                ]);

            return [
                'periode' => $snapshot->periode,
                'ringkasan' => [
                    'total_penduduk' => $snapshot->total_penduduk,
                    'total_kk' => $snapshot->total_kk,
                    'total_laki' => $snapshot->total_laki,
                    'total_perempuan' => $snapshot->total_perempuan,
                    'total_wajib_pilih' => $snapshot->total_wajib_pilih,
                ],
                'breakdown' => $snapshot->breakdown,
                'riwayat' => $riwayat,
            ];
        });

        return ApiResponse::success($data);
    }

    /** APBDes — PRD 6.4. */
    public function apbdes(Request $request): JsonResponse
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $villageId = $this->village->id();
        $tahunDiminta = $request->integer('tahun') ?: null;

        $kunci = "infografis:apbdes:{$villageId}:".($tahunDiminta ?? 'terbaru');

        $data = Cache::remember($kunci, self::TTL, function () use ($villageId, $tahunDiminta) {
            $tahun = BudgetYear::where('village_id', $villageId)
                ->where('publikasikan', true)
                ->when($tahunDiminta, fn ($q, $t) => $q->where('tahun', $t))
                ->orderByDesc('tahun')
                ->first();

            if (! $tahun) {
                return null;
            }

            $items = BudgetItem::with('kategori')
                ->where('budget_year_id', $tahun->id)
                ->get();

            $perKelompok = $items->groupBy(fn ($i) => $i->kategori->kelompok)
                ->map(fn ($grup, $kelompok) => [
                    'kelompok' => $kelompok,
                    'total_anggaran' => (float) $grup->sum('jumlah_anggaran'),
                    'total_realisasi' => (float) $grup->sum('jumlah_realisasi'),
                    'kategori' => $grup->groupBy(fn ($i) => $i->kategori->nama)
                        ->map(fn ($sub, $nama) => [
                            'nama' => $nama,
                            'total_anggaran' => (float) $sub->sum('jumlah_anggaran'),
                            'total_realisasi' => (float) $sub->sum('jumlah_realisasi'),
                            'items' => $sub->map(fn ($i) => [
                                'nama_item' => $i->nama_item,
                                'jumlah_anggaran' => (float) $i->jumlah_anggaran,
                                'jumlah_realisasi' => $i->jumlah_realisasi === null
                                    ? null : (float) $i->jumlah_realisasi,
                            ])->values(),
                        ])->values(),
                ])->values();

            // Kelompok bisa saja belum terisi sama sekali pada tahun berjalan,
            // jadi hasil firstWhere() harus diperlakukan sebagai mungkin-null.
            $totalKelompok = fn (string $kelompok) => (float) (
                $perKelompok->firstWhere('kelompok', $kelompok)['total_anggaran'] ?? 0
            );

            $pendapatan = $totalKelompok('Pendapatan');
            $belanja = $totalKelompok('Belanja');

            return [
                'tahun' => $tahun->tahun,
                'ringkasan' => [
                    'total_pendapatan' => $pendapatan,
                    'total_belanja' => $belanja,
                    // Surplus/defisit dihitung otomatis (PRD 6.4).
                    'surplus_defisit' => round($pendapatan - $belanja, 2),
                ],
                'kelompok' => $perKelompok,
                'tren' => $this->trenApbdes($villageId),
            ];
        });

        return ApiResponse::success($data);
    }

    /** Data stunting agregat — PRD 6.5. */
    public function stunting(): JsonResponse
    {
        $villageId = $this->village->id();

        $data = Cache::remember("infografis:stunting:{$villageId}", self::TTL, function () use ($villageId) {
            $catatan = StuntingRecord::with('dusun')
                ->where('village_id', $villageId)
                ->orderByDesc('periode')
                ->get();

            if ($catatan->isEmpty()) {
                return null;
            }

            $periodeTerbaru = $catatan->first()->periode;
            $terbaru = $catatan->where('periode', $periodeTerbaru);

            $diukur = (int) $terbaru->sum('jumlah_balita_diukur');
            $kasus = (int) $terbaru->sum('jumlah_kasus_stunting');

            return [
                'periode' => $periodeTerbaru,
                'ringkasan' => [
                    'jumlah_balita_diukur' => $diukur,
                    'jumlah_kasus_stunting' => $kasus,
                    'persentase_prevalensi' => $diukur > 0
                        ? round($kasus / $diukur * 100, 2) : null,
                ],
                'per_dusun' => $terbaru->filter(fn ($c) => $c->dusun_id !== null)
                    ->map(fn ($c) => [
                        'dusun' => $c->dusun?->nama ?? 'Tidak Diketahui',
                        'jumlah_balita_diukur' => $c->jumlah_balita_diukur,
                        'jumlah_kasus_stunting' => $c->jumlah_kasus_stunting,
                        'persentase_prevalensi' => $c->prevalensi(),
                    ])->values(),
                'riwayat' => $catatan->groupBy('periode')
                    ->map(fn ($grup, $periode) => [
                        'periode' => $periode,
                        'jumlah_balita_diukur' => (int) $grup->sum('jumlah_balita_diukur'),
                        'jumlah_kasus_stunting' => (int) $grup->sum('jumlah_kasus_stunting'),
                    ])->values()->sortBy('periode')->values(),
            ];
        });

        return ApiResponse::success($data);
    }

    /** IDM beserta tabel indikator — PRD 6.7. */
    public function idm(Request $request): JsonResponse
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $villageId = $this->village->id();
        $tahunDiminta = $request->integer('tahun') ?: null;

        $kunci = "infografis:idm:{$villageId}:".($tahunDiminta ?? 'terbaru');

        $data = Cache::remember($kunci, self::TTL, function () use ($villageId, $tahunDiminta) {
            $skor = IdmScore::with('indicators')
                ->where('village_id', $villageId)
                ->where('publikasikan', true)
                ->when($tahunDiminta, fn ($q, $t) => $q->where('tahun', $t))
                ->orderByDesc('tahun')
                ->first();

            if (! $skor) {
                return null;
            }

            return [
                'tahun' => $skor->tahun,
                'skor' => [
                    'iks' => $skor->skor_iks,
                    'ike' => $skor->skor_ike,
                    'ikl' => $skor->skor_ikl,
                    'idm' => $skor->skor_idm,
                ],
                'status_idm' => $skor->status_idm,
                'target' => [
                    'status' => $skor->target_status,
                    'skor_minimal' => $skor->skor_minimal_target,
                    'penambahan_dibutuhkan' => $skor->penambahan_skor_dibutuhkan,
                ],
                'indikator' => $skor->indicators->map(fn ($i) => [
                    'no' => $i->no,
                    'nama_indikator' => $i->nama_indikator,
                    'skor' => $i->skor,
                    'keterangan' => $i->keterangan,
                    'kegiatan_rekomendasi' => $i->kegiatan_rekomendasi,
                    'nilai_tambah' => $i->nilai_tambah,
                    'pihak_pelaksana' => $i->pihak_pelaksana ?? [],
                ]),
                'tahun_tersedia' => IdmScore::where('village_id', $villageId)
                    ->where('publikasikan', true)
                    ->orderByDesc('tahun')
                    ->pluck('tahun'),
            ];
        });

        return ApiResponse::success($data);
    }

    /** SDGs Desa — PRD 6.8. */
    public function sdgs(Request $request): JsonResponse
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $villageId = $this->village->id();
        $tahunDiminta = $request->integer('tahun') ?: null;

        $kunci = "infografis:sdgs:{$villageId}:".($tahunDiminta ?? 'terbaru');

        $data = Cache::remember($kunci, self::TTL, function () use ($villageId, $tahunDiminta) {
            $tahun = $tahunDiminta ?? SdgsScore::where('village_id', $villageId)
                ->where('publikasikan', true)
                ->max('tahun');

            if (! $tahun) {
                return null;
            }

            $skor = SdgsScore::where('village_id', $villageId)
                ->where('publikasikan', true)
                ->where('tahun', $tahun)
                ->orderBy('goal_number')
                ->get();

            if ($skor->isEmpty()) {
                return null;
            }

            return [
                'tahun' => (int) $tahun,
                'goals' => $skor->map(fn ($s) => [
                    'goal_number' => $s->goal_number,
                    'nama_goal' => $s->nama_goal,
                    'skor' => $s->skor,
                    'deskripsi_capaian' => $s->deskripsi_capaian,
                ]),
                'skor_rata_rata' => round((float) $skor->avg('skor'), 2),
            ];
        });

        return ApiResponse::success($data);
    }

    /**
     * Tren pendapatan & belanja antar-tahun — PRD 6.4 menyebut grafik ini
     * butuh minimal 2 tahun anggaran untuk tampil optimal.
     */
    private function trenApbdes(int $villageId): array
    {
        return BudgetYear::where('village_id', $villageId)
            ->where('publikasikan', true)
            ->orderBy('tahun')
            ->get()
            ->map(function ($tahun) {
                $items = BudgetItem::with('kategori')->where('budget_year_id', $tahun->id)->get();

                return [
                    'tahun' => $tahun->tahun,
                    'pendapatan' => (float) $items
                        ->filter(fn ($i) => $i->kategori->kelompok === 'Pendapatan')
                        ->sum('jumlah_anggaran'),
                    'belanja' => (float) $items
                        ->filter(fn ($i) => $i->kategori->kelompok === 'Belanja')
                        ->sum('jumlah_anggaran'),
                ];
            })
            ->values()
            ->all();
    }
}
