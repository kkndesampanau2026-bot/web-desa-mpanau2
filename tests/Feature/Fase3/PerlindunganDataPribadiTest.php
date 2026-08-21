<?php

namespace Tests\Feature\Fase3;

use App\Models\Resident;
use App\Models\Village;
use App\Services\PiiCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perlindungan data pribadi — PRD 6.3, 6.6 & 12.2 (UU PDP No. 27/2022).
 *
 * Berkas ini menguji jaminan paling mendasar dari sistem: NIK tidak pernah
 * tersimpan sebagai teks terbaca, namun tetap dapat dicari.
 */
class PerlindunganDataPribadiTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);
    }

    private function buatPenduduk(array $atribut = []): Resident
    {
        return Resident::create(array_merge([
            'village_id' => $this->village->id,
            'nik' => '7210010126000001',
            'no_kk' => '7210010126000099',
            'nama' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1990-05-17',
        ], $atribut));
    }

    /** Jaminan inti: NIK tidak boleh terbaca pada baris basis data. */
    public function test_nik_tersimpan_terenkripsi_bukan_teks_terbaca(): void
    {
        $this->buatPenduduk();

        $baris = DB::table('residents')->first();

        $this->assertStringNotContainsString('7210010126000001', $baris->nik);
        $this->assertStringNotContainsString('7210010126000099', $baris->no_kk);
        // Ciphertext Laravel jauh lebih panjang dari 16 digit aslinya.
        $this->assertGreaterThan(50, strlen($baris->nik));
    }

    public function test_nik_dapat_dibaca_kembali_lewat_model(): void
    {
        $id = $this->buatPenduduk()->id;

        $this->assertSame('7210010126000001', Resident::find($id)->nik);
    }

    /**
     * Alasan keberadaan blind index: pencarian exact match tanpa mendekripsi
     * seluruh tabel (PRD 6.6).
     */
    public function test_penduduk_dapat_dicari_lewat_nik_tanpa_mendekripsi_seluruh_tabel(): void
    {
        $this->buatPenduduk();
        $this->buatPenduduk(['nik' => '7210010126000002', 'nama' => 'Siti Aminah']);

        $hasil = Resident::cariNik('7210010126000001')->get();

        $this->assertCount(1, $hasil);
        $this->assertSame('Budi Santoso', $hasil->first()->nama);
    }

    /** NIK dengan spasi/tanda pisah harus dianggap sama. */
    public function test_pencarian_nik_mengabaikan_spasi_dan_tanda_pisah(): void
    {
        $this->buatPenduduk();

        $this->assertCount(1, Resident::cariNik('7210 0101 2600 0001')->get());
        $this->assertCount(1, Resident::cariNik('7210-0101-2600-0001')->get());
    }

    /** Blind index memungkinkan unique constraint pada kolom terenkripsi. */
    public function test_nik_ganda_dalam_satu_desa_ditolak_basis_data(): void
    {
        $this->buatPenduduk();

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->buatPenduduk(['nama' => 'Orang Lain']);
    }

    public function test_kolom_sensitif_tidak_ikut_terserialisasi(): void
    {
        $json = json_encode($this->buatPenduduk()->toArray());

        foreach (['nik', 'no_kk', 'nik_hash', 'no_kk_hash'] as $kolom) {
            $this->assertStringNotContainsString(
                "\"{$kolom}\":", $json,
                "Kolom sensitif `{$kolom}` tidak boleh ikut terserialisasi."
            );
        }
    }

    public function test_nik_tersamar_hanya_menampilkan_sebagian(): void
    {
        $this->assertSame('721001**********', $this->buatPenduduk()->nikTersamar());
    }

    /** PRD 6.6 mencontohkan penyamaran nama seperti "Budi S***". */
    public function test_nama_tersamar_menyisakan_kata_pertama(): void
    {
        $cipher = app(PiiCipher::class);

        $this->assertSame('Budi S******', $cipher->samarkanNama('Budi Santoso'));
        $this->assertSame('Siti A***** P******', $cipher->samarkanNama('Siti Aminah Pratiwi'));
    }

    /** Hash harus deterministik, jika tidak pencarian mustahil dilakukan. */
    public function test_hash_bersifat_deterministik_namun_berbeda_dari_nilai_asli(): void
    {
        $cipher = app(PiiCipher::class);

        $a = $cipher->hash('7210010126000001');
        $b = $cipher->hash('7210010126000001');

        $this->assertSame($a, $b);
        $this->assertSame(64, strlen($a));
        $this->assertNotSame('7210010126000001', $a);
        $this->assertNotSame($a, $cipher->hash('7210010126000002'));
    }

    public function test_status_wajib_pilih_dihitung_otomatis_dari_usia(): void
    {
        $dewasa = $this->buatPenduduk(['tanggal_lahir' => now()->subYears(20)->toDateString()]);
        $anak = $this->buatPenduduk([
            'nik' => '7210010126000003',
            'tanggal_lahir' => now()->subYears(10)->toDateString(),
        ]);

        $this->assertTrue($dewasa->status_wajib_pilih);
        $this->assertFalse($anak->status_wajib_pilih);
    }

    /**
     * Warga di bawah 17 tahun yang sudah menikah tetap masuk daftar pemilih —
     * PRD 6.3 menyebut "usia ≥17 ATAU sudah kawin".
     */
    public function test_warga_di_bawah_17_tahun_yang_sudah_kawin_tetap_wajib_pilih(): void
    {
        $penduduk = $this->buatPenduduk([
            'tanggal_lahir' => now()->subYears(16)->toDateString(),
            'status_perkawinan' => 'Kawin',
        ]);

        $this->assertTrue($penduduk->status_wajib_pilih);
    }
}
