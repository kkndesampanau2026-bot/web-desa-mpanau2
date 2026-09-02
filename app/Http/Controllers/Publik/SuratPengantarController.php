<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Dusun;
use App\Models\LetterApprovalLog;
use App\Models\LetterRequest;
use App\Models\Resident;
use App\Models\Rt;
use App\Services\ActivityLogger;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use App\Services\NomorTiketSurat;
use App\Services\PdfSuratPengantar;
use App\Services\SuratPengantarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Surat Pengantar RT/Dusun — halaman publik.
 *
 * Mengikuti pola dua modul publik yang sudah ada (Pengaduan & PPID): seluruh
 * alur berjalan tanpa login, warga menerima nomor tiket, lalu memakai nomor
 * itu untuk memantau dan mengunduh hasilnya.
 *
 * Satu perbedaan penting dari keduanya: surat pengantar memuat NIK dan alamat
 * lengkap, sehingga nomor tiket saja TIDAK cukup untuk membukanya. Halaman
 * cek status menuntut nomor tiket + tanggal lahir — lihat lacak().
 */
class SuratPengantarController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly SuratPengantarService $surat,
        private readonly CaptchaVerifier $captcha,
        private readonly ActivityLogger $audit,
    ) {}

    // -----------------------------------------------------------------
    // Formulir
    // -----------------------------------------------------------------

    public function formulir(Request $request): Response
    {
        return Inertia::render('Publik/Surat/Ajukan', [
            // RT & Dusun DARI BASIS DATA, tidak pernah ditulis di kode —
            // jumlah dan penomorannya berubah mengikuti keputusan desa.
            //
            // RT yang belum terpetakan ke dusun sengaja TIDAK ditawarkan:
            // dusun menentukan siapa penanda tangan kedua, sehingga pengajuan
            // dari RT semacam itu tidak punya kelanjutan. Lebih baik pilihannya
            // tidak muncul daripada warga mengisi formulir panjang lalu
            // ditolak di akhir.
            'daftar_rt' => Rt::with('dusun:id,nama')
                ->where('village_id', $this->village->id())
                ->whereNotNull('dusun_id')
                ->urut()
                ->get()
                ->map(fn (Rt $rt) => [
                    'id' => $rt->id,
                    'nomor' => $rt->nomor,
                    'dusun' => $rt->dusun?->nama,
                ]),

            // Dikirim hanya sebagai keterangan; dusun yang dipakai tetap
            // disimpulkan dari RT di sisi server.
            'daftar_dusun' => Dusun::where('village_id', $this->village->id())
                ->orderBy('urutan_tampil')
                ->get(['id', 'nama']),

            'pilihan' => [
                'agama' => LetterRequest::AGAMA,
                'status_perkawinan' => LetterRequest::STATUS_PERKAWINAN,
                'warga_negara' => LetterRequest::WARGA_NEGARA,
                // Daftar pekerjaan disusun dari data penduduk yang sudah ada,
                // bukan daftar baku yang ditulis di kode: yang muncul adalah
                // pekerjaan yang benar-benar ada di desa ini. Tetap dapat
                // diisi bebas bila tidak ada yang cocok.
                'pekerjaan' => $this->saranPekerjaan(),
            ],

            // Tanda terima dititipkan lewat flash session, sehingga menyegarkan
            // halaman tidak mengirim ulang pengajuan yang sama.
            'tiket' => $request->session()->get('tiket_surat'),

            'captcha_site_key' => config('captcha.site_key'),
        ]);
    }

    public function ajukan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],

            // Persis 16 digit, hanya angka. `digits:16` sekaligus menolak
            // huruf dan tanda pisah, sehingga blind index yang dihitung
            // darinya selalu berasal dari bentuk yang sama.
            'nik' => ['required', 'digits:16'],

            'tempat_lahir' => ['required', 'string', 'max:120'],
            // Tidak boleh masa depan; batas bawah menahan salah ketik tahun
            // yang membuat surat menyebut usia 200 tahun.
            'tanggal_lahir' => ['required', 'date', 'before:today', 'after:1900-01-01'],

            'pekerjaan' => ['required', 'string', 'max:120'],
            'agama' => ['required', Rule::in(LetterRequest::AGAMA)],
            'status_perkawinan' => ['required', Rule::in(LetterRequest::STATUS_PERKAWINAN)],
            'warga_negara' => ['required', Rule::in(LetterRequest::WARGA_NEGARA)],
            'alamat' => ['required', 'string', 'max:500'],
            'maksud_keperluan' => ['required', 'string', 'min:5', 'max:1000'],

            // `exists` dibatasi pada desa ini: tanpa itu, mengirim rt_id milik
            // desa lain akan lolos validasi begitu sistem menjadi multi-tenant.
            // `whereNotNull('dusun_id')` mengulang penyaringan dropdown di sisi
            // server. Bukan pengulangan yang mubazir: rt_id datang dari kiriman
            // klien dan dapat diubah, sementara pengajuan tanpa dusun akan
            // gagal pada constraint NOT NULL `letter_requests.dusun_id` dan
            // muncul di hadapan warga sebagai galat 500.
            'rt_id' => [
                'required',
                Rule::exists('rts', 'id')
                    ->where('village_id', $this->village->id())
                    ->whereNotNull('dusun_id'),
            ],

            ...$this->captcha->aturanValidasi(),
        ], [
            'nama.required' => 'Nama wajib diisi.',
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus tepat 16 digit angka.',
            'tempat_lahir.required' => 'Tempat lahir wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before' => 'Tanggal lahir tidak boleh di masa depan.',
            'pekerjaan.required' => 'Pekerjaan wajib diisi.',
            'agama.required' => 'Pilih agama.',
            'status_perkawinan.required' => 'Pilih status perkawinan.',
            'warga_negara.required' => 'Pilih kewarganegaraan.',
            'alamat.required' => 'Alamat wajib diisi.',
            'maksud_keperluan.required' => 'Maksud dan keperluan wajib diisi.',
            'maksud_keperluan.min' => 'Uraikan keperluan Anda sedikit lebih jelas.',
            'rt_id.required' => 'Pilih RT tempat Anda tinggal.',
            'rt_id.exists' => 'RT yang dipilih tidak dikenali atau belum terhubung ke dusun. '
                .'Silakan hubungi operator desa.',
        ]);

        if (! $this->captcha->verifikasi($request)) {
            return back()->withErrors([
                'nama' => 'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.',
            ])->withInput();
        }

        $permohonan = $this->surat->ajukan(
            $data,
            hash_hmac('sha256', (string) $request->ip(), config('app.key'))
        );

        return redirect()->route('surat.pengantar')->with('tiket_surat', [
            'ticket_number' => $permohonan->ticket_number,
            'nama' => $permohonan->nama,
            'status' => $permohonan->status,
            'tanggal_pengajuan' => $permohonan->created_at->toIso8601String(),
        ]);
    }

    // -----------------------------------------------------------------
    // Cek status
    // -----------------------------------------------------------------

    /**
     * Pelacakan status berdasarkan nomor tiket + tanggal lahir.
     *
     * Nomor tiket sendiri sudah acak dan panjang, namun ia tetap benda yang
     * beredar: tersalin ke WhatsApp, terlihat di layar, terkirim ke orang
     * yang salah. Karena isi yang dibukanya adalah NIK, alamat, dan keperluan
     * pemohon, dibutuhkan satu hal yang TIDAK ikut beredar bersama tiket.
     *
     * Tanggal lahir dipilih, bukan potongan NIK: warga menghafalnya tanpa
     * perlu membuka KTP, sementara nilainya tidak pernah ditampilkan halaman
     * mana pun sehingga tak dapat disimpulkan dari tiket itu sendiri.
     */
    public function lacak(Request $request): Response
    {
        $request->validate([
            'tiket' => ['nullable', 'string', 'max:32'],
            'tanggal_lahir' => ['nullable', 'date'],
        ]);

        $tiketMentah = $request->string('tiket')->toString();
        $tanggalLahir = $request->string('tanggal_lahir')->toString();

        $permohonan = null;
        $gagal = false;

        if ($tiketMentah !== '' && $tanggalLahir !== '') {
            $permohonan = LetterRequest::with(['rt', 'dusun', 'logs'])
                ->where('village_id', $this->village->id())
                ->where('ticket_number', NomorTiketSurat::normalkan($tiketMentah))
                ->whereDate('tanggal_lahir', $tanggalLahir)
                ->first();

            $gagal = $permohonan === null;
        }

        return Inertia::render('Publik/Surat/Lacak', [
            'tiket' => $tiketMentah ?: null,
            'tanggal_lahir' => $tanggalLahir ?: null,
            'permohonan' => $permohonan ? $this->untukWarga($permohonan) : null,
            // Pesan penolakan sengaja SATU dan netral untuk kedua sebab
            // (tiket salah / tanggal lahir salah). Membedakannya akan
            // mengubah halaman ini menjadi alat untuk memastikan sebuah
            // nomor tiket valid, lalu menebak tanggal lahirnya terpisah.
            'tidak_ditemukan' => $gagal,
        ]);
    }

    /**
     * Mengunduh PDF surat.
     *
     * Tiket saja tidak cukup, sama seperti halaman cek status: tanggal lahir
     * ikut diminta. Berkas juga tidak pernah disajikan lewat URL storage —
     * ia hidup di disk privat dan hanya keluar lewat method ini.
     */
    public function unduh(Request $request, string $tiket): StreamedResponse
    {
        $request->validate(['tanggal_lahir' => ['required', 'date']]);

        $permohonan = LetterRequest::where('village_id', $this->village->id())
            ->where('ticket_number', NomorTiketSurat::normalkan($tiket))
            ->whereDate('tanggal_lahir', $request->string('tanggal_lahir')->toString())
            ->first();

        abort_if($permohonan === null, 404, 'Surat tidak ditemukan.');
        abort_if(blank($permohonan->pdf_path), 404, 'Berkas surat belum tersedia.');

        // Draft tetap boleh diunduh pemohonnya sendiri (ia bercap DRAFT dan
        // berguna untuk memeriksa ejaan sebelum disetujui), tetapi pengajuan
        // yang sudah ditolak tidak menyisakan berkas apa pun.
        abort_if($permohonan->status === LetterRequest::DITOLAK, 404, 'Pengajuan ini ditolak.');

        abort_unless(
            Storage::disk(PdfSuratPengantar::DISK)->exists($permohonan->pdf_path),
            404,
            'Berkas surat tidak ditemukan.'
        );

        LetterApprovalLog::create([
            'letter_request_id' => $permohonan->id,
            'role' => 'WARGA',
            'action' => LetterApprovalLog::PDF_DIUNDUH,
        ]);

        $this->audit->log(
            'downloaded',
            $permohonan,
            "Surat {$permohonan->ticket_number} diunduh pemohon"
        );

        $namaBerkas = 'Surat-Pengantar-'.$permohonan->ticket_number.'.pdf';

        return Storage::disk(PdfSuratPengantar::DISK)->download(
            $permohonan->pdf_path,
            $namaBerkas,
            ['Content-Type' => 'application/pdf']
        );
    }

    // -----------------------------------------------------------------
    // Pendukung
    // -----------------------------------------------------------------

    /**
     * Bentuk pengajuan yang boleh dilihat warga.
     *
     * Disusun eksplisit, bukan dengan mengirim model apa adanya: satu kolom
     * baru yang sensitif tidak boleh diam-diam ikut terbawa hanya karena
     * ditambahkan ke tabel.
     *
     * @return array<string, mixed>
     */
    private function untukWarga(LetterRequest $p): array
    {
        return [
            'ticket_number' => $p->ticket_number,
            'nama' => $p->nama,
            // NIK TERSAMAR: 727103********13. Utuhnya hanya ada di PDF.
            'nik_tersamar' => $p->nikTersamar(),
            'rt' => $p->rt?->nomor,
            'dusun' => $p->dusun?->nama,
            'jenis_surat' => 'Surat Pengantar RT/Dusun',
            'maksud_keperluan' => $p->maksud_keperluan,
            'status' => $p->status,
            'nomor_surat' => $p->nomor_surat,
            'tanggal_pengajuan' => $p->created_at->toIso8601String(),
            'disetujui_rt_pada' => $p->approved_rt_at?->toIso8601String(),
            'disetujui_kadus_pada' => $p->approved_kadus_at?->toIso8601String(),
            'ditolak_pada' => $p->rejected_at?->toIso8601String(),
            'ditolak_oleh' => $p->rejected_by,
            'alasan_penolakan' => $p->rejection_reason,
            'pdf_tersedia' => filled($p->pdf_path),

            // Lini masa. Nama pejabat sengaja TIDAK disertakan: warga cukup
            // tahu tahapannya sudah lewat, dan menampilkan nama membuat
            // halaman ini menjadi daftar kontak pejabat yang dapat dipanen.
            'riwayat' => $p->logs
                ->whereIn('action', [
                    LetterApprovalLog::DIAJUKAN,
                    LetterApprovalLog::DISETUJUI_RT,
                    LetterApprovalLog::DITOLAK_RT,
                    LetterApprovalLog::DISETUJUI_KADUS,
                    LetterApprovalLog::DITOLAK_KADUS,
                    LetterApprovalLog::SURAT_TERBIT,
                ])
                ->sortBy('id')
                ->values()
                ->map(fn (LetterApprovalLog $l) => [
                    'action' => $l->action,
                    'waktu' => $l->created_at?->toIso8601String(),
                ]),
        ];
    }

    /**
     * Saran pekerjaan dari data penduduk desa.
     *
     * @return list<string>
     */
    private function saranPekerjaan(): array
    {
        return Resident::where('village_id', $this->village->id())
            ->whereNotNull('pekerjaan')
            ->distinct()
            ->orderBy('pekerjaan')
            ->limit(80)
            ->pluck('pekerjaan')
            ->filter()
            ->values()
            ->all();
    }
}
