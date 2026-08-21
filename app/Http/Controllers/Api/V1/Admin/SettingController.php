<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportantPhoneNumber;
use App\Models\Setting;
use App\Models\SocialMediaLink;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CMS Pengaturan Umum — PRD 5.19 & 9.5.
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function show(): JsonResponse
    {
        $villageId = $this->village->id();

        return ApiResponse::success([
            'setting' => Setting::firstOrNew(['village_id' => $villageId]),
            'nomor_telepon_penting' => ImportantPhoneNumber::where('village_id', $villageId)
                ->orderBy('urutan_tampil')->get(),
            'sosial_media' => SocialMediaLink::where('village_id', $villageId)
                ->orderBy('urutan_tampil')->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_desa' => ['nullable', 'string', 'max:255'],
            // Format kode wilayah Kemendagri xx.xx.xx.xxxx — PRD 3.2 & 6.17.
            'kode_wilayah' => ['nullable', 'string', 'regex:/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/'],
            'kelurahan' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'kabupaten' => ['nullable', 'string', 'max:255'],
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'alamat_kantor' => ['nullable', 'string', 'max:1000'],
            'jam_kerja' => ['nullable', 'array'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:30'],

            'nomor_telepon_penting' => ['nullable', 'array'],
            'nomor_telepon_penting.*.nama_layanan' => ['required', 'string', 'max:255'],
            'nomor_telepon_penting.*.nomor' => ['required', 'string', 'max:30'],

            'sosial_media' => ['nullable', 'array'],
            'sosial_media.*.platform' => ['required', 'string', 'max:50'],
            'sosial_media.*.url' => ['required', 'url', 'max:255'],
            'logo' => MediaService::aturanGambar(),
        ], [
            ...MediaService::pesanValidasi('logo'),
            'kode_wilayah.regex' => 'Kode wilayah harus berformat xx.xx.xx.xxxx (contoh: 72.10.01.2013).',
        ]);

        $villageId = $this->village->id();

        // Kedua daftar diganti utuh (hapus lalu tulis ulang) karena form CMS
        // mengirimkannya sebagai satu kesatuan. Dibungkus transaksi supaya
        // kegagalan di tengah tidak menyisakan daftar yang kosong sebagian.
        DB::transaction(function () use ($data, $villageId, &$setting) {
            $setting = Setting::firstOrNew(['village_id' => $villageId]);

            if ($request->hasFile('logo')) {
                $data['logo'] = $this->media->simpanGambar(
                    $request->file('logo'), 'identitas', $setting->logo
                );
            } else {
                unset($data['logo']);
            }

            $setting->fill($data);
            $setting->village_id = $villageId;
            $setting->save();

            if (array_key_exists('nomor_telepon_penting', $data)) {
                ImportantPhoneNumber::where('village_id', $villageId)->delete();
                foreach ($data['nomor_telepon_penting'] ?? [] as $i => $item) {
                    ImportantPhoneNumber::create([
                        'village_id' => $villageId,
                        'nama_layanan' => $item['nama_layanan'],
                        'nomor' => $item['nomor'],
                        'urutan_tampil' => $i,
                    ]);
                }
            }

            if (array_key_exists('sosial_media', $data)) {
                SocialMediaLink::where('village_id', $villageId)->delete();
                foreach ($data['sosial_media'] ?? [] as $i => $item) {
                    SocialMediaLink::create([
                        'village_id' => $villageId,
                        'platform' => $item['platform'],
                        'url' => $item['url'],
                        'urutan_tampil' => $i,
                    ]);
                }
            }
        });

        Cache::forget("settings:{$villageId}");
        $this->village->lupakanCache();

        $this->logger->log('updated', $setting, 'Memperbarui pengaturan umum desa');

        return ApiResponse::success(message: 'Pengaturan berhasil disimpan.');
    }
}
