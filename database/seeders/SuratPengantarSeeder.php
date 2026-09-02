<?php

namespace Database\Seeders;

use App\Models\Dusun;
use App\Models\LetterOfficial;
use App\Models\Rt;
use App\Models\Village;
use Illuminate\Database\Seeder;

/**
 * Master wilayah untuk Surat Pengantar — 4 Dusun & 21 RT Desa Mpanau.
 *
 * Isinya adalah KERANGKA, bukan data final. Yang pasti dari lampiran blangko
 * desa hanyalah jumlahnya (4 dusun, 21 RT) dan satu contoh pemetaan (RT 20
 * berada di Dusun 04). Pembagian RT ke dusun selain itu dibagi rata sebagai
 * titik awal dan WAJIB dikoreksi lewat /admin/surat sebelum dipakai warga:
 * pemetaan yang keliru membuat permohonan mendarat di meja Kepala Dusun yang
 * salah.
 *
 * Nama pejabat, chat ID Telegram, dan tanda tangan sengaja TIDAK diisi di
 * sini. Ketiganya data pribadi yang tidak boleh hidup di dalam repositori;
 * seluruhnya diisi operator lewat layar CMS.
 *
 * `updateOrCreate` di seluruh berkas: seeder ini aman dijalankan berulang dan
 * tidak pernah menimpa koreksi yang sudah dibuat operator.
 */
class SuratPengantarSeeder extends Seeder
{
    /** Jumlah RT sesuai keterangan desa. */
    private const JUMLAH_RT = 21;

    /** @var list<string> */
    private const DUSUN = ['Dusun 1', 'Dusun 2', 'Dusun 3', 'Dusun 4'];

    public function run(): void
    {
        $village = Village::where('is_active', true)->orderBy('id')->first();

        if (! $village) {
            $this->command?->warn('Belum ada desa aktif; SuratPengantarSeeder dilewati.');

            return;
        }

        // VillageSeeder membuat 3 dusun placeholder. Yang keempat ditambahkan
        // di sini — bukan dengan mengubah VillageSeeder — supaya perubahan
        // fitur ini tidak menyentuh seeder yang sudah berjalan di produksi.
        $dusun = [];

        foreach (self::DUSUN as $i => $nama) {
            $dusun[] = Dusun::updateOrCreate(
                ['village_id' => $village->id, 'nama' => $nama],
                ['urutan_tampil' => $i + 1]
            );
        }

        $this->seedRt($village->id, $dusun);
        $this->siapkanKerangkaPejabat($village->id, $dusun);
    }

    /**
     * @param  list<Dusun>  $dusun
     */
    private function seedRt(int $villageId, array $dusun): void
    {
        $perDusun = (int) ceil(self::JUMLAH_RT / count($dusun));

        for ($n = 1; $n <= self::JUMLAH_RT; $n++) {
            $nomor = str_pad((string) $n, 2, '0', STR_PAD_LEFT);

            Rt::updateOrCreate(
                ['village_id' => $villageId, 'nomor' => $nomor],
                [
                    // Hanya diisi saat baris pertama kali dibuat; RT yang
                    // dusunnya sudah dikoreksi operator tidak dikembalikan
                    // ke pembagian rata ini.
                    'dusun_id' => Rt::where('village_id', $villageId)
                        ->where('nomor', $nomor)
                        ->value('dusun_id')
                        ?? $dusun[min((int) floor(($n - 1) / $perDusun), count($dusun) - 1)]->id,
                    'urutan_tampil' => $n,
                ]
            );
        }
    }

    /**
     * Membuat baris pejabat kosong untuk tiap RT & dusun.
     *
     * Dibuat NONAKTIF: baris yang belum berisi nama dan chat ID tidak boleh
     * ikut terpilih sebagai penerima notifikasi. Operator mengisi datanya,
     * lalu mengaktifkannya — sehingga tidak ada keadaan antara di mana
     * permohonan dikirim ke pejabat yang belum ada orangnya.
     *
     * @param  list<Dusun>  $dusun
     */
    private function siapkanKerangkaPejabat(int $villageId, array $dusun): void
    {
        foreach (Rt::where('village_id', $villageId)->get() as $rt) {
            LetterOfficial::firstOrCreate(
                [
                    'village_id' => $villageId,
                    'role' => LetterOfficial::ROLE_KETUA_RT,
                    'rt_id' => $rt->id,
                ],
                [
                    'nama' => 'Ketua RT '.$rt->nomor,
                    'jabatan_teks' => 'Ketua RT '.$rt->nomor,
                    'is_active' => false,
                ]
            );
        }

        foreach ($dusun as $d) {
            LetterOfficial::firstOrCreate(
                [
                    'village_id' => $villageId,
                    'role' => LetterOfficial::ROLE_KEPALA_DUSUN,
                    'dusun_id' => $d->id,
                ],
                [
                    // Bila modul Profil sudah mencatat nama kepala dusun,
                    // nilainya dipakai sebagai titik awal alih-alih dibiarkan
                    // kosong — satu data yang sama tidak perlu diketik dua kali.
                    'nama' => $d->nama_kepala_dusun ?: 'Kepala '.$d->nama,
                    'jabatan_teks' => 'Ketua Dusun '.$d->nama,
                    'is_active' => false,
                ]
            );
        }
    }
}
