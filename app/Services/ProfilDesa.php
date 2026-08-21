<?php

namespace App\Services;

use App\Http\Resources\BpdMemberResource;
use App\Http\Resources\OfficialResource;
use App\Models\BpdMember;
use App\Models\Official;
use App\Models\VillageProfile;
use Illuminate\Support\Facades\Cache;

/**
 * Profil Desa & SOTK (PRD 6.1–6.2).
 *
 * Seluruh data di sini nyaris tidak pernah berubah namun dibaca sangat sering,
 * sehingga di-cache sesuai strategi PRD 7.3. Cache dibatalkan oleh controller
 * admin saat data disimpan, bukan menunggu TTL habis — karena itu kunci cache
 * di bawah harus tetap sama persis dengan yang dilupakan di sisi CMS.
 *
 * Diangkat menjadi service karena dibaca dari tiga tempat: Beranda (visi),
 * halaman Profil, dan halaman Pemerintah Desa.
 */
class ProfilDesa
{
    /** Umur cache (detik) untuk data profil & SOTK. */
    private const TTL = 3600;

    public function __construct(private readonly CurrentVillage $village) {}

    /**
     * Profil desa, atau null bila admin belum mengisinya sama sekali.
     *
     * Sengaja null alih-alih melempar 404: halaman publik tetap dirender
     * lengkap dengan judul modul dan pesan "Belum Ada Data" (PRD 3.2).
     *
     * @return array<string, mixed>|null
     */
    public function data(): ?array
    {
        $villageId = $this->village->id();

        return Cache::remember("profil:{$villageId}", self::TTL, function () use ($villageId) {
            $profil = VillageProfile::where('village_id', $villageId)->first();

            if (! $profil) {
                return null;
            }

            return [
                'sambutan' => $profil->sambutan,
                'nama_kepala_desa' => $profil->nama_kepala_desa,
                'foto_kepala_desa' => $profil->foto_kepala_desa
                    ? asset('storage/'.$profil->foto_kepala_desa) : null,
                'sejarah' => $profil->sejarah,
                'visi' => $profil->visi,
                'misi' => $profil->misi ?? [],
                'bagan_pemerintahan' => $profil->bagan_pemerintahan
                    ? asset('storage/'.$profil->bagan_pemerintahan) : null,
                'bagan_bpd' => $profil->bagan_bpd
                    ? asset('storage/'.$profil->bagan_bpd) : null,
                'geografis' => [
                    'luas_desa_m2' => $profil->luas_desa_m2,
                    'luas_desa_hektar' => $profil->luasHektar(),
                    'jumlah_penduduk' => $profil->jumlah_penduduk_manual,
                    'batas' => [
                        'utara' => $profil->batas_utara,
                        'timur' => $profil->batas_timur,
                        'selatan' => $profil->batas_selatan,
                        'barat' => $profil->batas_barat,
                    ],
                ],
                'peta' => [
                    'latitude' => $profil->latitude,
                    'longitude' => $profil->longitude,
                ],
            ];
        });
    }

    /**
     * Aparat Pemerintah Desa — PRD 6.2.
     *
     * @return array<int, array<string, mixed>>
     */
    public function aparat(): array
    {
        $villageId = $this->village->id();

        return Cache::remember(
            "officials:{$villageId}",
            self::TTL,
            fn () => OfficialResource::collection(
                Official::where('village_id', $villageId)->tampil()->get()
            )->resolve()
        );
    }

    /**
     * Anggota BPD — lembaga terpisah dari aparat desa (PRD 3.2 & 6.2).
     *
     * @return array<int, array<string, mixed>>
     */
    public function bpd(): array
    {
        $villageId = $this->village->id();

        return Cache::remember(
            "bpd:{$villageId}",
            self::TTL,
            fn () => BpdMemberResource::collection(
                BpdMember::where('village_id', $villageId)->tampil()->get()
            )->resolve()
        );
    }
}
