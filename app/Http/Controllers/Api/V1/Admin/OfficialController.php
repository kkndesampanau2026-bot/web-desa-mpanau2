<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Official;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * CMS Aparat Pemerintah Desa — PRD 5.4 & 9.1.
 */
class OfficialController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function index(): JsonResponse
    {
        $data = Official::where('village_id', $this->village->id())
            ->orderBy('urutan_tampil')
            ->orderBy('nama')
            ->get();

        return ApiResponse::success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $data['village_id'] = $this->village->id();
        $data = $this->prosesFoto($request, $data);

        $official = Official::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $official, "Menambah aparat desa: {$official->nama}");

        return ApiResponse::success($official, 'Data aparat desa berhasil ditambahkan.', 201);
    }

    public function update(Request $request, Official $official): JsonResponse
    {
        $this->pastikanMilikDesaIni($official);

        $sebelum = $official->getOriginal();
        $official->update($this->prosesFoto($request, $this->validasi($request), $official->foto));
        $this->bersihkanCache();

        $this->logger->log(
            'updated', $official, "Memperbarui aparat desa: {$official->nama}",
            $sebelum, $official->getAttributes()
        );

        return ApiResponse::success($official, 'Data aparat desa berhasil diperbarui.');
    }

    public function destroy(Official $official): JsonResponse
    {
        $this->pastikanMilikDesaIni($official);

        $sebelum = $official->getOriginal();
        $nama = $official->nama;

        // Berkas ikut dihapus bersama recordnya; menyisakannya di disk berarti
        // foto seseorang tetap dapat diakses lewat URL setelah datanya dihapus.
        $this->media->hapus($official->foto);
        $official->delete();
        $this->bersihkanCache();

        $this->logger->log('deleted', $official, "Menghapus aparat desa: {$nama}", $sebelum);

        return ApiResponse::success(message: 'Data aparat desa berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', 'max:255'],
            'no_sk_pengangkatan' => ['nullable', 'string', 'max:255'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'tingkat' => ['nullable', 'integer', 'min:0', 'max:20'],
            'status_aktif' => ['nullable', 'boolean'],
            'foto' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('foto'));
    }

    /**
     * Mengganti objek berkas hasil validasi dengan path tempat ia disimpan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prosesFoto(Request $request, array $data, ?string $fotoLama = null): array
    {
        if ($request->hasFile('foto')) {
            $data['foto'] = $this->media->simpanGambar($request->file('foto'), 'aparat', $fotoLama);
        } else {
            // Tanpa ini, menyimpan formulir tanpa mengganti foto akan
            // menghapus foto yang sudah ada.
            unset($data['foto']);
        }

        return $data;
    }

    /**
     * Mencegah operator satu desa menyunting data desa lain lewat penebakan id.
     * Sudah relevan sekarang meski masih single-tenant, karena route model
     * binding mengambil record berdasarkan id saja.
     */
    private function pastikanMilikDesaIni(Official $official): void
    {
        abort_unless($official->village_id === $this->village->id(), 404);
    }

    private function bersihkanCache(): void
    {
        Cache::forget('officials:'.$this->village->id());
    }
}
