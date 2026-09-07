<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\PointOfInterest;
use App\Models\VillageProfile;
use App\Services\CurrentVillage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Peta Desa & Titik Lokasi — PRD 6.9.
 *
 * Melayani modul "Listing": peta interaktif berisi titik-titik menarik di
 * seluruh desa, terpisah dari peta lokasi kantor pada halaman Profil.
 */
class PetaController extends Controller
{
    private const TTL = 1800;

    public function __construct(private readonly CurrentVillage $village) {}

    public function listing(Request $request): Response
    {
        $request->validate(['kategori' => ['nullable', 'string', 'max:100']]);

        $villageId = $this->village->id();
        $kategori = $request->string('kategori')->toString();
        $kunci = "poi:{$villageId}:".($kategori ?: 'semua');

        $data = Cache::remember($kunci, self::TTL, function () use ($villageId, $kategori) {
            $titik = PointOfInterest::query()
                ->where('village_id', $villageId)
                ->tampil()
                ->when($kategori, fn ($q, $k) => $q->where('kategori', $k))
                ->with(['dusun:id,nama', 'tourismSpot:id,slug', 'product:id,slug'])
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nama' => $p->nama,
                    'kategori' => $p->kategori,
                    'deskripsi' => $p->deskripsi,
                    'alamat' => $p->alamat,
                    // Selalu terisi: bila perangkat desa tidak menyetel tautan
                    // sendiri, tautan pencarian dibangkitkan dari koordinat
                    // titik ini. Koordinat wajib ada, jadi popup peta tidak
                    // pernah kehilangan tombol "buka di Google Maps".
                    'tautan_maps' => $p->tautan_maps ?: 'https://www.google.com/maps/search/?api=1&query='
                        .$p->latitude.','.$p->longitude,
                    'dusun' => $p->dusun?->nama,
                    // Dikirim sebagai angka, bukan string, agar Leaflet dapat
                    // langsung memakainya tanpa konversi di sisi klien.
                    'latitude' => (float) $p->latitude,
                    'longitude' => (float) $p->longitude,
                    'foto' => $p->foto ? asset('storage/'.$p->foto) : null,
                    'icon_marker' => $p->icon_marker,
                    // Tautan ke halaman terkait bila titik ini memang destinasi
                    // wisata atau produk UMKM terdaftar (PRD 6.9).
                    'tautan' => match (true) {
                        $p->tourismSpot !== null => '/potensi/'.$p->tourismSpot->slug
                            .'?kategori=Pariwisata',
                        $p->product !== null => '/potensi/'.$p->product->slug
                            .'?kategori=Ekonomi',
                        default => null,
                    },
                ]);

            if ($titik->isEmpty()) {
                return null;   // empty-state (PRD 3.2)
            }

            $profil = VillageProfile::where('village_id', $villageId)->first();

            return [
                'titik' => $titik,
                'kategori_tersedia' => PointOfInterest::where('village_id', $villageId)
                    ->where('status_tampil', true)
                    ->distinct()
                    ->orderBy('kategori')
                    ->pluck('kategori'),
                // Pusat peta: koordinat kantor desa bila sudah diisi, jika
                // belum diambil dari rata-rata titik yang ada — supaya peta
                // tidak pernah terbuka di tengah samudra.
                'pusat_peta' => [
                    'latitude' => (float) ($profil?->latitude ?? $titik->avg('latitude')),
                    'longitude' => (float) ($profil?->longitude ?? $titik->avg('longitude')),
                ],
            ];
        });

        return Inertia::render('Publik/Peta/Listing', [
            'data' => $data,
            'filter' => ['kategori' => $kategori ?: null],
        ]);
    }
}
