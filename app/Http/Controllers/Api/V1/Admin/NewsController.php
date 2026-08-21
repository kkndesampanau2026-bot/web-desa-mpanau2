<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Mews\Purifier\Facades\Purifier;

/**
 * CMS Berita — PRD 5.11 & 9.3.
 */
class NewsController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $berita = News::query()
            ->where('village_id', $this->village->id())
            ->with(['kategori', 'penulis'])
            ->when(
                $request->string('status')->toString(),
                fn ($q, $status) => $q->where('status', $status)
            )
            ->when(
                $request->string('cari')->toString(),
                fn ($q, $cari) => $q->where('judul', 'like', "%{$cari}%")
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($berita);
    }

    public function show(News $news): JsonResponse
    {
        $this->pastikanMilikDesaIni($news);

        return ApiResponse::success($news->load(['kategori', 'penulis', 'tags']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $data['village_id'] = $this->village->id();
        $data['penulis_id'] = $request->user()->id;
        $data['slug'] = $this->slugUnik($data['judul']);
        $data['konten'] = Purifier::clean($data['konten']);
        $data['tanggal_publish'] = $this->tentukanTanggalPublish($data);

        $tags = $data['tags'] ?? [];
        unset($data['tags']);
        $data = $this->prosesGambar($request, $data);

        $berita = News::create($data);
        $berita->tags()->sync($tags);

        $this->logger->log('created', $berita, "Membuat berita: {$berita->judul}");

        return ApiResponse::success(
            $berita->load(['kategori', 'tags']),
            'Berita berhasil dibuat.',
            201
        );
    }

    public function update(Request $request, News $news): JsonResponse
    {
        $this->pastikanMilikDesaIni($news);

        $data = $this->validasi($request);
        $sebelum = $news->getOriginal();

        // Slug hanya dibuat ulang bila judul benar-benar berubah. Menjaga slug
        // tetap stabil berarti tautan yang sudah tersebar tidak putus dan
        // peringkat SEO artikel tidak hilang (PRD 12.4).
        if ($data['judul'] !== $news->judul) {
            $data['slug'] = $this->slugUnik($data['judul'], kecualikanId: $news->id);
        }

        $data['konten'] = Purifier::clean($data['konten']);
        $data['tanggal_publish'] = $this->tentukanTanggalPublish($data, $news);

        $tags = $data['tags'] ?? [];
        unset($data['tags']);
        $data = $this->prosesGambar($request, $data, $news);

        $news->update($data);
        $news->tags()->sync($tags);

        $this->logger->log(
            'updated', $news, "Memperbarui berita: {$news->judul}",
            $sebelum, $news->getAttributes()
        );

        return ApiResponse::success($news->load(['kategori', 'tags']), 'Berita berhasil diperbarui.');
    }

    public function destroy(News $news): JsonResponse
    {
        $this->pastikanMilikDesaIni($news);

        $judul = $news->judul;
        // Soft delete: berita yang keliru terhapus masih bisa dipulihkan,
        // dan penulis serta jejak auditnya tetap utuh.
        $news->delete();

        $this->logger->log('deleted', $news, "Menghapus berita: {$judul}");

        return ApiResponse::success(message: 'Berita berhasil dihapus.');
    }

    /**
     * Mengganti objek berkas hasil validasi dengan path penyimpanannya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prosesGambar(Request $request, array $data, ?News $lama = null): array
    {
        foreach (['gambar_utama', 'og_image'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $this->media->simpanGambar(
                    $request->file($field), 'berita', $lama?->{$field}
                );
            } else {
                // Tanpa ini, menyimpan artikel tanpa mengganti gambar akan
                // menghapus gambar yang sudah terpasang.
                unset($data[$field]);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'ringkasan' => ['nullable', 'string', 'max:1000'],
            'konten' => ['required', 'string'],
            'news_category_id' => [
                'nullable',
                Rule::exists('news_categories', 'id')
                    ->where('village_id', $this->village->id()),
            ],
            'status' => ['required', Rule::in(['draft', 'terjadwal', 'published', 'diarsipkan'])],
            'tanggal_publish' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                Rule::exists('news_tags', 'id')->where('village_id', $this->village->id()),
            ],
            'gambar_utama' => MediaService::aturanGambar(),
            'og_image' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('gambar_utama'));
    }

    /**
     * Menentukan waktu tayang sesuai status.
     *
     * `published` tanpa tanggal berarti "tayangkan sekarang". `terjadwal`
     * mensyaratkan tanggal yang diisi admin. Status lain tidak memerlukan
     * tanggal sama sekali.
     */
    private function tentukanTanggalPublish(array $data, ?News $news = null): ?string
    {
        if ($data['status'] === 'published') {
            return $data['tanggal_publish']
                ?? $news?->tanggal_publish?->toDateTimeString()
                ?? now()->toDateTimeString();
        }

        return $data['tanggal_publish'] ?? null;
    }

    private function slugUnik(string $judul, ?int $kecualikanId = null): string
    {
        $dasar = Str::slug($judul) ?: 'berita';
        $slug = $dasar;
        $n = 2;

        // Slug wajib unik per desa (unique index). Penomoran dilakukan di sini
        // agar admin tidak pernah melihat galat "slug sudah dipakai" — dua
        // artikel berjudul sama adalah hal yang wajar terjadi antar tahun.
        while (
            News::withTrashed()
                ->where('village_id', $this->village->id())
                ->where('slug', $slug)
                ->when($kecualikanId, fn ($q) => $q->where('id', '!=', $kecualikanId))
                ->exists()
        ) {
            $slug = "{$dasar}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function pastikanMilikDesaIni(News $news): void
    {
        abort_unless($news->village_id === $this->village->id(), 404);
    }
}
