<?php

namespace Tests\Feature\Surat;

use App\Models\Dusun;
use App\Models\LetterApprovalLog;
use App\Models\LetterOfficial;
use App\Models\LetterRequest;
use App\Models\Rt;
use App\Models\Village;
use App\Services\PdfSuratPengantar;
use App\Services\TelegramBot;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Surat Pengantar RT/Dusun — alur warga, persetujuan Telegram, dan otorisasi.
 *
 * Perhatian khusus pada webhook Telegram: ia satu-satunya endpoint di seluruh
 * sistem yang mengubah status dokumen resmi tanpa sesi login. Karena itu
 * separuh berkas ini menguji apa yang TIDAK boleh terjadi di sana.
 */
class SuratPengantarTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;

    private Dusun $dusun;

    private Rt $rt;

    private LetterOfficial $ketuaRt;

    private LetterOfficial $kadus;

    /**
     * Respons palsu Bot API yang sedang berlaku.
     *
     * Disimpan sebagai properti, bukan didaftarkan ulang lewat Http::fake(),
     * karena `Http::fake()` MENGGABUNG stub baru dengan yang sudah ada dan
     * memakai stub pertama yang cocok — memanggilnya lagi di dalam sebuah
     * test tidak menimpa stub dari setUp(). Membuang instance facade pun tak
     * menolong: factory-nya singleton di container.
     *
     * Stub setUp() karena itu berupa closure yang membaca properti ini pada
     * setiap permintaan, sehingga test cukup menggantinya untuk mengubah
     * perilaku Telegram.
     */
    private mixed $responsTelegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        Storage::fake(PdfSuratPengantar::DISK);

        // Bot dianggap aktif agar jalur pengiriman ikut terlewati; seluruh
        // panggilan keluarnya dipalsukan sehingga tidak ada lalu lintas nyata.
        config([
            'telegram.token' => 'token-uji',
            'telegram.webhook_secret' => 'rahasia-uji',
        ]);

        $this->responsTelegram = Http::response([
            'ok' => true,
            'result' => ['message_id' => 555],
        ]);

        Http::fake(['api.telegram.org/*' => fn () => $this->responsTelegram]);

        RateLimiter::clear('cek-surat');

        $this->village = Village::create([
            'nama' => 'Desa Uji', 'slug' => 'desa-uji', 'is_active' => true,
        ]);

        $this->dusun = Dusun::create([
            'village_id' => $this->village->id, 'nama' => 'Dusun 4', 'urutan_tampil' => 4,
        ]);

        $this->rt = Rt::create([
            'village_id' => $this->village->id,
            'dusun_id' => $this->dusun->id,
            'nomor' => '20',
            'urutan_tampil' => 20,
        ]);

        $this->ketuaRt = LetterOfficial::create([
            'village_id' => $this->village->id,
            'role' => LetterOfficial::ROLE_KETUA_RT,
            'rt_id' => $this->rt->id,
            'nama' => 'Ahmad Yani',
            'telegram_chat_id' => '111111',
            'is_active' => true,
        ]);

        $this->kadus = LetterOfficial::create([
            'village_id' => $this->village->id,
            'role' => LetterOfficial::ROLE_KEPALA_DUSUN,
            'dusun_id' => $this->dusun->id,
            'nama' => 'Sitti Aminah',
            'telegram_chat_id' => '222222',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    /** @param  array<string, mixed>  $ganti */
    private function ajukan(array $ganti = [])
    {
        return $this->post('/layanan-mandiri/surat-pengantar', array_merge([
            'nama' => 'Budi Utomo',
            'nik' => '7210312038900013',
            'tempat_lahir' => 'Lambunu',
            'tanggal_lahir' => '1989-03-12',
            'pekerjaan' => 'Buruh Harian Lepas',
            'agama' => 'Islam',
            'status_perkawinan' => 'Menikah',
            'warga_negara' => 'WNI',
            'alamat' => 'Jl. Kerandjalembah',
            'maksud_keperluan' => 'Untuk membuat surat keterangan usaha.',
            'rt_id' => $this->rt->id,
        ], $ganti));
    }

    private function permohonanTerbaru(): LetterRequest
    {
        return LetterRequest::latest('id')->firstOrFail();
    }

    /** Mensimulasikan penekanan tombol inline di Telegram. */
    private function tekanTombol(string $chatId, string $data, int $messageId = 900)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'rahasia-uji')
            ->postJson('/telegram/webhook', [
                'callback_query' => [
                    'id' => 'cb-'.uniqid(),
                    'data' => $data,
                    'message' => [
                        'message_id' => $messageId,
                        'chat' => ['id' => $chatId],
                    ],
                ],
            ]);
    }

    /** Mensimulasikan balasan teks (alasan penolakan) di Telegram. */
    private function balas(string $chatId, int $balasKe, string $teks)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'rahasia-uji')
            ->postJson('/telegram/webhook', [
                'message' => [
                    'chat' => ['id' => $chatId],
                    'text' => $teks,
                    'reply_to_message' => ['message_id' => $balasKe],
                ],
            ]);
    }

    /**
     * Menolak lewat dua langkah: tekan TOLAK, lalu balas alasannya.
     *
     * `message_id` balasan adalah 555 — nilai yang dikembalikan Http::fake
     * untuk pesan permintaan alasan, dan itulah kunci yang dipakai controller
     * untuk menautkan alasan ke pengajuannya.
     */
    private function tolakLewatTelegram(string $chatId, string $tahap, string $uuid, string $alasan)
    {
        $this->tekanTombol($chatId, "reject_{$tahap}:{$uuid}");

        return $this->balas($chatId, 555, $alasan);
    }

    // ==================================================================
    // Skenario 1 — alur berhasil penuh
    // ==================================================================

    public function test_warga_dapat_mengajukan_surat_tanpa_login(): void
    {
        $this->ajukan()->assertRedirect('/layanan-mandiri/surat-pengantar');

        $p = $this->permohonanTerbaru();

        // Nomor tiket acak, bukan berurutan, dan tanpa bagian tanggal yang
        // dapat ditebak.
        $this->assertMatchesRegularExpression('/^SP-MPN-[0-9A-HJKMNP-TV-Z]{8}$/', $p->ticket_number);

        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->status);
        $this->assertSame('Budi Utomo', $p->nama);

        // Dusun DISIMPULKAN dari RT, tidak dikirim warga.
        $this->assertSame($this->dusun->id, $p->dusun_id);

        // NIK tersimpan terenkripsi; kolom mentahnya tidak memuat nilai asli.
        $this->assertSame('7210312038900013', $p->nik);
        $mentah = \DB::table('letter_requests')->where('id', $p->id)->value('nik');
        $this->assertStringNotContainsString('7210312038900013', (string) $mentah);
        $this->assertNotEmpty($p->nik_hash);

        // Draft PDF dibuat segera.
        $this->assertNotNull($p->pdf_path);
        Storage::disk(PdfSuratPengantar::DISK)->assertExists($p->pdf_path);
        $this->assertStringContainsString('draft-', $p->pdf_path);

        $this->assertDatabaseHas('letter_approval_logs', [
            'letter_request_id' => $p->id,
            'action' => LetterApprovalLog::DIAJUKAN,
        ]);
    }

    public function test_alur_lengkap_rt_lalu_kadus_menerbitkan_pdf_final(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();
        $draft = $p->pdf_path;

        // --- Ketua RT menyetujui ---
        $this->tekanTombol('111111', "approve_rt:{$p->uuid}")->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::MENUNGGU_KADUS, $p->status);
        $this->assertSame($this->ketuaRt->id, $p->approved_rt_by);
        $this->assertNotNull($p->approved_rt_at);

        // --- Kepala Dusun menyetujui ---
        $this->tekanTombol('222222', "approve_kadus:{$p->uuid}")->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::DISETUJUI, $p->status);
        $this->assertSame($this->kadus->id, $p->approved_kadus_by);

        // Nomor surat mengikuti blangko desa: urut per RT.
        $this->assertSame(1, $p->nomor_urut);
        $this->assertSame('1/RT.20/DUSUN 4', $p->nomor_surat);

        // PDF final menggantikan draft, dan draft lamanya dibersihkan.
        $this->assertStringContainsString('final-', $p->pdf_path);
        Storage::disk(PdfSuratPengantar::DISK)->assertExists($p->pdf_path);
        Storage::disk(PdfSuratPengantar::DISK)->assertMissing($draft);

        foreach ([
            LetterApprovalLog::DISETUJUI_RT,
            LetterApprovalLog::DISETUJUI_KADUS,
            LetterApprovalLog::SURAT_TERBIT,
        ] as $aksi) {
            $this->assertDatabaseHas('letter_approval_logs', [
                'letter_request_id' => $p->id,
                'action' => $aksi,
            ]);
        }
    }

    public function test_warga_dapat_mengunduh_surat_yang_sudah_disetujui(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");
        $this->tekanTombol('222222', "approve_kadus:{$p->uuid}");

        $this->get("/layanan-mandiri/surat-pengantar/{$p->ticket_number}/unduh?tanggal_lahir=1989-03-12")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('letter_approval_logs', [
            'letter_request_id' => $p->id,
            'action' => LetterApprovalLog::PDF_DIUNDUH,
        ]);
    }

    // ==================================================================
    // Skenario 2 & 3 — penolakan
    // ==================================================================

    public function test_penolakan_oleh_rt_menyimpan_alasan_dan_menghentikan_alur(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tolakLewatTelegram('111111', 'rt', $p->uuid, 'Data alamat tidak sesuai dengan data RT.')
            ->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::DITOLAK, $p->status);
        $this->assertSame('Data alamat tidak sesuai dengan data RT.', $p->rejection_reason);
        $this->assertSame('RT', $p->rejected_by);
        $this->assertSame($this->ketuaRt->id, $p->rejected_by_official_id);
        $this->assertNotNull($p->rejected_at);

        // Berkas draft tidak boleh tersisa untuk diunduh.
        $this->assertNull($p->pdf_path);
    }

    public function test_penolakan_oleh_kadus_setelah_rt_menyetujui(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");
        $this->tolakLewatTelegram('222222', 'kadus', $p->uuid, 'Keperluan tidak jelas.')->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::DITOLAK, $p->status);
        $this->assertSame('KADUS', $p->rejected_by);
        $this->assertSame('Keperluan tidak jelas.', $p->rejection_reason);

        // Persetujuan RT tetap tercatat: riwayatnya tidak boleh terhapus
        // hanya karena tahap berikutnya menolak.
        $this->assertNotNull($p->approved_rt_at);
    }

    public function test_warga_melihat_alasan_penolakan_pada_halaman_cek_status(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tolakLewatTelegram('111111', 'rt', $p->uuid, 'Data yang diberikan tidak sesuai.');

        $this->get('/layanan-mandiri/surat-pengantar/lacak?tiket='.$p->ticket_number
            .'&tanggal_lahir=1989-03-12')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Publik/Surat/Lacak')
                ->where('permohonan.status', LetterRequest::DITOLAK)
                ->where('permohonan.alasan_penolakan', 'Data yang diberikan tidak sesuai.')
                ->where('permohonan.ditolak_oleh', 'RT')
            );
    }

    // ==================================================================
    // Skenario 4 — Telegram tak berwenang
    // ==================================================================

    public function test_chat_id_tidak_terdaftar_tidak_dapat_menyetujui(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('999999', "approve_rt:{$p->uuid}")->assertOk();

        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->fresh()->status);
    }

    public function test_ketua_rt_lain_tidak_dapat_menyetujui_surat_di_luar_wilayahnya(): void
    {
        $rtLain = Rt::create([
            'village_id' => $this->village->id,
            'dusun_id' => $this->dusun->id,
            'nomor' => '01',
        ]);

        LetterOfficial::create([
            'village_id' => $this->village->id,
            'role' => LetterOfficial::ROLE_KETUA_RT,
            'rt_id' => $rtLain->id,
            'nama' => 'Ketua RT 01',
            'telegram_chat_id' => '333333',
            'is_active' => true,
        ]);

        $this->ajukan();
        $p = $this->permohonanTerbaru();

        // Tombol yang sah, ditekan pejabat yang sah — tetapi untuk RT lain.
        $this->tekanTombol('333333', "approve_rt:{$p->uuid}")->assertOk();

        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->fresh()->status);
    }

    public function test_ketua_rt_tidak_dapat_menyetujui_pada_tahap_kepala_dusun(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");

        // Role-nya tidak cocok dengan tahap yang diminta.
        $this->tekanTombol('111111', "approve_kadus:{$p->uuid}")->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::MENUNGGU_KADUS, $p->status);
        $this->assertNull($p->approved_kadus_by);
    }

    public function test_pejabat_nonaktif_tidak_dapat_menyetujui(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->ketuaRt->update(['is_active' => false]);

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}")->assertOk();

        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->fresh()->status);
    }

    public function test_webhook_menolak_permintaan_tanpa_header_rahasia(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->postJson('/telegram/webhook', [
            'callback_query' => [
                'id' => 'cb-palsu',
                'data' => "approve_rt:{$p->uuid}",
                'message' => ['message_id' => 1, 'chat' => ['id' => '111111']],
            ],
        ])->assertForbidden();

        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->fresh()->status);
    }

    public function test_callback_data_yang_tidak_berbentuk_uuid_diabaikan(): void
    {
        $this->ajukan();

        $this->tekanTombol('111111', 'approve_rt:1 OR 1=1')->assertOk();
        $this->tekanTombol('111111', 'sesuatu_yang_lain')->assertOk();

        $this->assertSame(LetterRequest::MENUNGGU_RT, $this->permohonanTerbaru()->status);
    }

    // ==================================================================
    // Skenario 5 — persetujuan ganda
    // ==================================================================

    public function test_rt_tidak_dapat_menyetujui_dua_kali(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");
        $waktuPertama = $p->fresh()->approved_rt_at;

        // Tombol lama ditekan lagi.
        $this->tekanTombol('111111', "approve_rt:{$p->uuid}")->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::MENUNGGU_KADUS, $p->status);
        $this->assertEquals($waktuPertama, $p->approved_rt_at);

        $this->assertSame(1, LetterApprovalLog::where('letter_request_id', $p->id)
            ->where('action', LetterApprovalLog::DISETUJUI_RT)
            ->count());
    }

    public function test_kadus_tidak_dapat_menyetujui_dua_kali(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");
        $this->tekanTombol('222222', "approve_kadus:{$p->uuid}");

        $nomorPertama = $p->fresh()->nomor_surat;

        $this->tekanTombol('222222', "approve_kadus:{$p->uuid}")->assertOk();

        $p->refresh();
        // Nomor surat tidak boleh bergeser: penerbitan kedua akan memakan
        // satu nomor agenda RT tanpa surat yang menyertainya.
        $this->assertSame($nomorPertama, $p->nomor_surat);
        $this->assertSame(1, LetterApprovalLog::where('letter_request_id', $p->id)
            ->where('action', LetterApprovalLog::DISETUJUI_KADUS)
            ->count());
    }

    public function test_tombol_lama_pada_pengajuan_yang_sudah_ditolak_tidak_berpengaruh(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tolakLewatTelegram('111111', 'rt', $p->uuid, 'Tidak lengkap.');

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}")->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::DITOLAK, $p->status);
        $this->assertNull($p->approved_rt_by);
    }

    public function test_tombol_lama_pada_surat_yang_sudah_terbit_tidak_berpengaruh(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");
        $this->tekanTombol('222222', "approve_kadus:{$p->uuid}");

        $this->tolakLewatTelegram('222222', 'kadus', $p->uuid, 'Berubah pikiran.')->assertOk();

        $p->refresh();
        $this->assertSame(LetterRequest::DISETUJUI, $p->status);
        $this->assertNull($p->rejection_reason);
    }

    // ==================================================================
    // Validasi formulir
    // ==================================================================

    public function test_nik_wajib_enam_belas_digit_angka(): void
    {
        $this->ajukan(['nik' => '123'])->assertSessionHasErrors('nik');
        $this->ajukan(['nik' => 'ABCDEFGHIJKLMNOP'])->assertSessionHasErrors('nik');

        $this->assertSame(0, LetterRequest::count());
    }

    public function test_kolom_wajib_ditegakkan(): void
    {
        $this->post('/layanan-mandiri/surat-pengantar', [])
            ->assertSessionHasErrors([
                'nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'pekerjaan',
                'agama', 'status_perkawinan', 'warga_negara', 'alamat',
                'maksud_keperluan', 'rt_id',
            ]);
    }

    public function test_rt_di_luar_daftar_ditolak(): void
    {
        $this->ajukan(['rt_id' => 99999])->assertSessionHasErrors('rt_id');
    }

    public function test_tanggal_lahir_masa_depan_ditolak(): void
    {
        $this->ajukan(['tanggal_lahir' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('tanggal_lahir');
    }

    // ==================================================================
    // Keamanan halaman cek status
    // ==================================================================

    public function test_nomor_tiket_saja_tidak_cukup_untuk_membuka_data(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        // Tanpa tanggal lahir: tidak ada data yang dikembalikan sama sekali.
        $this->get('/layanan-mandiri/surat-pengantar/lacak?tiket='.$p->ticket_number)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('permohonan', null));

        // Tanggal lahir keliru: ditolak dengan pesan yang sama seperti tiket
        // keliru, sehingga tidak dapat dipakai memastikan tiket itu valid.
        $this->get('/layanan-mandiri/surat-pengantar/lacak?tiket='.$p->ticket_number
            .'&tanggal_lahir=1990-01-01')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permohonan', null)
                ->where('tidak_ditemukan', true)
            );
    }

    public function test_halaman_cek_status_hanya_menampilkan_nik_tersamar(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->get('/layanan-mandiri/surat-pengantar/lacak?tiket='.$p->ticket_number
            .'&tanggal_lahir=1989-03-12')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('permohonan.nik_tersamar', '721031**********')
            )
            ->assertDontSee('7210312038900013');
    }

    public function test_unduh_pdf_menolak_tanggal_lahir_yang_salah(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->get("/layanan-mandiri/surat-pengantar/{$p->ticket_number}/unduh?tanggal_lahir=1990-01-01")
            ->assertNotFound();
    }

    public function test_pdf_pengajuan_yang_ditolak_tidak_dapat_diunduh(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tolakLewatTelegram('111111', 'rt', $p->uuid, 'Tidak sesuai.');

        $this->get("/layanan-mandiri/surat-pengantar/{$p->ticket_number}/unduh?tanggal_lahir=1989-03-12")
            ->assertNotFound();
    }

    public function test_nomor_tiket_dinormalkan_dari_ketikan_warga(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        // Huruf kecil, spasi, dan tanda hubung yang hilang tetap dikenali.
        $mentah = strtolower(str_replace('-', ' ', $p->ticket_number));

        $this->get('/layanan-mandiri/surat-pengantar/lacak?tiket='.urlencode($mentah)
            .'&tanggal_lahir=1989-03-12')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('permohonan.ticket_number', $p->ticket_number));
    }

    // ==================================================================
    // Kondisi tepi
    // ==================================================================

    public function test_pengajuan_tetap_berhasil_meski_ketua_rt_belum_punya_chat_id(): void
    {
        $this->ketuaRt->update(['telegram_chat_id' => null]);

        $this->ajukan()->assertRedirect();

        $p = $this->permohonanTerbaru();

        // Pengajuan berdiri utuh; hanya notifikasinya yang tercatat gagal.
        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->status);
        $this->assertDatabaseHas('letter_approval_logs', [
            'letter_request_id' => $p->id,
            'action' => LetterApprovalLog::NOTIFIKASI_GAGAL,
        ]);
    }

    public function test_pengajuan_tetap_berhasil_meski_telegram_tidak_dapat_dihubungi(): void
    {
        $this->responsTelegram = Http::response(null, 500);

        $this->ajukan()->assertRedirect();

        $p = $this->permohonanTerbaru();
        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->status);

        $alasan = LetterApprovalLog::where('letter_request_id', $p->id)
            ->where('action', LetterApprovalLog::NOTIFIKASI_GAGAL)
            ->value('reason');

        $this->assertStringContainsString('Telegram menolak', (string) $alasan);
    }

    public function test_token_bot_yang_belum_diisi_tercatat_sebagai_sebabnya_sendiri(): void
    {
        // Sebab paling sering saat pemasangan, dan yang paling mahal bila
        // tersamar sebagai kegagalan jaringan: operator akan memeriksa chat ID
        // dan koneksi berjam-jam padahal cukup mengisi satu baris .env.
        config(['telegram.token' => null]);

        $this->ajukan()->assertRedirect();

        $p = $this->permohonanTerbaru();
        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->status);

        $alasan = LetterApprovalLog::where('letter_request_id', $p->id)
            ->where('action', LetterApprovalLog::NOTIFIKASI_GAGAL)
            ->value('reason');

        $this->assertStringContainsString('TELEGRAM_BOT_TOKEN', (string) $alasan);
    }

    public function test_pengajuan_tetap_berhasil_meski_ketua_rt_belum_terdaftar(): void
    {
        $this->ketuaRt->delete();

        $this->ajukan()->assertRedirect();

        $this->assertSame(LetterRequest::MENUNGGU_RT, $this->permohonanTerbaru()->status);
    }

    public function test_surat_tetap_terbit_meski_pejabat_belum_punya_gambar_tanda_tangan(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        // Kedua pejabat memang tanpa signature_path sejak setUp.
        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");
        $this->tekanTombol('222222', "approve_kadus:{$p->uuid}");

        $p->refresh();
        $this->assertSame(LetterRequest::DISETUJUI, $p->status);
        Storage::disk(PdfSuratPengantar::DISK)->assertExists($p->pdf_path);
    }

    public function test_alasan_penolakan_kosong_tidak_mengubah_status(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "reject_rt:{$p->uuid}");
        $this->balas('111111', 555, '   ')->assertOk();

        // Menekan TOLAK saja belum menolak apa pun: statusnya baru berpindah
        // setelah alasan yang sah diterima.
        $this->assertSame(LetterRequest::MENUNGGU_RT, $p->fresh()->status);
    }

    public function test_nomor_surat_berurut_per_rt_bukan_per_desa(): void
    {
        // Yang diuji penomoran surat, bukan pembatasan laju.
        $this->withoutMiddleware(ThrottleRequests::class);

        $rtLain = Rt::create([
            'village_id' => $this->village->id,
            'dusun_id' => $this->dusun->id,
            'nomor' => '01',
        ]);

        LetterOfficial::create([
            'village_id' => $this->village->id,
            'role' => LetterOfficial::ROLE_KETUA_RT,
            'rt_id' => $rtLain->id,
            'nama' => 'Ketua RT 01',
            'telegram_chat_id' => '333333',
            'is_active' => true,
        ]);

        // Surat pertama di RT 20.
        $this->ajukan();
        $a = $this->permohonanTerbaru();
        $this->tekanTombol('111111', "approve_rt:{$a->uuid}");
        $this->tekanTombol('222222', "approve_kadus:{$a->uuid}");

        // Surat pertama di RT 01 — nomornya harus 1 lagi, bukan 2.
        $this->ajukan(['rt_id' => $rtLain->id]);
        $b = $this->permohonanTerbaru();
        $this->tekanTombol('333333', "approve_rt:{$b->uuid}");
        $this->tekanTombol('222222', "approve_kadus:{$b->uuid}");

        $this->assertSame(1, $a->fresh()->nomor_urut);
        $this->assertSame(1, $b->fresh()->nomor_urut);
        $this->assertSame('1/RT.01/DUSUN 4', $b->fresh()->nomor_surat);
    }

    public function test_nomor_tiket_selalu_unik(): void
    {
        // Pembatasan laju dilewati: yang diuji keunikan nomor tiket, bukan
        // remnya — dan rem itu punya tesnya sendiri di bawah.
        $this->withoutMiddleware(ThrottleRequests::class);

        for ($i = 0; $i < 8; $i++) {
            $this->ajukan();
        }

        $this->assertSame(8, LetterRequest::count());
        $this->assertSame(8, LetterRequest::distinct()->count('ticket_number'));
    }

    public function test_pengajuan_dibatasi_lajunya_per_alamat_ip(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->ajukan()->assertRedirect();
        }

        // Yang keempat ditahan.
        $this->ajukan()->assertSessionHasErrors('nama');
        $this->assertSame(3, LetterRequest::count());
    }

    public function test_lalu_lintas_webhook_tidak_memakan_jatah_formulir_warga(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        // Middleware `throttle:N,1` tanpa nama menyusun kuncinya dari
        // domain + IP saja, sehingga seluruh route yang memakainya berbagi
        // satu penghitung. Kedua route di sini memakai named limiter justru
        // agar hal itu tidak terjadi — inilah yang diuji.
        for ($i = 0; $i < 10; $i++) {
            $this->tekanTombol('999999', "approve_rt:{$p->uuid}")->assertOk();
        }

        $this->ajukan()->assertRedirect();
        $this->assertSame(2, LetterRequest::count());
    }

    public function test_rt_yang_belum_terhubung_ke_dusun_tidak_dapat_dipilih(): void
    {
        $rtYatim = Rt::create([
            'village_id' => $this->village->id,
            'nomor' => '99',
            'dusun_id' => null,
        ]);

        // Tidak ditawarkan pada formulir…
        $this->get('/layanan-mandiri/surat-pengantar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'daftar_rt',
                fn ($daftar) => collect($daftar)->doesntContain('nomor', '99')
            ));

        // …dan ditolak pula bila rt_id-nya tetap dikirim. Tanpa penjagaan ini
        // pengajuan menabrak constraint NOT NULL `letter_requests.dusun_id`
        // dan muncul di hadapan warga sebagai galat 500.
        $this->ajukan(['rt_id' => $rtYatim->id])->assertSessionHasErrors('rt_id');
        $this->assertSame(0, LetterRequest::count());
    }

    // ==================================================================
    // Bot API
    // ==================================================================

    public function test_setwebhook_berhasil_meski_telegram_menjawab_result_true(): void
    {
        // Bot API menjawab setWebhook/deleteWebhook/answerCallbackQuery dengan
        // `result: true`, bukan objek. Ketika panggil() masih dituntut
        // mengembalikan ?array, ketiganya melempar TypeError justru saat
        // Telegram menjawab BERHASIL — dan karena Throwable-nya ditelan,
        // kegagalannya menyamar sebagai "Telegram tidak dapat dihubungi".
        $this->responsTelegram = Http::response(['ok' => true, 'result' => true]);

        $bot = app(TelegramBot::class);

        $this->assertTrue($bot->setWebhook('https://contoh.test/telegram/webhook', 'rahasia'));
        $this->assertTrue($bot->hapusWebhook());
    }

    public function test_rahasia_webhook_dengan_karakter_terlarang_ditolak_lebih_awal(): void
    {
        // Telegram hanya menerima A-Z a-z 0-9 _ - pada secret_token. Hasil
        // `php artisan key:generate --show` memuat ":" dan "=", sehingga
        // pemasangan gagal dengan pesan yang tidak menyebut rahasianya sama
        // sekali. Diperiksa lebih dulu agar sebabnya terbaca langsung.
        config(['telegram.webhook_secret' => 'base64:aGFsbG8gZHVuaWE=']);

        $this->artisan('surat:telegram-webhook', ['--url' => 'https://contoh.test'])
            ->expectsOutputToContain('karakter yang tidak diterima Telegram')
            ->assertFailed();
    }

    public function test_rahasia_webhook_yang_sah_diterima(): void
    {
        config(['telegram.webhook_secret' => 'abcDEF123_-xyz']);
        $this->responsTelegram = Http::response(['ok' => true, 'result' => true]);

        $this->artisan('surat:telegram-webhook', ['--url' => 'https://contoh.test'])
            ->assertSuccessful();
    }

    // ==================================================================
    // Audit
    // ==================================================================

    public function test_setiap_persetujuan_tercatat_pada_audit_log_dengan_pelakunya(): void
    {
        $this->ajukan();
        $p = $this->permohonanTerbaru();

        $this->tekanTombol('111111', "approve_rt:{$p->uuid}");

        $this->assertDatabaseHas('activity_logs', [
            'aksi' => 'approved',
            'subjek_tipe' => LetterRequest::class,
            'subjek_id' => $p->id,
            'pelaku_tipe' => LetterOfficial::class,
            'pelaku_id' => $this->ketuaRt->id,
            'user_nama' => 'Ahmad Yani',
        ]);
    }

    public function test_audit_log_tidak_pernah_memuat_nik_mentah(): void
    {
        $this->ajukan();

        $this->assertDatabaseMissing('activity_logs', ['deskripsi' => '7210312038900013']);

        $semua = \DB::table('activity_logs')->get()->toJson();
        $this->assertStringNotContainsString('7210312038900013', $semua);
    }
}
