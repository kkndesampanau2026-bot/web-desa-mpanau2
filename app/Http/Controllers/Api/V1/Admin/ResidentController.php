<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dusun;
use App\Models\Resident;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\PiiCipher;
use App\Services\PopulationAggregator;
use App\Services\ResidentCsvImporter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CMS Data Penduduk — PRD 5.5, 9.2 & 12.2.
 *
 * Endpoint paling sensitif dalam sistem. Tiga aturan yang berlaku di sini:
 *
 *  1. NIK & No. KK TIDAK PERNAH dikembalikan utuh, bahkan kepada admin,
 *     kecuali lewat method `show()` yang menuntut permission `view-population-pii`
 *     tersendiri dan mencatat aksesnya ke audit trail.
 *  2. Setiap perubahan dicatat ActivityLogger (PRD 12.2).
 *  3. Snapshot agregat diperbarui setelah data berubah, agar halaman publik
 *     ikut mutakhir tanpa menyentuh tabel ini.
 */
class ResidentController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly PiiCipher $cipher,
        private readonly PopulationAggregator $aggregator,
    ) {}

    /**
     * Daftar penduduk. NIK ditampilkan tersamar — daftar semacam ini paling
     * mudah tersalin keluar, sehingga tidak pernah memuat NIK utuh.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cari' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:32'],
            'dusun_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $penduduk = Resident::query()
            ->where('village_id', $this->village->id())
            ->with('dusun:id,nama')
            ->when(
                $request->string('cari')->toString(),
                fn ($q, $cari) => $q->where('nama', 'like', "%{$cari}%")
            )
            // Pencarian NIK lewat blind index — tanpa mendekripsi satu baris pun.
            ->when(
                $request->string('nik')->toString(),
                fn ($q, $nik) => $q->where('nik_hash', $this->cipher->hash($nik))
            )
            ->when($request->integer('dusun_id'), fn ($q, $id) => $q->where('dusun_id', $id))
            ->orderBy('nama')
            ->paginate($request->integer('per_page', 25));

        $penduduk->setCollection($penduduk->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'nik' => $p->nikTersamar(),
            'nama' => $p->nama,
            'jenis_kelamin' => $p->jenis_kelamin,
            'tanggal_lahir' => $p->tanggal_lahir?->toDateString(),
            'dusun' => $p->dusun?->nama,
            'pekerjaan' => $p->pekerjaan,
            'status_wajib_pilih' => $p->status_wajib_pilih,
        ]));

        return ApiResponse::paginated($penduduk);
    }

    /**
     * Detail satu penduduk beserta NIK utuh.
     *
     * Dipisahkan dari index() dan dijaga permission `view-population-pii`
     * karena inilah satu-satunya jalur yang membuka data pribadi. Setiap
     * pemanggilan dicatat sebagai jejak AKSES — UU PDP menuntut pembacaan
     * data pribadi ikut tertelusuri, bukan hanya perubahannya.
     */
    public function show(Resident $resident): JsonResponse
    {
        $this->pastikanMilikDesaIni($resident);

        $this->logger->logAccess(
            $resident,
            "Melihat data pribadi penduduk: {$resident->nama}"
        );

        return ApiResponse::success([
            ...$resident->toArray(),
            'nik' => $resident->nik,
            'no_kk' => $resident->no_kk,
            'dusun' => $resident->dusun?->nama,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $data['village_id'] = $this->village->id();

        $penduduk = Resident::create($data);
        $this->segarkanAgregat();

        // Nilai NIK tidak diteruskan ke log — ActivityLogger menyaringnya,
        // dan deskripsi pun sengaja hanya memuat nama.
        $this->logger->log('created', $penduduk, "Menambah penduduk: {$penduduk->nama}");

        return ApiResponse::success(
            ['id' => $penduduk->id, 'nama' => $penduduk->nama],
            'Data penduduk berhasil ditambahkan.',
            201
        );
    }

    public function update(Request $request, Resident $resident): JsonResponse
    {
        $this->pastikanMilikDesaIni($resident);

        $sebelum = $resident->getOriginal();
        $resident->update($this->validasi($request, $resident));
        $this->segarkanAgregat();

        $this->logger->log(
            'updated', $resident, "Memperbarui penduduk: {$resident->nama}",
            $sebelum, $resident->getAttributes()
        );

        return ApiResponse::success(
            ['id' => $resident->id, 'nama' => $resident->nama],
            'Data penduduk berhasil diperbarui.'
        );
    }

    public function destroy(Resident $resident): JsonResponse
    {
        $this->pastikanMilikDesaIni($resident);

        $nama = $resident->nama;
        // Soft delete: pencatatan mutasi penduduk (pindah/meninggal) menuntut
        // riwayat tetap dapat ditelusuri, bukan hilang seketika.
        $resident->delete();
        $this->segarkanAgregat();

        $this->logger->log('deleted', $resident, "Menghapus penduduk: {$nama}");

        return ApiResponse::success(message: 'Data penduduk berhasil dihapus.');
    }

    /** Template CSV resmi — PRD 6.3 & 10.5. */
    public function templateCsv(): StreamedResponse
    {
        $kolom = ResidentCsvImporter::KOLOM_TEMPLATE;

        return response()->streamDownload(function () use ($kolom) {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel di Windows membaca huruf beraksen dengan benar.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, $kolom);
            fputcsv($keluaran, [
                '7210010126000001', 'Nama Contoh', 'L', '1990-05-17',
                '7210010126000099', 'Kepala Keluarga', 'Dusun 1',
                'SLTA/Sederajat', 'Petani', 'Kawin', 'Islam', 'Penduduk Tetap',
            ]);

            fclose($keluaran);
        }, 'template-data-penduduk.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Impor CSV — PRD 10.5.
     *
     * Diproses sinkron agar operator langsung menerima ringkasan hasil per
     * baris. PRD 7.3 menyebut impor sebaiknya lewat queue; itu menjadi
     * relevan pada volume besar dan menunggu Redis tersedia (lihat DEVIASI A3).
     */
    public function importCsv(Request $request, ResidentCsvImporter $importer): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Berkas harus berformat CSV.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);

        $baris = $this->bacaCsv($request->file('file')->getRealPath());

        if ($baris === null) {
            return ApiResponse::error(
                'Baris header CSV tidak sesuai. Wajib memuat kolom: '
                .implode(', ', ResidentCsvImporter::KOLOM_WAJIB).'.',
                422
            );
        }

        if ($baris === []) {
            return ApiResponse::error('Berkas CSV tidak berisi data.', 422);
        }

        // Nama dusun pada CSV dipetakan ke id; dicocokkan huruf kecil supaya
        // "Dusun 1" dan "dusun 1" sama-sama dikenali.
        $petaDusun = Dusun::where('village_id', $this->village->id())
            ->pluck('id', 'nama')
            ->mapWithKeys(fn ($id, $nama) => [strtolower($nama) => $id])
            ->all();

        $hasil = $importer->impor($baris, $this->village->id(), $petaDusun);
        $this->segarkanAgregat();

        $this->logger->log(
            'imported', null,
            "Impor CSV penduduk: {$hasil['berhasil']} berhasil, {$hasil['gagal']} gagal"
        );

        return ApiResponse::success($hasil, 'Impor selesai diproses.');
    }

    /**
     * Membaca CSV menjadi larik asosiatif.
     *
     * @return array<int, array<string, string>>|null null bila header tidak sah
     */
    private function bacaCsv(string $path): ?array
    {
        $berkas = fopen($path, 'r');
        $header = fgetcsv($berkas);

        if ($header === false) {
            fclose($berkas);

            return null;
        }

        // Membuang BOM yang disisipkan Excel pada kolom pertama; tanpa ini
        // nama kolom pertama tidak akan pernah cocok.
        $header = array_map(
            fn ($k) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $k))),
            $header
        );

        if (array_diff(ResidentCsvImporter::KOLOM_WAJIB, $header) !== []) {
            fclose($berkas);

            return null;
        }

        $baris = [];

        while (($data = fgetcsv($berkas)) !== false) {
            // Melewati baris kosong yang lazim tertinggal di akhir berkas Excel.
            if ($data === [null] || implode('', array_map('strval', $data)) === '') {
                continue;
            }

            // Panjang baris disamakan dengan header agar array_combine tidak gagal
            // saat ada kolom yang tidak terisi di ujung baris.
            $data = array_pad(array_slice($data, 0, count($header)), count($header), '');
            $baris[] = array_combine($header, $data);
        }

        fclose($berkas);

        return $baris;
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Resident $abaikan = null): array
    {
        return $request->validate([
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'no_kk' => ['nullable', 'string', 'regex:/^\d{16}$/'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'dusun_id' => [
                'nullable',
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],
            'status_hubungan_kk' => ['nullable', Rule::in(['Kepala Keluarga', 'Istri', 'Anak', 'Lainnya'])],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
            'status_perkawinan' => ['nullable', Rule::in(['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'])],
            'agama' => ['nullable', 'string', 'max:20'],
            'status_domisili' => ['nullable', Rule::in(['Penduduk Tetap', 'Penduduk Sementara'])],
            'status_mutasi' => ['nullable', Rule::in(['Lahir', 'Mati', 'Pindah Masuk', 'Pindah Keluar'])],
            'tanggal_mutasi' => ['nullable', 'date'],
        ], [
            'nik.regex' => 'NIK harus tepat 16 digit angka.',
            'no_kk.regex' => 'No. KK harus tepat 16 digit angka.',
        ]);
    }

    /** Memperbarui snapshot agregat & membuang cache infografis publik. */
    private function segarkanAgregat(): void
    {
        $villageId = $this->village->id();

        $this->aggregator->buatSnapshot($villageId);
        Cache::forget("infografis:penduduk:{$villageId}");
    }

    private function pastikanMilikDesaIni(Resident $resident): void
    {
        abort_unless($resident->village_id === $this->village->id(), 404);
    }
}
