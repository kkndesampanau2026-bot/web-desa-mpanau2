<?php

namespace Tests\Feature\Fase6;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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
        return $this->bacaLembar($respons)->toArray();
    }

    /** Lembar kerja utuh — dipakai saat gambar tertanam ikut diperiksa. */
    private function bacaLembar(\Illuminate\Testing\TestResponse $respons): Worksheet
    {
        $sementara = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($sementara, $respons->streamedContent());

        $lembar = IOFactory::load($sementara)->getActiveSheet();
        @unlink($sementara);

        return $lembar;
    }

    /** Menaruh gambar sungguhan pada disk privat, sepola unggahan warga. */
    private function lampirkanGambar(Complaint $pengaduan, string $nama = 'foto-jalan.jpg'): ComplaintAttachment
    {
        $gd = imagecreatetruecolor(800, 600);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 200, 120, 60));

        ob_start();
        imagejpeg($gd);
        $isi = (string) ob_get_clean();
        imagedestroy($gd);

        $path = 'lampiran-pengaduan/'.uniqid().'.jpg';
        Storage::disk('local')->put($path, $isi);

        return ComplaintAttachment::create([
            'complaint_id' => $pengaduan->id,
            'path' => $path,
            'nama_asli' => $nama,
            'mime_type' => 'image/jpeg',
            'ukuran_byte' => strlen($isi),
        ]);
    }

    private function lampirkanPdf(Complaint $pengaduan, string $nama = 'surat.pdf'): ComplaintAttachment
    {
        $path = 'lampiran-pengaduan/'.uniqid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 palsu');

        return ComplaintAttachment::create([
            'complaint_id' => $pengaduan->id,
            'path' => $path,
            'nama_asli' => $nama,
            'mime_type' => 'application/pdf',
            'ukuran_byte' => 14,
        ]);
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
        $this->get('/pengaduan')->assertOk()->assertInertia(
            fn ($page) => $page->where('kategori', Complaint::KATEGORI)
        );

        $this->post('/pengaduan', [
            'nama' => 'Warga Uji',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'TRANTIBUM',
            'isi_pengaduan' => 'Lampu jalan mati sejak pekan lalu di RT 02.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('complaints', ['kategori_pengaduan' => 'TRANTIBUM']);
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

    public function test_rekap_memuat_delapan_kolom_yang_diminta(): void
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
            'Lampiran',
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

        [$tiket, $nama, $telepon, $kategori, $isi, , $status, $tanggapan] = $baris[1];

        $this->assertSame($p->nomor_tiket, $tiket);
        $this->assertSame('Warga Uji', $nama);
        // Nomor harus utuh sebagai teks — angka nol di depan tidak boleh hilang.
        $this->assertSame('081234567890', (string) $telepon);
        $this->assertSame('PEK. UMUM', $kategori);
        $this->assertStringContainsString('Jalan berlubang', $isi);
        $this->assertSame('Selesai', $status);
        $this->assertStringContainsString('Sudah diperbaiki', $tanggapan);
    }

    public function test_lampiran_gambar_ditanam_sebagai_gambar_di_dalam_sel(): void
    {
        Storage::fake('local');

        $p = $this->buatPengaduan();
        $this->lampirkanGambar($p);

        $lembar = $this->bacaLembar(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $gambar = $lembar->getDrawingCollection();

        $this->assertCount(1, $gambar, 'Gambar seharusnya tertanam di dalam berkas, bukan hanya namanya.');
        $this->assertSame('F2', $gambar[0]->getCoordinates());

        // Dikecilkan lebih dulu: menanam berkas 5 MB apa adanya membuat rekap
        // tidak dapat dikirim lewat surel.
        $this->assertLessThanOrEqual(120, $gambar[0]->getWidth());
        $this->assertLessThanOrEqual(90, $gambar[0]->getHeight());

        // Selnya sendiri dibiarkan kosong — gambarnya yang berbicara.
        $this->assertSame('', (string) $lembar->getCell('F2')->getValue());
    }

    public function test_tiga_gambar_ditanam_bersebelahan_pada_baris_yang_sama(): void
    {
        Storage::fake('local');

        $p = $this->buatPengaduan();
        $this->lampirkanGambar($p, 'satu.jpg');
        $this->lampirkanGambar($p, 'dua.jpg');
        $this->lampirkanGambar($p, 'tiga.jpg');

        $lembar = $this->bacaLembar(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $gambar = $lembar->getDrawingCollection();
        $this->assertCount(3, $gambar);

        $geser = [];
        foreach ($gambar as $g) {
            $this->assertSame('F2', $g->getCoordinates());
            $geser[] = $g->getOffsetX();
        }

        // Ketiganya harus berjajar, bukan bertumpuk di titik yang sama.
        $this->assertSame($geser, array_unique($geser));
    }

    public function test_lampiran_pdf_menjadi_tautan_yang_dapat_dibuka(): void
    {
        Storage::fake('local');

        $p = $this->buatPengaduan();
        $lampiran = $this->lampirkanPdf($p, 'bukti-sengketa.pdf');

        $lembar = $this->bacaLembar(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        // PDF tidak dapat ditanam sebagai isi — yang tertulis namanya, dan
        // selnya menjadi tautan menuju endpoint admin yang membukanya.
        $this->assertCount(0, $lembar->getDrawingCollection());
        $this->assertSame('bukti-sengketa.pdf', $lembar->getCell('F2')->getValue());

        $this->assertStringContainsString(
            "/admin/pengaduan/{$p->id}/lampiran/{$lampiran->id}",
            $lembar->getCell('F2')->getHyperlink()->getUrl()
        );
    }

    public function test_tautan_lampiran_pada_rekap_benar_benar_membuka_berkasnya(): void
    {
        Storage::fake('local');

        $p = $this->buatPengaduan();
        $lampiran = $this->lampirkanPdf($p);

        $respons = $this->actingAs($this->operator())
            ->get("/admin/pengaduan/{$p->id}/lampiran/{$lampiran->id}")
            ->assertOk();

        $this->assertSame('application/pdf', $respons->headers->get('content-type'));

        // `inline`, bukan `attachment`: PDF harus terbuka di tab peramban,
        // itulah gunanya tautan pada rekap.
        $this->assertStringStartsWith('inline', $respons->headers->get('content-disposition'));
        $this->assertSame('nosniff', $respons->headers->get('x-content-type-options'));

        $this->assertDatabaseHas('activity_logs', ['aksi' => 'viewed']);
    }

    public function test_tautan_lampiran_menolak_tamu_dan_lampiran_milik_pengaduan_lain(): void
    {
        Storage::fake('local');

        $p = $this->buatPengaduan();
        $lain = $this->buatPengaduan();
        $lampiran = $this->lampirkanPdf($lain);

        $this->get("/admin/pengaduan/{$p->id}/lampiran/{$lampiran->id}")
            ->assertRedirect('/admin/masuk');

        // Lampiran milik pengaduan lain tidak boleh terbuka lewat id pengaduan
        // mana pun yang kebetulan dipegang penyerang.
        $this->actingAs($this->operator())
            ->get("/admin/pengaduan/{$p->id}/lampiran/{$lampiran->id}")
            ->assertNotFound();

        $this->actingAs($this->operator('Operator PPID'))
            ->get("/admin/pengaduan/{$lain->id}/lampiran/{$lampiran->id}")
            ->assertForbidden();
    }

    public function test_gambar_yang_berkasnya_hilang_tidak_menggagalkan_rekap(): void
    {
        Storage::fake('local');

        $p = $this->buatPengaduan();
        ComplaintAttachment::create([
            'complaint_id' => $p->id,
            'path' => 'lampiran-pengaduan/tidak-ada.jpg',
            'nama_asli' => 'hilang.jpg',
            'mime_type' => 'image/jpeg',
            'ukuran_byte' => 1024,
        ]);

        $lembar = $this->bacaLembar(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $this->assertCount(0, $lembar->getDrawingCollection());
        // Baris lainnya tetap utuh — satu berkas hilang tidak boleh
        // menjatuhkan seluruh rekap.
        $this->assertSame($p->nomor_tiket, $lembar->getCell('A2')->getValue());
    }

    public function test_pengaduan_tanpa_lampiran_dan_tanpa_tanggapan_tetap_terbaca(): void
    {
        $this->buatPengaduan();

        $baris = $this->bacaXlsx(
            $this->actingAs($this->operator())->get('/admin/pengaduan/ekspor')
        );

        $this->assertSame('Tidak ada', $baris[1][5]);
        $this->assertSame('Belum ditanggapi', $baris[1][7]);
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

        $this->assertStringContainsString('Bukan kewenangan desa', $baris[1][7]);
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
        $this->assertSame('Selesai', $baris[1][6]);
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
