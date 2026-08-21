<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CMS Galeri Kegiatan — PRD 5.15 & 6.13.
 *
 * Album adalah wadah; isinya foto yang diunggah terpisah. Pemisahan ini
 * disengaja: mengunggah 30 foto sekaligus lewat satu form album berarti satu
 * kegagalan jaringan membatalkan seluruh pekerjaan admin. Dengan endpoint foto
 * tersendiri, album tersimpan lebih dulu dan unggahan dapat diulang per batch.
 */
class GalleryController extends Controller
{
    /** Batas foto per satu kali unggah — menjaga request tetap di bawah post_max_size. */
    private const MAKS_FOTO_SEKALI_UNGGAH = 10;

    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $album = Gallery::query()
            ->where('village_id', $this->village->id())
            ->withCount('photos')
            ->when(
                $request->string('cari')->toString(),
                fn ($q, $cari) => $q->where('nama_album', 'like', "%{$cari}%")
            )
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($album);
    }

    public function show(Gallery $gallery): JsonResponse
    {
        $this->pastikanMilikDesaIni($gallery);

        return ApiResponse::success($gallery->load('photos'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $data['village_id'] = $this->village->id();
        $data['slug'] = $this->slugUnik($data['nama_album']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->media->simpanGambar(
                $request->file('cover_image'), 'galeri'
            );
        }

        $album = Gallery::create($data);

        $this->logger->log('created', $album, "Membuat album galeri: {$album->nama_album}");

        return ApiResponse::success($album, 'Album galeri berhasil dibuat.', 201);
    }

    public function update(Request $request, Gallery $gallery): JsonResponse
    {
        $this->pastikanMilikDesaIni($gallery);

        $data = $this->validasi($request);
        $sebelum = $gallery->getOriginal();

        // Slug hanya dibuat ulang bila nama berubah, supaya tautan album yang
        // sudah tersebar tidak putus.
        if ($data['nama_album'] !== $gallery->nama_album) {
            $data['slug'] = $this->slugUnik($data['nama_album'], $gallery->id);
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->media->simpanGambar(
                $request->file('cover_image'), 'galeri', $gallery->cover_image
            );
        } else {
            // Tanpa ini, menyimpan form tanpa mengganti sampul akan menghapus
            // sampul yang sudah terpasang.
            unset($data['cover_image']);
        }

        $gallery->update($data);

        $this->logger->log(
            'updated', $gallery, "Memperbarui album galeri: {$gallery->nama_album}",
            $sebelum, $gallery->getAttributes()
        );

        return ApiResponse::success($gallery, 'Album galeri berhasil diperbarui.');
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $this->pastikanMilikDesaIni($gallery);

        $nama = $gallery->nama_album;

        // Berkas dihapus lebih dulu selagi barisnya masih ada. Foto anak ikut
        // terhapus lewat cascade di basis data, jadi path-nya harus dikumpulkan
        // sekarang atau berkasnya menjadi sampah yang tak terlacak.
        foreach ($gallery->photos as $foto) {
            $this->media->hapus($foto->path);
        }
        $this->media->hapus($gallery->cover_image);

        $gallery->delete();

        $this->logger->log('deleted', $gallery, "Menghapus album galeri: {$nama}");

        return ApiResponse::success(message: 'Album galeri berhasil dihapus.');
    }

    /**
     * Mengunggah satu batch foto ke dalam album.
     */
    public function unggahFoto(Request $request, Gallery $gallery): JsonResponse
    {
        $this->pastikanMilikDesaIni($gallery);

        $request->validate([
            'foto' => ['required', 'array', 'max:'.self::MAKS_FOTO_SEKALI_UNGGAH],
            'foto.*' => MediaService::aturanGambar(wajib: true),
            'caption' => ['nullable', 'array'],
            'caption.*' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'array'],
            'alt_text.*' => ['nullable', 'string', 'max:255'],
        ], [
            ...MediaService::pesanValidasi('foto.*'),
            'foto.required' => 'Pilih setidaknya satu foto untuk diunggah.',
            'foto.max' => 'Maksimal '.self::MAKS_FOTO_SEKALI_UNGGAH.' foto dalam sekali unggah.',
        ]);

        // Urutan melanjutkan foto yang sudah ada, bukan dimulai dari nol —
        // supaya batch kedua tidak menumpuk di posisi yang sama dengan batch
        // pertama dan membuat urutan tampil menjadi acak.
        $urutan = (int) $gallery->photos()->max('urutan_tampil');

        $tersimpan = DB::transaction(function () use ($request, $gallery, &$urutan) {
            $hasil = [];

            foreach ($request->file('foto') as $i => $berkas) {
                $hasil[] = $gallery->photos()->create([
                    'path' => $this->media->simpanGambar($berkas, 'galeri'),
                    'caption' => $request->input("caption.{$i}"),
                    'alt_text' => $request->input("alt_text.{$i}"),
                    'urutan_tampil' => ++$urutan,
                ]);
            }

            return $hasil;
        });

        // Album tanpa sampul memakai foto pertama yang diunggah, agar admin
        // tidak perlu mengunggah gambar yang sama dua kali.
        if (! $gallery->cover_image && $tersimpan !== []) {
            $gallery->update(['cover_image' => $tersimpan[0]->path]);
        }

        $jumlah = count($tersimpan);
        $this->logger->log(
            'created', $gallery,
            "Mengunggah {$jumlah} foto ke album: {$gallery->nama_album}"
        );

        return ApiResponse::success($tersimpan, "{$jumlah} foto berhasil diunggah.", 201);
    }

    public function ubahFoto(Request $request, Gallery $gallery, GalleryPhoto $photo): JsonResponse
    {
        $this->pastikanMilikDesaIni($gallery);
        abort_unless($photo->gallery_id === $gallery->id, 404);

        $photo->update($request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
        ]));

        return ApiResponse::success($photo, 'Keterangan foto berhasil diperbarui.');
    }

    public function hapusFoto(Gallery $gallery, GalleryPhoto $photo): JsonResponse
    {
        $this->pastikanMilikDesaIni($gallery);
        abort_unless($photo->gallery_id === $gallery->id, 404);

        // Sampul yang menunjuk foto ini ikut dikosongkan; membiarkannya berarti
        // album menampilkan gambar yang berkasnya sudah tidak ada.
        if ($gallery->cover_image === $photo->path) {
            $gallery->update(['cover_image' => null]);
        }

        $this->media->hapus($photo->path);
        $photo->delete();

        $this->logger->log('deleted', $gallery, "Menghapus foto dari album: {$gallery->nama_album}");

        return ApiResponse::success(message: 'Foto berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama_album' => ['required', 'string', 'max:255'],
            'tanggal_kegiatan' => ['nullable', 'date'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'status_tampil' => ['nullable', 'boolean'],
            'cover_image' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('cover_image'));
    }

    private function slugUnik(string $nama, ?int $kecualikanId = null): string
    {
        $dasar = Str::slug($nama) ?: 'album';
        $slug = $dasar;
        $n = 2;

        while (
            Gallery::where('village_id', $this->village->id())
                ->where('slug', $slug)
                ->when($kecualikanId, fn ($q) => $q->where('id', '!=', $kecualikanId))
                ->exists()
        ) {
            $slug = "{$dasar}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function pastikanMilikDesaIni(Gallery $gallery): void
    {
        abort_unless($gallery->village_id === $this->village->id(), 404);
    }
}
