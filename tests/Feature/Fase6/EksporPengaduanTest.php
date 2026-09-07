<?php

namespace Tests\Feature\Fase6;

use App\Models\Complaint;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Rekap pengaduan sebagai berkas Excel.
 *
 * Berkasnya memuat data pribadi pelapor, jadi yang diuji bukan hanya "kolomnya
 * benar" melainkan juga bahwa jalannya keluar dijaga izin dan meninggalkan
 * jejak audit — ekspor adalah cara termudah memindahkan seluruh data pelapor
 * keluar dari sistem sekaligus.
 */
class EksporPengaduanTest extends TestCase
{
    use RefreshDatabase;

    private Village $desa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->desa = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);
    }

    private function operator(string $peran = 'Admin Utama'): User
    {
        $user = User::create([
            'name' => 'Operator Uji',
            'email' => 'op'.uniqid().'@uji.test',
            'password' => Hash::make('rahasia123'),
            'village_id' => $this->desa->id,
        ]);
        $user->assignRole($peran);

        return $user;
    }

    private function buatPengaduan(array $ubahan = []): Complaint
    {
        return Complaint::create(array_merge([
            'village_id' => $this->desa->id,
            'nomor_tiket' => Complaint::buatNomorTiket(),
            'nama' => 'Warga Uji',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'PEK. UMUM',
            'isi_pengaduan' => 'Jalan berlubang di depan balai desa.',
            'status' => 'baru',
        ], $ubahan));
    }

    /**
     * Membaca kembali berkas .xlsx yang diunduh menjadi larik baris.
     *
     * @return array<int, array<int, mixed>>
     */
    private function bacaXlsx(\Illuminate\Testing\TestResponse $respons): array
    {
        $sementara = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($sementara, $respons->streamedContent());

        $baris = IOFactory::load($sementara)->getActiveSheet()->toArray();
        @unlink($sementara);

        return $baris;
    }

    // -----------------------------------------------------------------
    // Kategori baru
    // -----------------------------------------------------------------

    public function test_kategori_baru_diterima_basis_data_dan_yang_lama_ditolak(): void
    {
        // Kolomnya ENUM: kalau migrasinya tidak ikut mengubah definisi kolom,
        // baris ini gagal di tingkat basis data, bukan di validasi.
        foreach (Complaint::KATEGORI as $kategori) {
            $p = $this->buatPengaduan(['kategori_pengaduan' => $kategori]);
            $this->assertSame($kategori, $p->fresh()->kategori_pengaduan);
        }

        $this->assertSame(
            ['PENDIDIKAN', 'KESEHATAN', 'PEK. UMUM', 'PERUMAHAN', 'TRANTIBUM', 'SOSIAL'],
            Complaint::KATEGORI
        );
    }

    public function test_formulir_publik_menawarkan_dan_menerima_kategori_baru(): void
    {
        // Halaman formulirnya sudah tidak ada; kategori kini sampai ke warga
        // lewat prop bersama yang menyertai SETIAP halaman publik, karena
        // formulirnya berupa popup mengambang.
        $this->get('/')->assertOk()->assertInertia(
            fn ($page) => $page->where('kategori_pengaduan', Complaint::KATEGORI)
        );

        $this->post('/pengaduan', [
            'nama' => 'Warga Uji',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'TRANTIBUM',
            'isi_pengaduan' => 'Lampu jalan mati sejak pekan lalu di RT 02.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaints', ['kategori_pengaduan' => 'TRANTIBUM']);
    }

    public function test_halaman_formulir_pengaduan_sudah_tidak_ada(): void
    {
        // Mengadu kini hanya lewat popup mengambang. Alamat lamanya dialihkan,
        // bukan dibiarkan menjadi 404 — ia sempat beredar dan terindeks.
        $this->get('/pengaduan')
            ->assertRedirect('/layanan-mandiri')
            ->assertStatus(301);
    }

    public function test_alamat_lacak_lama_dialihkan_beserta_nomor_tiketnya(): void
    {
        $this->get('/pengaduan/lacak')
            ->assertRedirect('/layanan-mandiri/lacak')
            ->assertStatus(301);

        // Nomor tiket beredar bersama tautannya; membuangnya saat mengalihkan
        // memaksa warga mengetik ulang tiket yang sudah ada di tangannya.
        $this->get('/pengaduan/lacak?tiket=PGD-20260101-0001')
            ->assertRedirect('/layanan-mandiri/lacak?tiket=PGD-20260101-0001');
    }

    public function test_mengadu_mengarahkan_ke_halaman_lacak_beserta_tanda_terima(): void
    {
        // POST /pengaduan HARUS tetap sampai ke controller, bukan tertelan
        // pengalihan GET yang menempati alamat yang sama. Bila ia tertelan,
        // popup "Aduan Warga" akan menjawab "berhasil" tanpa pernah menyimpan
        // apa pun — kegagalan paling buruk yang mungkin terjadi di sini.
        $this->assertSame(0, Complaint::count());

        $this->post('/pengaduan', [
            'nama' => 'Warga Uji',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'PERUMAHAN',
            'isi_pengaduan' => 'Atap balai dusun bocor sejak hujan pekan lalu.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Complaint::count(), 'Aduan seharusnya benar-benar tersimpan.');

        $pengaduan = Complaint::latest('id')->firstOrFail();

        $this->get("/layanan-mandiri/lacak?tiket={$pengaduan->nomor_tiket}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pengaduan.nomor_tiket', $pengaduan->nomor_tiket)
            );
    }

    public function test_kategori_lama_ditolak_validasi(): void
    {
        $this->post('/pengaduan', [
            'nama' => 'Warga Uji',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'Kebersihan',
            'isi_pengaduan' => 'Sampah menumpuk di ujung gang sejak tiga hari.',
        ])->assertSessionHasErrors('kategori_pengaduan');
    }

    // -----------------------------------------------------------------
    // Ekspor
    // -----------------------------------------------------------------

    public function test_rekap_memuat_tujuh_kolom_yang_diminta(): void
    {
        $this->buatPengaduan();

        $respons = $this->actingAs($this->operator())
            ->get('/admin/pengaduan/ekspor')
            ->assertOk();

        $this->assertStringContainsString(
            'spreadsheetml',
            $respons->headers->get('content-type'),
            'Berkas harus berformat .xlsx, bukan CSV.'
        );

        $baris = $this->bacaXlsx($respons);

        $this->assertSame([
            'No Tiket Aduan',
            'Nama',
            'No Telepon',
            'Kategori Aduan',
            'Isi Aduan',
            'Status Aduan',
            'Tanggapan untuk Pelapor',
        ], $baris[0]);
    }

    public function test_isi_baris_sepadan_dengan_datanya(): void
    {
        $p = $this->buatPengaduan([
            'status' => 'selesai',
            'tanggapan_admin' => 'Sudah diperbaiki pekan ini.',
        ]);

        $baris = $this->bacaXlsx(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        [$tiket, $nama, $telepon, $kategori, $isi, $status, $tanggapan] = $baris[1];

        $this->assertSame($p->nomor_tiket, $tiket);
        $this->assertSame('Warga Uji', $nama);
        // Nomor harus utuh sebagai teks — angka nol di depan tidak boleh hilang.
        $this->assertSame('081234567890', (string) $telepon);
        $this->assertSame('PEK. UMUM', $kategori);
        $this->assertStringContainsString('Jalan berlubang', $isi);
        $this->assertSame('Selesai', $status);
        $this->assertStringContainsString('Sudah diperbaiki', $tanggapan);
    }







    public function test_pengaduan_tanpa_tanggapan_tetap_terbaca(): void
    {
        $this->buatPengaduan();

        $baris = $this->bacaXlsx(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $this->assertSame('Belum ditanggapi', $baris[1][6]);
    }

    public function test_alasan_penolakan_ikut_pada_kolom_tanggapan(): void
    {
        // Yang dibaca pelapor di halaman lacak mencakup alasan penolakan;
        // mengambil `tanggapan_admin` saja membuat pengaduan yang ditolak
        // tampak tidak pernah dijawab.
        $this->buatPengaduan([
            'status' => 'ditolak',
            'alasan_penolakan' => 'Bukan kewenangan desa.',
        ]);

        $baris = $this->bacaXlsx(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $this->assertStringContainsString('Bukan kewenangan desa', $baris[1][6]);
    }

    public function test_penyaring_status_membatasi_isi_rekap(): void
    {
        $this->buatPengaduan(['status' => 'baru']);
        $this->buatPengaduan(['status' => 'selesai', 'tanggapan_admin' => 'Beres.']);

        $baris = $this->bacaXlsx(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor?status=selesai')
        );

        // Satu baris judul + satu baris data.
        $this->assertCount(2, $baris);
        $this->assertSame('Selesai', $baris[1][5]);
    }

    public function test_penyaring_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->operator())
            ->get('/admin/pengaduan/ekspor?status=ngawur')
            ->assertSessionHasErrors('status');

        $this->actingAs($this->operator())
            ->get('/admin/pengaduan/ekspor?kategori=Kebersihan')
            ->assertSessionHasErrors('kategori');
    }

    public function test_pengaduan_desa_lain_tidak_ikut_terbawa(): void
    {
        $desaLain = Village::create([
            'nama' => 'Desa Lain', 'slug' => 'desa-lain', 'is_active' => true,
        ]);

        $this->buatPengaduan();
        $this->buatPengaduan(['village_id' => $desaLain->id, 'nama' => 'Warga Desa Lain']);

        $baris = $this->bacaXlsx(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $this->assertCount(2, $baris);
        $this->assertSame('Warga Uji', $baris[1][1]);
    }

    // -----------------------------------------------------------------
    // Kewenangan & jejak
    // -----------------------------------------------------------------

    public function test_tamu_dan_operator_tanpa_izin_ditolak(): void
    {
        $this->get('/admin/pengaduan/ekspor')->assertRedirect('/admin/masuk');

        $this->actingAs($this->operator('Operator PPID'))
            ->get('/admin/pengaduan/ekspor')
            ->assertForbidden();
    }

    public function test_setiap_pengunduhan_tercatat_pada_audit_log(): void
    {
        $this->buatPengaduan();

        $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor?status=baru');

        $this->assertDatabaseHas('activity_logs', ['aksi' => 'exported']);

        $log = \App\Models\ActivityLog::where('aksi', 'exported')->firstOrFail();
        $this->assertSame(1, $log->data_sesudah['jumlah_baris']);
        $this->assertSame('baru', $log->data_sesudah['penyaring_status']);
    }
}
