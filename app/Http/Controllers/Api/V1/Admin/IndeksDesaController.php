<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dusun;
use App\Models\IdmIndicator;
use App\Models\IdmScore;
use App\Models\SdgsScore;
use App\Models\StuntingRecord;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * CMS Stunting, IDM, dan SDGs — PRD 5.7, 5.9, 5.10.
 *
 * Ketiganya digabung dalam satu controller karena berbagi pola yang sama
 * (data indeks per tahun/periode dengan bendera publikasi) dan dikelola oleh
 * role yang sama.
 */
class IndeksDesaController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
    ) {}

    // ------------------------------------------------------------------
    // Stunting — PRD 6.5
    // ------------------------------------------------------------------

    /** Master dusun untuk mengisi pilihan pada form stunting per-dusun. */
    public function dusuns(): JsonResponse
    {
        $data = Dusun::where('village_id', $this->village->id())
            ->orderBy('urutan_tampil')
            ->orderBy('nama')
            ->get(['id', 'nama']);

        return ApiResponse::success($data);
    }

    public function stuntingIndex(): JsonResponse
    {
        $data = StuntingRecord::with('dusun:id,nama')
            ->where('village_id', $this->village->id())
            ->orderByDesc('periode')
            ->get()
            ->map(fn ($c) => [
                ...$c->toArray(),
                'persentase_prevalensi' => $c->prevalensi(),
            ]);

        return ApiResponse::success($data);
    }

    public function stuntingSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periode' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'dusun_id' => [
                'nullable',
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],
            'jumlah_balita_diukur' => ['required', 'integer', 'min:0'],
            // Kasus stunting tidak mungkin melebihi jumlah balita yang diukur;
            // tanpa aturan ini prevalensi bisa tampil di atas 100%.
            'jumlah_kasus_stunting' => ['required', 'integer', 'min:0', 'lte:jumlah_balita_diukur'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'periode.regex' => 'Periode harus berformat YYYY-MM (contoh: 2026-01).',
            'jumlah_kasus_stunting.lte' => 'Jumlah kasus stunting tidak boleh melebihi jumlah balita yang diukur.',
        ]);

        $catatan = StuntingRecord::updateOrCreate(
            [
                'village_id' => $this->village->id(),
                'periode' => $data['periode'],
                'dusun_id' => $data['dusun_id'] ?? null,
            ],
            $data
        );

        Cache::forget("infografis:stunting:{$this->village->id()}");

        $this->logger->log(
            $catatan->wasRecentlyCreated ? 'created' : 'updated',
            $catatan,
            "Menyimpan data stunting periode {$catatan->periode}"
        );

        return ApiResponse::success($catatan, 'Data stunting berhasil disimpan.');
    }

    public function stuntingHapus(StuntingRecord $stuntingRecord): JsonResponse
    {
        abort_unless($stuntingRecord->village_id === $this->village->id(), 404);

        $periode = $stuntingRecord->periode;
        $stuntingRecord->delete();
        Cache::forget("infografis:stunting:{$this->village->id()}");

        $this->logger->log('deleted', $stuntingRecord, "Menghapus data stunting periode {$periode}");

        return ApiResponse::success(message: 'Data stunting berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // IDM — PRD 6.7
    // ------------------------------------------------------------------

    public function idmIndex(): JsonResponse
    {
        $data = IdmScore::with('indicators')
            ->where('village_id', $this->village->id())
            ->orderByDesc('tahun')
            ->get();

        return ApiResponse::success($data);
    }

    public function idmSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'skor_iks' => ['nullable', 'numeric', 'between:0,1'],
            'skor_ike' => ['nullable', 'numeric', 'between:0,1'],
            'skor_ikl' => ['nullable', 'numeric', 'between:0,1'],
            'skor_idm' => ['nullable', 'numeric', 'between:0,1'],
            'status_idm' => ['nullable', Rule::in(IdmScore::STATUS)],
            'target_status' => ['nullable', Rule::in(IdmScore::STATUS)],
            'skor_minimal_target' => ['nullable', 'numeric', 'between:0,1'],
            'penambahan_skor_dibutuhkan' => ['nullable', 'numeric', 'between:0,1'],
            'publikasikan' => ['nullable', 'boolean'],
        ], [
            'skor_iks.between' => 'Skor IKS berada pada rentang 0 sampai 1.',
            'skor_ike.between' => 'Skor IKE berada pada rentang 0 sampai 1.',
            'skor_ikl.between' => 'Skor IKL berada pada rentang 0 sampai 1.',
        ]);

        $skor = IdmScore::updateOrCreate(
            ['village_id' => $this->village->id(), 'tahun' => $data['tahun']],
            $data
        );

        // Skor komposit dihitung otomatis bila admin tidak mengisinya sendiri
        // (PRD 6.7 memperbolehkan keduanya).
        if (blank($data['skor_idm'] ?? null)) {
            $skor->skor_idm = $skor->hitungKomposit();
            $skor->save();
        }

        $this->bersihkanCacheIdm();

        $this->logger->log(
            $skor->wasRecentlyCreated ? 'created' : 'updated',
            $skor,
            "Menyimpan skor IDM tahun {$skor->tahun}"
        );

        return ApiResponse::success($skor->fresh(), 'Skor IDM berhasil disimpan.');
    }

    public function idmHapus(IdmScore $idmScore): JsonResponse
    {
        abort_unless($idmScore->village_id === $this->village->id(), 404);

        $tahun = $idmScore->tahun;
        // Indikator ikut terhapus lewat foreign key berkaskade; menghapusnya
        // di sini juga akan menjadi dua sumber kebenaran yang bisa berbeda.
        $idmScore->delete();
        $this->bersihkanCacheIdm();

        $this->logger->log('deleted', $idmScore, "Menghapus skor IDM tahun {$tahun}");

        return ApiResponse::success(message: 'Skor IDM berhasil dihapus.');
    }

    /** Mengganti seluruh tabel indikator satu tahun sekaligus. */
    public function idmIndikatorSimpan(Request $request, IdmScore $idmScore): JsonResponse
    {
        abort_unless($idmScore->village_id === $this->village->id(), 404);

        $data = $request->validate([
            'indikator' => ['required', 'array'],
            'indikator.*.no' => ['nullable', 'integer', 'min:0'],
            'indikator.*.nama_indikator' => ['required', 'string', 'max:255'],
            'indikator.*.skor' => ['nullable', 'numeric'],
            'indikator.*.keterangan' => ['nullable', 'string', 'max:1000'],
            'indikator.*.kegiatan_rekomendasi' => ['nullable', 'string', 'max:1000'],
            'indikator.*.nilai_tambah' => ['nullable', 'numeric'],
            'indikator.*.pihak_pelaksana' => ['nullable', 'array'],
            'indikator.*.pihak_pelaksana.*' => [Rule::in(IdmIndicator::PIHAK_PELAKSANA)],
        ]);

        // Diganti utuh karena form CMS mengirim tabel indikator sebagai satu
        // kesatuan; mencocokkan baris satu per satu justru rawan meninggalkan
        // indikator lama yang sudah dihapus admin.
        $idmScore->indicators()->delete();

        foreach ($data['indikator'] as $i => $indikator) {
            $idmScore->indicators()->create([...$indikator, 'no' => $indikator['no'] ?? $i + 1]);
        }

        $this->bersihkanCacheIdm();

        $this->logger->log(
            'updated', $idmScore,
            "Memperbarui tabel indikator IDM tahun {$idmScore->tahun}"
        );

        return ApiResponse::success(
            $idmScore->load('indicators'),
            'Tabel indikator IDM berhasil disimpan.'
        );
    }

    // ------------------------------------------------------------------
    // SDGs Desa — PRD 6.8
    // ------------------------------------------------------------------

    public function sdgsIndex(Request $request): JsonResponse
    {
        $data = SdgsScore::where('village_id', $this->village->id())
            ->when($request->integer('tahun'), fn ($q, $t) => $q->where('tahun', $t))
            ->orderByDesc('tahun')
            ->orderBy('goal_number')
            ->get();

        return ApiResponse::success($data);
    }

    /** Menyimpan skor 18 tujuan sekaligus untuk satu tahun. */
    public function sdgsSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'publikasikan' => ['nullable', 'boolean'],
            'goals' => ['required', 'array', 'max:18'],
            'goals.*.goal_number' => ['required', 'integer', 'between:1,18'],
            'goals.*.nama_goal' => ['required', 'string', 'max:255'],
            'goals.*.skor' => ['nullable', 'numeric', 'between:0,100'],
            'goals.*.deskripsi_capaian' => ['nullable', 'string', 'max:1000'],
        ], [
            'goals.*.goal_number.between' => 'SDGs Desa terdiri dari 18 tujuan (1–18).',
            'goals.*.skor.between' => 'Skor SDGs berada pada rentang 0 sampai 100.',
        ]);

        foreach ($data['goals'] as $goal) {
            SdgsScore::updateOrCreate(
                [
                    'village_id' => $this->village->id(),
                    'tahun' => $data['tahun'],
                    'goal_number' => $goal['goal_number'],
                ],
                [...$goal, 'publikasikan' => $data['publikasikan'] ?? false]
            );
        }

        $villageId = $this->village->id();
        Cache::forget("infografis:sdgs:{$villageId}:terbaru");
        Cache::forget("infografis:sdgs:{$villageId}:{$data['tahun']}");

        $this->logger->log('updated', null, "Menyimpan skor SDGs Desa tahun {$data['tahun']}");

        return ApiResponse::success(message: 'Skor SDGs Desa berhasil disimpan.');
    }

    /**
     * Menghapus seluruh skor SDGs satu tahun sekaligus.
     *
     * Satuannya tahun, bukan tujuan: ke-18 tujuan disimpan dan disunting
     * sebagai satu kesatuan (lihat `sdgsSimpan`), sehingga menghapus satu
     * tujuan saja akan menyisakan tahun yang capaiannya bolong tanpa cara
     * mengembalikannya dari antarmuka.
     */
    public function sdgsHapus(int $tahun): JsonResponse
    {
        $villageId = $this->village->id();

        $jumlah = SdgsScore::where('village_id', $villageId)->where('tahun', $tahun)->delete();

        if ($jumlah === 0) {
            return ApiResponse::error('Data SDGs tahun tersebut tidak ditemukan.', 404);
        }

        Cache::forget("infografis:sdgs:{$villageId}:terbaru");
        Cache::forget("infografis:sdgs:{$villageId}:{$tahun}");

        $this->logger->log('deleted', null, "Menghapus skor SDGs Desa tahun {$tahun}");

        return ApiResponse::success(message: 'Skor SDGs Desa berhasil dihapus.');
    }

    private function bersihkanCacheIdm(): void
    {
        $villageId = $this->village->id();

        Cache::forget("infografis:idm:{$villageId}:terbaru");

        IdmScore::where('village_id', $villageId)
            ->pluck('tahun')
            ->each(fn ($t) => Cache::forget("infografis:idm:{$villageId}:{$t}"));
    }
}
