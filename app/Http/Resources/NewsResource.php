<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Berita — PRD 6.10.
 *
 * Dipakai untuk daftar maupun detail. Kolom `konten` yang berat hanya
 * disertakan pada tampilan detail, agar payload daftar tetap ringan sesuai
 * target performa PRD 12.1.
 */
class NewsResource extends JsonResource
{
    public function __construct($resource, private readonly bool $detail = false)
    {
        parent::__construct($resource);
    }

    /** Varian detail — menyertakan konten lengkap & meta SEO. */
    public static function detail($resource): self
    {
        return new self($resource, detail: true);
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'judul' => $this->judul,
            'slug' => $this->slug,
            'ringkasan' => $this->ringkasan,
            'gambar_utama' => $this->gambar_utama ? asset('storage/'.$this->gambar_utama) : null,
            'tanggal_publish' => $this->tanggal_publish?->toIso8601String(),
            'jumlah_dilihat' => $this->jumlah_dilihat,
            'kategori' => $this->whenLoaded('kategori', fn () => [
                'id' => $this->kategori->id,
                'nama' => $this->kategori->nama,
                'slug' => $this->kategori->slug,
            ]),
            'penulis' => $this->whenLoaded('penulis', fn () => $this->penulis?->name),
            'tags' => $this->whenLoaded(
                'tags',
                fn () => $this->tags->map(fn ($t) => ['nama' => $t->nama, 'slug' => $t->slug])
            ),
        ];

        if ($this->detail) {
            $data['konten'] = $this->konten;
            $data['galeri_gambar'] = collect($this->galeri_gambar ?? [])
                ->map(fn ($p) => asset('storage/'.$p))
                ->all();
            $data['meta'] = [
                // Fallback ke judul/ringkasan agar setiap artikel tetap punya
                // meta tag yang layak dibagikan meski admin tidak mengisinya
                // (PRD 12.4).
                'title' => $this->meta_title ?: $this->judul,
                'description' => $this->meta_description ?: $this->ringkasan,
                'og_image' => $this->og_image
                    ? asset('storage/'.$this->og_image)
                    : ($this->gambar_utama ? asset('storage/'.$this->gambar_utama) : null),
            ];
        }

        return $data;
    }
}
