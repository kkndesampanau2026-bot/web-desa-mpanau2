<?php

namespace App\Services;

use App\Models\ImportantPhoneNumber;
use App\Models\Setting;
use App\Models\SocialMediaLink;
use Illuminate\Support\Facades\Cache;

/**
 * Identitas & kontak desa yang dipakai kerangka halaman publik (PRD 9.5).
 *
 * Header dan footer memerlukan data ini pada SETIAP halaman, sehingga ia
 * dibagikan sebagai prop bersama Inertia, bukan diambil ulang per halaman.
 * Logikanya diangkat ke service tersendiri karena kini dipanggil dari dua
 * tempat — middleware Inertia dan endpoint API publik yang masih dipakai
 * beberapa layar CMS — dan menyalinnya berarti dua sumber kebenaran yang
 * cepat atau lambat akan berbeda isi.
 */
class PengaturanSitus
{
    private const TTL = 3600;

    public function __construct(private readonly CurrentVillage $village) {}

    /**
     * @return array<string, mixed>
     */
    public function untukTataLetak(): array
    {
        $villageId = $this->village->id();

        return Cache::remember("settings:{$villageId}", self::TTL, function () use ($villageId) {
            $setting = Setting::where('village_id', $villageId)->first();
            $village = $this->village->get();

            return [
                // Nama & kode wilayah punya cadangan dari tabel `villages`,
                // supaya footer tetap terisi meski admin belum membuka
                // halaman Pengaturan sama sekali.
                'nama_desa' => $setting?->nama_desa ?: $village->nama,
                'kode_wilayah' => $setting?->kode_wilayah ?: $village->kode_wilayah,
                'logo' => $setting?->logo ? asset('storage/'.$setting->logo) : null,
                // Null berarti "pakai banner bawaan" — Beranda menyediakan
                // berkas statisnya sendiri, lengkap dengan varian ukurannya.
                'banner' => $setting?->banner ? asset('storage/'.$setting->banner) : null,
                'wilayah' => [
                    'kelurahan' => $setting?->kelurahan,
                    'kecamatan' => $setting?->kecamatan,
                    'kabupaten' => $setting?->kabupaten,
                    'provinsi' => $setting?->provinsi,
                    'kode_pos' => $setting?->kode_pos,
                ],
                'alamat_kantor' => $setting?->alamat_kantor,
                'jam_kerja' => $setting?->jam_kerja ?? [],
                'kontak' => [
                    'telepon' => $setting?->telepon,
                    'email' => $setting?->email,
                    'whatsapp' => $setting?->whatsapp,
                ],
                'nomor_telepon_penting' => ImportantPhoneNumber::where('village_id', $villageId)
                    ->orderBy('urutan_tampil')
                    ->get(['nama_layanan', 'nomor']),
                'sosial_media' => SocialMediaLink::where('village_id', $villageId)
                    ->orderBy('urutan_tampil')
                    ->get(['platform', 'url']),
            ];
        });
    }
}
