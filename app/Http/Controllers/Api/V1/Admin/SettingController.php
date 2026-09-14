<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportantPhoneNumber;
use App\Models\Setting;
use App\Models\SocialMediaLink;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Services\NomorWhatsapp;
use App\Support\ApiResponse;
use Closure;
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
            /*
             * Kolom `whatsapp` TIDAK lagi menerima isian.
             *
             * Formulir dulu punya dua tempat berbeda untuk nomor WhatsApp desa:
             * satu kolom di "Identitas & Wilayah" — yang ternyata tidak pernah
             * ditampilkan halaman publik mana pun — dan satu baris pada daftar
             * sosial media yang memang tampil di footer. Atas permintaan
             * pemilik produk yang pertama dihapus; kolom basis datanya
             * dibiarkan agar nilai lama tidak ikut hilang.
             */

            'nomor_telepon_penting' => ['nullable', 'array'],
            'nomor_telepon_penting.*.nama_layanan' => ['required', 'string', 'max:255'],
            'nomor_telepon_penting.*.nomor' => ['required', 'string', 'max:30'],

            'sosial_media' => ['nullable', 'array'],
            'sosial_media.*.platform' => ['required', 'string', 'max:50'],
            /*
             * Bukan `url`, karena baris WhatsApp diisi NOMOR, bukan alamat.
             *
             * Operator desa tidak menghafal bentuk tautan wa.me, dan memaksa
             * mereka menyusunnya sendiri adalah cara termudah mendapatkan
             * tautan yang salah ketik. Bentuk akhirnya disusun server (lihat
             * blok penyimpanan di bawah), sehingga yang tersimpan selalu satu
             * bentuk dari mana pun asalnya.
             */
            'sosial_media.*.url' => [
                'required', 'string', 'max:255',
                function (string $atribut, mixed $nilai, Closure $gagal) use ($request) {
                    $indeks = explode('.', $atribut)[1] ?? '';
                    $platform = $request->input("sosial_media.{$indeks}.platform");

                    if (NomorWhatsapp::platformWhatsapp($platform)) {
                        if (NomorWhatsapp::tautan($nilai) === null) {
                            $gagal('Nomor WhatsApp tidak dikenali. Tulis seperti 0812-3456-7890.');
                        }

                        return;
                    }

                    if (! filter_var($nilai, FILTER_VALIDATE_URL)) {
                        $gagal('Alamat tautan tidak valid. Awali dengan https://');
                    }
                },
            ],
            'logo' => MediaService::aturanGambar(),
            // Banner TIDAK lagi di sini: hero beranda kini memuat beberapa
            // gambar dan dikelola layar tersendiri (Admin\BannerController).
            // Membiarkan satu kolom banner di formulir ini akan menghadirkan
            // dua tempat berbeda untuk menyetel gambar yang sama.
        ], [
            ...MediaService::pesanValidasi('logo'),
            'kode_wilayah.regex' => 'Kode wilayah harus berformat xx.xx.xx.xxxx (contoh: 72.10.01.2013).',
        ]);

        $villageId = $this->village->id();

        // Kedua daftar diganti utuh (hapus lalu tulis ulang) karena form CMS
        // mengirimkannya sebagai satu kesatuan. Dibungkus transaksi supaya
        // kegagalan di tengah tidak menyisakan daftar yang kosong sebagian.
        // `$request` WAJIB ikut ditangkap: closure PHP tidak mewarisi lingkup
        // pemanggilnya. Tanpa ini, `$request->hasFile(...)` di dalam blok
        // memanggil method pada null — dan karena baris itu dilalui pada
        // SETIAP penyimpanan (bukan hanya saat ada berkas), seluruh
        // penyimpanan Pengaturan Umum berakhir galat 500.
        DB::transaction(function () use ($data, $villageId, $request, &$setting) {
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
                        // Nomor WhatsApp disimpan sebagai tautan wa.me yang
                        // sudah jadi. Dengan begitu footer situs publik cukup
                        // memasangnya apa adanya pada href — tidak ada satu
                        // pun tempat lain yang perlu tahu aturan nomor WA.
                        'url' => NomorWhatsapp::platformWhatsapp($item['platform'])
                            ? NomorWhatsapp::tautan($item['url'])
                            : $item['url'],
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
