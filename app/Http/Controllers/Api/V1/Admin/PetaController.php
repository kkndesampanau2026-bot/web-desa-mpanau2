<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointOfInterest;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * CMS Titik Lokasi (POI) — PRD 5.16 & 9.1.
 */
class PetaController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function index(): JsonResponse
    {
        $data = PointOfInterest::where('village_id', $this->village->id())
            ->with('dusun:id,nama')
            ->orderBy('kategori')
            ->orderBy('nama')
            ->get();

        return ApiResponse::success([
            'titik' => $data,
            'kategori_bawaan' => PointOfInterest::KATEGORI_BAWAAN,
        ]);
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $data['village_id'] = $this->village->id();
        $data = $this->prosesFoto($request, $data);

        $titik = PointOfInterest::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $titik, "Menambah titik lokasi: {$titik->nama}");

        return ApiResponse::success($titik, 'Titik lokasi berhasil ditambahkan.', 201);
    }

    public function ubah(Request $request, PointOfInterest $pointOfInterest): JsonResponse
    {
        $this->pastikanMilikDesaIni($pointOfInterest->village_id);

        $sebelum = $pointOfInterest->getOriginal();
        $pointOfInterest->update(
            $this->prosesFoto($request, $this->validasi($request), $pointOfInterest->foto)
        );
        $this->bersihkanCache();

        $this->logger->log(
            'updated', $pointOfInterest, "Memperbarui titik lokasi: {$pointOfInterest->nama}",
            $sebelum, $pointOfInterest->getAttributes()
        );

        return ApiResponse::success($pointOfInterest, 'Titik lokasi berhasil diperbarui.');
    }

    public function hapus(PointOfInterest $pointOfInterest): JsonResponse
    {
        $this->pastikanMilikDesaIni($pointOfInterest->village_id);

        $nama = $pointOfInterest->nama;

        $this->media->hapus($pointOfInterest->foto);
        $pointOfInterest->delete();
        $this->bersihkanCache();

        $this->logger->log('deleted', $pointOfInterest, "Menghapus titik lokasi: {$nama}");

        return ApiResponse::success(message: 'Titik lokasi berhasil dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prosesFoto(Request $request, array $data, ?string $fotoLama = null): array
    {
        if ($request->hasFile('foto')) {
            $data['foto'] = $this->media->simpanGambar($request->file('foto'), 'poi', $fotoLama);
        } else {
            unset($data['foto']);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            // Teks bebas agar desa dapat menambah kategori sendiri (PRD 6.9);
            // daftar bawaan disediakan sebagai pilihan pada form CMS.
            'kategori' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            // Koordinat WAJIB — titik peta tanpa koordinat tidak ada gunanya.
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'alamat' => ['nullable', 'string', 'max:500'],
            // Skema dibatasi http/https: nilainya dirender sebagai href
            // pada popup peta publik, sehingga 'javascript:' di sini akan
            // menjadi celah XSS bagi setiap pengunjung yang mengkliknya.
            'tautan_maps' => ['nullable', 'url:http,https', 'max:500'],
            'icon_marker' => ['nullable', 'string', 'max:50'],
            'dusun_id' => [
                'nullable',
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],
            'tourism_spot_id' => [
                'nullable',
                Rule::exists('tourism_spots', 'id')->where('village_id', $this->village->id()),
            ],
            'product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where('village_id', $this->village->id()),
            ],
            'status_tampil' => ['nullable', 'boolean'],
            'foto' => MediaService::aturanGambar(),
        ], [
            ...MediaService::pesanValidasi('foto'),
            'latitude.required' => 'Koordinat lintang (latitude) wajib diisi.',
            'longitude.required' => 'Koordinat bujur (longitude) wajib diisi.',
            'latitude.between' => 'Latitude berada pada rentang -90 sampai 90.',
            'longitude.between' => 'Longitude berada pada rentang -180 sampai 180.',
            'tautan_maps.url' => 'Tautan lokasi harus berupa URL lengkap yang diawali https://.',
        ]);
    }

    private function pastikanMilikDesaIni(?int $villageId): void
    {
        abort_unless($villageId === $this->village->id(), 404);
    }

    private function bersihkanCache(): void
    {
        $villageId = $this->village->id();

        Cache::forget("poi:{$villageId}:semua");

        // Kategori bersifat teks bebas, jadi cache per kategori dibuang
        // berdasarkan nilai yang benar-benar ada di basis data.
        PointOfInterest::where('village_id', $villageId)
            ->distinct()
            ->pluck('kategori')
            ->each(fn ($k) => Cache::forget("poi:{$villageId}:{$k}"));
    }
}
