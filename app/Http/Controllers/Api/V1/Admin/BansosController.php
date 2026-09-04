<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BansosRecipient;
use App\Models\BansosSearchLog;
use App\Models\BansosType;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\PiiCipher;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CMS Bantuan Sosial — PRD 5.8 & 9.2.
 *
 * Mengikuti pola yang sama dengan modul Kependudukan: NIK tampil tersamar di
 * daftar, dan membukanya utuh menuntut permission `view-bansos-pii`
 * tersendiri yang tercatat pada audit trail.
 */
class BansosController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly PiiCipher $cipher,
    ) {}

    // ------------------------------------------------------------------
    // Master jenis bantuan
    // ------------------------------------------------------------------

    public function jenisIndex(): JsonResponse
    {
        $data = BansosType::where('village_id', $this->village->id())
            ->withCount('recipients')
            ->orderBy('urutan_tampil')
            ->get();

        return ApiResponse::success($data);
    }

    public function jenisSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'sumber_dana' => ['nullable', 'string', 'max:255'],
            'status_aktif' => ['nullable', 'boolean'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
        ]);

        $jenis = BansosType::create([
            ...$data,
            'village_id' => $this->village->id(),
            'slug' => $this->slugUnik($data['nama']),
        ]);

        $this->bersihkanCache();
        $this->logger->log('created', $jenis, "Menambah jenis bantuan: {$jenis->nama}");

        return ApiResponse::success($jenis, 'Jenis bantuan berhasil ditambahkan.', 201);
    }

    public function jenisUbah(Request $request, BansosType $bansosType): JsonResponse
    {
        abort_unless($bansosType->village_id === $this->village->id(), 404);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'sumber_dana' => ['nullable', 'string', 'max:255'],
        ]);

        $sebelum = $bansosType->getOriginal();
        $bansosType->update($data);

        Cache::forget("infografis:bansos:{$this->village->id()}");
        $this->logger->log(
            'updated', $bansosType, "Memperbarui jenis bantuan: {$bansosType->nama}",
            $sebelum, $bansosType->getAttributes()
        );

        return ApiResponse::success($bansosType, 'Jenis bantuan berhasil diperbarui.');
    }

    public function jenisHapus(BansosType $bansosType): JsonResponse
    {
        $this->pastikanMilikDesaIni($bansosType->village_id);

        // Menghapus jenis bantuan akan ikut menghapus seluruh data penerimanya
        // (cascade). Karena itu penghapusan ditolak selama masih ada penerima —
        // admin harus memindahkan atau menghapus datanya lebih dulu secara sadar.
        if ($bansosType->recipients()->exists()) {
            return ApiResponse::error(
                'Jenis bantuan ini masih memiliki data penerima. '
                .'Hapus atau pindahkan data penerimanya terlebih dahulu.',
                422
            );
        }

        $nama = $bansosType->nama;
        $bansosType->delete();

        $this->bersihkanCache();
        $this->logger->log('deleted', $bansosType, "Menghapus jenis bantuan: {$nama}");

        return ApiResponse::success(message: 'Jenis bantuan berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Data penerima
    // ------------------------------------------------------------------

    public function penerimaIndex(Request $request): JsonResponse
    {
        $request->validate([
            'cari' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:32'],
            'bansos_type_id' => ['nullable', 'integer'],
            'tahun_anggaran' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $penerima = BansosRecipient::query()
            ->where('village_id', $this->village->id())
            ->with(['jenisBantuan:id,nama', 'dusun:id,nama'])
            ->when(
                $request->string('cari')->toString(),
                fn ($q, $cari) => $q->where('nama', 'like', "%{$cari}%")
            )
            ->when(
                $request->string('nik')->toString(),
                fn ($q, $nik) => $q->where('nik_hash', $this->cipher->hash($nik))
            )
            ->when($request->integer('bansos_type_id'), fn ($q, $id) => $q->where('bansos_type_id', $id))
            ->when($request->integer('tahun_anggaran'), fn ($q, $t) => $q->where('tahun_anggaran', $t))
            ->orderBy('nama')
            ->paginate($request->integer('per_page', 25));

        $penerima->setCollection($penerima->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'nik' => $p->nikTersamar(),
            'nama' => $p->nama,
            'bansos_type_id' => $p->bansos_type_id,
            'jenis_bantuan' => $p->jenisBantuan?->nama,
            'dusun' => $p->dusun?->nama,
            'tahun_anggaran' => $p->tahun_anggaran,
            'status' => $p->status,
            'nominal' => $p->nominal === null ? null : (float) $p->nominal,
            'nominal_publik' => $p->nominal_publik,
        ]));

        return ApiResponse::paginated($penerima);
    }

    /** Membuka NIK utuh — dijaga permission tersendiri & tercatat sebagai akses. */
    public function penerimaShow(BansosRecipient $bansosRecipient): JsonResponse
    {
        $this->pastikanMilikDesaIni($bansosRecipient->village_id);

        $this->logger->logAccess(
            $bansosRecipient,
            "Melihat data pribadi penerima bansos: {$bansosRecipient->nama}"
        );

        return ApiResponse::success([
            ...$bansosRecipient->toArray(),
            'nik' => $bansosRecipient->nik,
            'no_kk' => $bansosRecipient->no_kk,
            'jenis_bantuan' => $bansosRecipient->jenisBantuan?->nama,
        ]);
    }

    public function penerimaSimpan(Request $request): JsonResponse
    {
        $data = $this->validasiPenerima($request);
        $data['village_id'] = $this->village->id();

        $penerima = BansosRecipient::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $penerima, "Menambah penerima bansos: {$penerima->nama}");

        return ApiResponse::success(
            ['id' => $penerima->id, 'nama' => $penerima->nama],
            'Data penerima berhasil ditambahkan.',
            201
        );
    }

    public function penerimaUbah(Request $request, BansosRecipient $bansosRecipient): JsonResponse
    {
        $this->pastikanMilikDesaIni($bansosRecipient->village_id);

        $sebelum = $bansosRecipient->getOriginal();
        $bansosRecipient->update($this->validasiPenerima($request, ubah: true, abaikan: $bansosRecipient));
        $this->bersihkanCache();

        $this->logger->log(
            'updated', $bansosRecipient, "Memperbarui penerima bansos: {$bansosRecipient->nama}",
            $sebelum, $bansosRecipient->getAttributes()
        );

        return ApiResponse::success(
            ['id' => $bansosRecipient->id, 'nama' => $bansosRecipient->nama],
            'Data penerima berhasil diperbarui.'
        );
    }

    public function penerimaHapus(BansosRecipient $bansosRecipient): JsonResponse
    {
        $this->pastikanMilikDesaIni($bansosRecipient->village_id);

        $nama = $bansosRecipient->nama;
        $bansosRecipient->delete();
        $this->bersihkanCache();

        $this->logger->log('deleted', $bansosRecipient, "Menghapus penerima bansos: {$nama}");

        return ApiResponse::success(message: 'Data penerima berhasil dihapus.');
    }

    /**
     * Ringkasan aktivitas pencarian publik — PRD 6.6 & 15.
     *
     * Membantu admin mengenali upaya scraping: satu IP dengan ratusan
     * pencarian gagal adalah pola enumerasi, bukan warga yang mengecek
     * statusnya sendiri.
     */
    public function pantauPencarian(): JsonResponse
    {
        $villageId = $this->village->id();
        $sejak = now()->subDays(7);

        $mencurigakan = BansosSearchLog::query()
            ->where('village_id', $villageId)
            ->where('created_at', '>=', $sejak)
            ->selectRaw('ip_hash, COUNT(*) AS jumlah')
            ->selectRaw('SUM(ditemukan = 0) AS gagal')
            ->groupBy('ip_hash')
            ->havingRaw('COUNT(*) >= 30')
            ->orderByDesc('jumlah')
            ->limit(20)
            ->get()
            ->map(fn ($b) => [
                // Hash dipotong: cukup untuk membedakan satu IP dari yang lain
                // tanpa memberi nilai penuh yang bisa dicocokkan di tempat lain.
                'ip' => substr($b->ip_hash, 0, 12).'…',
                'jumlah_pencarian' => (int) $b->jumlah,
                'gagal' => (int) $b->gagal,
            ]);

        return ApiResponse::success([
            'periode' => '7 hari terakhir',
            'total_pencarian' => BansosSearchLog::where('village_id', $villageId)
                ->where('created_at', '>=', $sejak)->count(),
            'ip_mencurigakan' => $mencurigakan,
        ]);
    }

    /**
     * @param  bool  $ubah  true saat memperbarui: NIK boleh tidak disertakan.
     * @param  BansosRecipient|null  $abaikan  baris yang sedang disunting
     * @return array<string, mixed>
     */
    private function validasiPenerima(
        Request $request,
        bool $ubah = false,
        ?BansosRecipient $abaikan = null,
    ): array {
        return $request->validate([
            'bansos_type_id' => [
                'required',
                Rule::exists('bansos_types', 'id')->where('village_id', $this->village->id()),
            ],
            'dusun_id' => [
                'nullable',
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],
            'nama' => ['required', 'string', 'max:255'],
            // Pada pembaruan NIK bersifat opsional — lihat catatan di atas.
            'nik' => [
                $ubah ? 'sometimes' : 'required', 'string', 'regex:/^\d{16}$/',
                /*
                 * Satu orang tidak boleh tercatat dua kali untuk bantuan dan
                 * tahun yang sama — indeks unik `penerima_unik_per_bantuan`
                 * menegakkannya di basis data. Diperiksa di sini lewat blind
                 * index (NIK tersimpan terenkripsi non-deterministik, jadi
                 * `Rule::unique` biasa tidak akan pernah cocok); tanpa itu,
                 * pencatatan ganda berakhir galat 500 alih-alih pesan yang
                 * memberi tahu operator bahwa warganya sudah terdaftar.
                 */
                function (string $atribut, mixed $nilai, callable $gagal) use ($request, $abaikan) {
                    $kembar = BansosRecipient::where('village_id', $this->village->id())
                        ->where('bansos_type_id', $request->integer('bansos_type_id'))
                        ->where('tahun_anggaran', $request->integer('tahun_anggaran'))
                        ->where('nik_hash', $this->cipher->hash((string) $nilai))
                        ->when($abaikan, fn ($q) => $q->whereKeyNot($abaikan->getKey()))
                        ->exists();

                    if ($kembar) {
                        $gagal('Warga dengan NIK ini sudah tercatat sebagai penerima bantuan tersebut pada tahun yang sama.');
                    }
                },
            ],
            'no_kk' => ['nullable', 'string', 'regex:/^\d{16}$/'],
            'tahun_anggaran' => ['required', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif', 'dicabut'])],
            'nominal' => ['nullable', 'numeric', 'min:0'],
            'nominal_publik' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'nik.regex' => 'NIK harus tepat 16 digit angka.',
            'no_kk.regex' => 'No. KK harus tepat 16 digit angka.',
        ]);
    }

    private function slugUnik(string $nama): string
    {
        $dasar = Str::slug($nama) ?: 'bantuan';
        $slug = $dasar;
        $n = 2;

        while (
            BansosType::where('village_id', $this->village->id())
                ->where('slug', $slug)->exists()
        ) {
            $slug = "{$dasar}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function pastikanMilikDesaIni(?int $villageId): void
    {
        abort_unless($villageId === $this->village->id(), 404);
    }

    private function bersihkanCache(): void
    {
        Cache::forget("infografis:bansos:{$this->village->id()}");
    }
}
