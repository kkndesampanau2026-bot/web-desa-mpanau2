<?php

namespace Tests\Feature\Admin;

use App\Models\Complaint;
use App\Models\Dusun;
use App\Models\LetterRequest;
use App\Models\PpidRequest;
use App\Models\Rt;
use App\Models\User;
use App\Models\Village;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Lonceng notifikasi dashboard — antrean seluruh layanan warga.
 *
 * Yang paling penting diuji di sini bukan angkanya, melainkan PENYARINGAN
 * IZINNYA. Pratinjau pengaduan membawa nama pelapor dan potongan isi aduannya;
 * bila lonceng menampilkannya kepada operator yang tidak berwenang menanggapi
 * pengaduan, data itu bocor ke setiap layar dashboard yang ia buka — bukan
 * hanya ke halaman Pengaduan yang memang ditolak untuknya (PRD 12.2).
 */
class NotifikasiAdminTest extends TestCase
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

    private function operator(string $peran): User
    {
        $user = User::create([
            'name' => "Akun {$peran}",
            'email' => 'op'.uniqid().'@uji.test',
            'password' => Hash::make('rahasia123'),
            'village_id' => $this->desa->id,
            'status_aktif' => true,
        ]);
        $user->assignRole($peran);

        return $user;
    }

    private function pengaduan(string $status = 'baru', ?int $villageId = null): Complaint
    {
        return Complaint::create([
            'village_id' => $villageId ?? $this->desa->id,
            'nomor_tiket' => Complaint::buatNomorTiket(),
            'nama' => 'Warga Pelapor',
            'no_telepon_wa' => '081234567890',
            'kategori_pengaduan' => 'PEK. UMUM',
            'isi_pengaduan' => 'Jalan berlubang di depan balai desa.',
            'status' => $status,
        ]);
    }

    private function permohonanPpid(string $status = 'diajukan'): PpidRequest
    {
        return PpidRequest::create([
            'village_id' => $this->desa->id,
            'nomor_registrasi' => 'PPID-'.uniqid(),
            'nama_pemohon' => 'Warga Pemohon',
            'no_identitas' => '7210010101010001',
            'kontak' => '081234567890',
            'alamat' => 'Dusun I',
            'informasi_diminta' => 'Salinan APBDes tahun berjalan.',
            'tujuan_penggunaan' => 'Penelitian',
            'cara_memperoleh' => 'email',
            'status' => $status,
        ]);
    }

    private function pengajuanSurat(string $status): LetterRequest
    {
        $dusun = Dusun::firstOrCreate(
            ['village_id' => $this->desa->id, 'nama' => 'Dusun I'],
        );
        $rt = Rt::firstOrCreate(
            ['village_id' => $this->desa->id, 'dusun_id' => $dusun->id, 'nomor' => '01'],
        );

        return LetterRequest::create([
            'village_id' => $this->desa->id,
            'ticket_number' => 'SP-UJI-'.strtoupper(uniqid()),
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'nik' => '7210010101010002',
            // Blind index-nya WAJIB diisi: kolomnya NOT NULL tanpa default,
            // dan yang mengisinya di alur sungguhan adalah SuratPengantarService.
            'nik_hash' => app(\App\Services\PiiCipher::class)->hash('7210010101010002'),
            'nama' => 'Warga Pemohon Surat',
            'tempat_lahir' => 'Palu',
            'tanggal_lahir' => '1990-01-01',
            'pekerjaan' => 'Petani',
            'agama' => 'Islam',
            'status_perkawinan' => 'Menikah',
            'warga_negara' => 'WNI',
            'alamat' => 'Dusun I',
            'maksud_keperluan' => 'Melamar pekerjaan.',
            'rt_id' => $rt->id,
            'dusun_id' => $dusun->id,
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function notifikasi(User $pengguna): ?array
    {
        $data = null;

        $this->actingAs($pengguna)->get('/admin')->assertOk()->assertInertia(
            function (Assert $page) use (&$data) {
                $data = $page->toArray()['props']['notifikasi'] ?? null;
            }
        );

        return $data;
    }

    // -----------------------------------------------------------------
    // Cakupan layanan
    // -----------------------------------------------------------------

    public function test_lonceng_memuat_ketiga_layanan_warga(): void
    {
        $this->pengaduan();
        $this->pengaduan();
        $this->permohonanPpid();
        $this->pengajuanSurat(LetterRequest::MENUNGGU_RT);
        $this->pengajuanSurat(LetterRequest::MENUNGGU_KADUS);
        $this->pengajuanSurat(LetterRequest::MENUNGGU_KADUS);

        $notifikasi = $this->notifikasi($this->operator('Admin Utama'));

        $jumlahPer = collect($notifikasi['grup'])->pluck('jumlah', 'kunci')->all();

        $this->assertSame(2, $jumlahPer['pengaduan']);
        $this->assertSame(1, $jumlahPer['ppid']);
        $this->assertSame(3, $jumlahPer['surat']);
        $this->assertSame(6, $notifikasi['jumlah'], 'Lencana lonceng menjumlahkan seluruh grup.');
    }

    public function test_hanya_menghitung_yang_benar_benar_menunggu(): void
    {
        // Sudah ditangani — tidak boleh ikut terhitung.
        $this->pengaduan('selesai');
        $this->permohonanPpid('selesai');
        $this->pengajuanSurat(LetterRequest::DISETUJUI);
        $this->pengajuanSurat(LetterRequest::DITOLAK);

        $notifikasi = $this->notifikasi($this->operator('Admin Utama'));

        $this->assertSame(0, $notifikasi['jumlah']);
        foreach ($notifikasi['grup'] as $grup) {
            $this->assertSame([], $grup['item'], "Grup {$grup['kunci']} kosong seharusnya tanpa pratinjau.");
        }
    }

    public function test_pengaduan_desa_lain_tidak_ikut_terhitung(): void
    {
        $desaLain = Village::create([
            'nama' => 'Desa Lain', 'slug' => 'desa-lain', 'is_active' => true,
        ]);

        $this->pengaduan('baru');
        $this->pengaduan('baru', $desaLain->id);

        $notifikasi = $this->notifikasi($this->operator('Admin Utama'));
        $jumlahPer = collect($notifikasi['grup'])->pluck('jumlah', 'kunci')->all();

        $this->assertSame(1, $jumlahPer['pengaduan']);
    }

    public function test_pratinjau_dibatasi_lima_dan_menyebut_sisanya(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->pengaduan();
        }

        $notifikasi = $this->notifikasi($this->operator('Admin Utama'));
        $grup = collect($notifikasi['grup'])->firstWhere('kunci', 'pengaduan');

        $this->assertSame(7, $grup['jumlah']);
        $this->assertCount(5, $grup['item'], 'Lonceng adalah ringkasan, bukan daftar kerja.');
    }

    public function test_pratinjau_pengaduan_menautkan_langsung_ke_aduannya(): void
    {
        $p = $this->pengaduan();

        $notifikasi = $this->notifikasi($this->operator('Admin Utama'));
        $grup = collect($notifikasi['grup'])->firstWhere('kunci', 'pengaduan');

        $this->assertSame("/admin/pengaduan?buka={$p->id}", $grup['item'][0]['ke']);
        $this->assertSame('Warga Pelapor', $grup['item'][0]['judul']);
    }

    // -----------------------------------------------------------------
    // Penyaringan izin — bagian yang paling menentukan
    // -----------------------------------------------------------------

    public function test_operator_ppid_tidak_melihat_antrean_pengaduan(): void
    {
        $this->pengaduan();
        $this->permohonanPpid();

        $notifikasi = $this->notifikasi($this->operator('Operator PPID'));
        $kunci = collect($notifikasi['grup'])->pluck('kunci')->all();

        $this->assertSame(['ppid'], $kunci);

        // Nama pelapor & isi aduan tidak boleh menempel pada respons halaman
        // mana pun yang dibuka operator ini.
        $this->assertStringNotContainsString('Warga Pelapor', json_encode($notifikasi));
    }

    public function test_operator_konten_hanya_melihat_antrean_pengaduan(): void
    {
        $this->pengaduan();
        $this->permohonanPpid();
        $this->pengajuanSurat(LetterRequest::MENUNGGU_RT);

        $notifikasi = $this->notifikasi($this->operator('Operator Konten'));
        $kunci = collect($notifikasi['grup'])->pluck('kunci')->all();

        $this->assertSame(['pengaduan'], $kunci);
        $this->assertStringNotContainsString('Warga Pemohon', json_encode($notifikasi));
    }

    public function test_lonceng_hilang_bagi_operator_tanpa_satu_pun_layanan(): void
    {
        $this->pengaduan();

        /*
         * Peran dicabut, BUKAN sekadar `syncPermissions` pada penggunanya:
         * `syncPermissions` hanya menyetel izin LANGSUNG, sedangkan izin yang
         * datang lewat peran tetap melekat. Tanpa mencabut perannya, pengguna
         * ini masih memegang `respond-complaint` dan tesnya akan menguji
         * keadaan yang tidak pernah terjadi.
         */
        $tanpaLayanan = $this->operator('Operator Konten');
        $tanpaLayanan->syncRoles([]);
        $tanpaLayanan->syncPermissions(['view-dashboard', 'manage-settings']);

        $this->assertNull($this->notifikasi($tanpaLayanan));
    }

    public function test_prop_notifikasi_tidak_ikut_pada_halaman_publik(): void
    {
        $this->pengaduan();

        // Antrean layanan adalah data kerja operator; ia tidak boleh menempel
        // pada respons halaman yang dibuka warga.
        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page->where('notifikasi', null)
        );
    }
}
