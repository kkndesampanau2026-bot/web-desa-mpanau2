<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\VillageProfile;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mews\Purifier\Facades\Purifier;

/**
 * CMS Profil Desa — PRD 5.3 & 9.1.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function show(): JsonResponse
    {
        $profil = VillageProfile::firstOrNew(['village_id' => $this->village->id()]);

        return ApiResponse::success($profil->toArray());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sambutan' => ['nullable', 'string'],
            'nama_kepala_desa' => ['nullable', 'string', 'max:255'],
            'sejarah' => ['nullable', 'string'],
            'visi' => ['nullable', 'string'],
            'misi' => ['nullable', 'array'],
            'misi.*' => ['string', 'max:1000'],
            'luas_desa_m2' => ['nullable', 'integer', 'min:0'],
            'jumlah_penduduk_manual' => ['nullable', 'integer', 'min:0'],
            'batas_utara' => ['nullable', 'string', 'max:255'],
            'batas_timur' => ['nullable', 'string', 'max:255'],
            'batas_selatan' => ['nullable', 'string', 'max:255'],
            'batas_barat' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Berkas bersifat opsional: formulir profil kerap disimpan
            // berkali-kali tanpa mengganti gambarnya.
            'foto_kepala_desa' => MediaService::aturanGambar(),
            'bagan_pemerintahan' => MediaService::aturanGambar(),
            'bagan_bpd' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('foto_kepala_desa'));

        // Field rich text disanitasi sebelum disimpan, bukan saat ditampilkan.
        // Menyimpan HTML yang sudah bersih berarti satu titik kegagalan saja,
        // alih-alih mengandalkan setiap konsumen data ingat menyaringnya.
        foreach (['sambutan', 'sejarah'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = Purifier::clean($data[$field]);
            }
        }

        $profil = VillageProfile::firstOrNew(['village_id' => $this->village->id()]);
        $sebelum = $profil->exists ? $profil->getOriginal() : null;

        // Gambar diproses hanya bila benar-benar diunggah; path lama
        // diteruskan agar berkas yang digantikan ikut terhapus.
        foreach (['foto_kepala_desa', 'bagan_pemerintahan', 'bagan_bpd'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $this->media->simpanGambar(
                    $request->file($field), 'profil', $profil->{$field}
                );
            } else {
                unset($data[$field]);
            }
        }

        $profil->fill($data);
        $profil->village_id = $this->village->id();
        $profil->save();

        Cache::forget('profil:'.$this->village->id());

        $this->logger->log(
            $profil->wasRecentlyCreated ? 'created' : 'updated',
            $profil,
            'Memperbarui profil desa',
            $sebelum,
            $profil->getAttributes(),
        );

        return ApiResponse::success($profil->fresh()->toArray(), 'Profil desa berhasil disimpan.');
    }
}
