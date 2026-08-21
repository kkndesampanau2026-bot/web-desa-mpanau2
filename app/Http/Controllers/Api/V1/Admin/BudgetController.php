<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\BudgetYear;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * CMS APBDes — PRD 5.6 & 9.2.
 */
class BudgetController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
    ) {}

    /** Daftar tahun anggaran beserta ringkasan totalnya. */
    public function tahun(): JsonResponse
    {
        $data = BudgetYear::where('village_id', $this->village->id())
            ->withSum('items as total_anggaran', 'jumlah_anggaran')
            ->orderByDesc('tahun')
            ->get();

        return ApiResponse::success($data);
    }

    public function simpanTahun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahun' => [
                'required', 'integer', 'min:2000', 'max:2100',
                Rule::unique('budget_years')->where('village_id', $this->village->id()),
            ],
            'status' => ['nullable', Rule::in(['berjalan', 'ditutup'])],
            'publikasikan' => ['nullable', 'boolean'],
        ], [
            'tahun.unique' => 'Tahun anggaran ini sudah ada.',
        ]);

        $data['village_id'] = $this->village->id();
        $tahun = BudgetYear::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $tahun, "Menambah tahun anggaran {$tahun->tahun}");

        return ApiResponse::success($tahun, 'Tahun anggaran berhasil dibuat.', 201);
    }

    public function ubahTahun(Request $request, BudgetYear $budgetYear): JsonResponse
    {
        $this->pastikanMilikDesaIni($budgetYear->village_id);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['berjalan', 'ditutup'])],
            'publikasikan' => ['nullable', 'boolean'],
        ]);

        $sebelum = $budgetYear->getOriginal();
        $budgetYear->update($data);
        $this->bersihkanCache();

        $this->logger->log(
            'updated', $budgetYear, "Memperbarui tahun anggaran {$budgetYear->tahun}",
            $sebelum, $budgetYear->getAttributes()
        );

        return ApiResponse::success($budgetYear, 'Tahun anggaran berhasil diperbarui.');
    }

    /** Master kategori (Pendapatan/Belanja/Pembiayaan). */
    public function kategori(): JsonResponse
    {
        $data = BudgetCategory::where('village_id', $this->village->id())
            ->orderBy('kelompok')
            ->orderBy('urutan_tampil')
            ->get();

        return ApiResponse::success($data);
    }

    public function simpanKategori(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kelompok' => ['required', Rule::in(BudgetCategory::KELOMPOK)],
            'nama' => ['required', 'string', 'max:255'],
            'kode' => ['nullable', 'string', 'max:20'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['village_id'] = $this->village->id();
        $kategori = BudgetCategory::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $kategori, "Menambah kategori APBDes: {$kategori->nama}");

        return ApiResponse::success($kategori, 'Kategori berhasil ditambahkan.', 201);
    }

    /** Rincian item per tahun anggaran. */
    public function items(Request $request): JsonResponse
    {
        $request->validate(['budget_year_id' => ['required', 'integer']]);

        $items = BudgetItem::with('kategori')
            ->where('village_id', $this->village->id())
            ->where('budget_year_id', $request->integer('budget_year_id'))
            ->orderBy('urutan_tampil')
            ->get();

        return ApiResponse::success($items);
    }

    public function simpanItem(Request $request): JsonResponse
    {
        $data = $this->validasiItem($request);
        $data['village_id'] = $this->village->id();

        $item = BudgetItem::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $item, "Menambah item APBDes: {$item->nama_item}");

        return ApiResponse::success($item, 'Item anggaran berhasil ditambahkan.', 201);
    }

    public function ubahItem(Request $request, BudgetItem $budgetItem): JsonResponse
    {
        $this->pastikanMilikDesaIni($budgetItem->village_id);

        $sebelum = $budgetItem->getOriginal();
        $budgetItem->update($this->validasiItem($request));
        $this->bersihkanCache();

        $this->logger->log(
            'updated', $budgetItem, "Memperbarui item APBDes: {$budgetItem->nama_item}",
            $sebelum, $budgetItem->getAttributes()
        );

        return ApiResponse::success($budgetItem, 'Item anggaran berhasil diperbarui.');
    }

    public function hapusItem(BudgetItem $budgetItem): JsonResponse
    {
        $this->pastikanMilikDesaIni($budgetItem->village_id);

        $nama = $budgetItem->nama_item;
        $budgetItem->delete();
        $this->bersihkanCache();

        $this->logger->log('deleted', $budgetItem, "Menghapus item APBDes: {$nama}");

        return ApiResponse::success(message: 'Item anggaran berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasiItem(Request $request): array
    {
        return $request->validate([
            'budget_year_id' => [
                'required',
                Rule::exists('budget_years', 'id')->where('village_id', $this->village->id()),
            ],
            'budget_category_id' => [
                'required',
                Rule::exists('budget_categories', 'id')->where('village_id', $this->village->id()),
            ],
            'nama_item' => ['required', 'string', 'max:255'],
            // Nilai anggaran tidak boleh negatif: pengurangan dinyatakan lewat
            // kelompok Pengeluaran, bukan lewat angka minus.
            'jumlah_anggaran' => ['required', 'numeric', 'min:0'],
            'jumlah_realisasi' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function pastikanMilikDesaIni(?int $villageId): void
    {
        abort_unless($villageId === $this->village->id(), 404);
    }

    private function bersihkanCache(): void
    {
        $villageId = $this->village->id();

        // Cache APBDes disimpan per tahun yang diminta, sehingga kunci
        // "terbaru" maupun kunci per tahun sama-sama perlu dibuang.
        Cache::forget("infografis:apbdes:{$villageId}:terbaru");

        BudgetYear::where('village_id', $villageId)
            ->pluck('tahun')
            ->each(fn ($t) => Cache::forget("infografis:apbdes:{$villageId}:{$t}"));
    }
}
