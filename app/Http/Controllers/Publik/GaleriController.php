<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Services\CurrentVillage;
use App\Support\Paginasi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Galeri — PRD 6.13.
 */
class GaleriController extends Controller
{
    public function __construct(private readonly CurrentVillage $village) {}

    public function index(Request $request): Response
    {
        $album = Gallery::query()
            ->where('village_id', $this->village->id())
            ->tampil()
            ->withCount('photos')
            ->paginate($request->integer('per_page', 12))
            ->withQueryString();

        $album->setCollection($album->getCollection()->map(fn ($a) => [
            'id' => $a->id,
            'nama_album' => $a->nama_album,
            'slug' => $a->slug,
            'tanggal_kegiatan' => $a->tanggal_kegiatan?->toDateString(),
            'deskripsi' => $a->deskripsi,
            'cover_image' => $a->cover_image ? asset('storage/'.$a->cover_image) : null,
            'jumlah_foto' => $a->photos_count,
        ]));

        return Inertia::render('Publik/Galeri/Index', [
            'galeri' => Paginasi::bentuk($album),
        ]);
    }

    public function show(string $slug): Response
    {
        $album = Gallery::query()
            ->where('village_id', $this->village->id())
            ->where('slug', $slug)
            ->where('status_tampil', true)
            ->with('photos')
            ->firstOrFail();

        return Inertia::render('Publik/Galeri/Detail', [
            'album' => [
                'id' => $album->id,
                'nama_album' => $album->nama_album,
                'slug' => $album->slug,
                'tanggal_kegiatan' => $album->tanggal_kegiatan?->toDateString(),
                'deskripsi' => $album->deskripsi,
                'foto' => $album->photos->map(fn ($f) => [
                    'id' => $f->id,
                    'url' => asset('storage/'.$f->path),
                    'caption' => $f->caption,
                    // Alt text wajib ada demi aksesibilitas (PRD 12.4); bila admin
                    // belum mengisinya, caption atau nama album dipakai sebagai
                    // cadangan agar pembaca layar tetap mendapat konteks.
                    'alt_text' => $f->alt_text ?: ($f->caption ?: $album->nama_album),
                ]),
            ],
        ]);
    }
}
