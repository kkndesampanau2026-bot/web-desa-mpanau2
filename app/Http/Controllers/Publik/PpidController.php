<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\PpidInformationItem;
use App\Models\PpidLegalBasis;
use App\Models\PpidRequest;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PPID — halaman publik (PRD 6.14).
 *
 * Struktur mengikuti kategori baku UU No. 14/2008: dasar hukum, informasi
 * berkala, serta-merta, setiap saat, dan formulir permohonan.
 */
class PpidController extends Controller
{
    private const TTL = 3600;

    public function __construct(
        private readonly CurrentVillage $village,
        private readonly CaptchaVerifier $captcha,
    ) {}

    public function beranda(): Response
    {
        return Inertia::render('Publik/Ppid/Beranda');
    }

    public function dasarHukum(): Response
    {
        $villageId = $this->village->id();

        $daftar = Cache::remember(
            "ppid:dasar-hukum:{$villageId}",
            self::TTL,
            fn () => PpidLegalBasis::where('village_id', $villageId)
                ->orderBy('urutan_tampil')
                ->orderByDesc('tahun')
                ->get()
                ->map(fn ($d) => [
                    'judul_regulasi' => $d->judul_regulasi,
                    'nomor_regulasi' => $d->nomor_regulasi,
                    'tahun' => $d->tahun,
                    'file' => $d->file_pdf ? asset('storage/'.$d->file_pdf) : null,
                ])
        );

        return Inertia::render('Publik/Ppid/DasarHukum', ['dasar_hukum' => $daftar]);
    }

    /**
     * Daftar dokumen menurut jenis: berkala, serta-merta, atau setiap saat.
     *
     * Jenis datang dari segmen URL, bukan query string, sehingga tiap kategori
     * punya alamat sendiri yang dapat dibagikan dan diindeks.
     */
    public function informasi(string $jenis): Response
    {
        abort_unless(in_array($jenis, PpidInformationItem::JENIS, true), 404);

        $villageId = $this->village->id();

        $daftar = Cache::remember(
            "ppid:informasi:{$villageId}:{$jenis}",
            self::TTL,
            fn () => PpidInformationItem::where('village_id', $villageId)
                ->where('jenis', $jenis)
                ->tampil()
                ->get()
                ->map(fn ($i) => [
                    'judul' => $i->judul,
                    'deskripsi' => $i->deskripsi,
                    'kategori' => $i->kategori,
                    'periode' => $i->periode,
                    'tingkat_urgensi' => $i->tingkat_urgensi,
                    'tanggal_publish' => $i->tanggal_publish?->toDateString(),
                    'file' => $i->file ? asset('storage/'.$i->file) : null,
                ])
        );

        return Inertia::render('Publik/Ppid/Informasi', [
            'jenis' => $jenis,
            'informasi' => $daftar,
        ]);
    }

    public function formulirPermohonan(Request $request): Response
    {
        return Inertia::render('Publik/Ppid/Permohonan', [
            // Bukti pengajuan dititipkan lewat flash session sesaat setelah
            // formulir dikirim, sehingga menyegarkan halaman tidak mengirim
            // ulang permohonan yang sama.
            'bukti' => $request->session()->get('bukti_permohonan'),
        ]);
    }

    /**
     * Mengajukan permohonan informasi — PRD 6.14 & 10.4.
     *
     * Tanpa login: warga cukup menerima nomor registrasi untuk melacak
     * statusnya kemudian.
     */
    public function ajukanPermohonan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_pemohon' => ['required', 'string', 'max:255'],
            'no_identitas' => ['nullable', 'string', 'max:32'],
            'kontak' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'informasi_diminta' => ['required', 'string', 'max:2000'],
            'tujuan_penggunaan' => ['nullable', 'string', 'max:1000'],
            'cara_memperoleh' => ['required', Rule::in(['langsung', 'email', 'pos'])],
            ...$this->captcha->aturanValidasi(),
        ], [
            'nama_pemohon.required' => 'Nama pemohon wajib diisi.',
            'kontak.required' => 'Kontak yang dapat dihubungi wajib diisi.',
            'informasi_diminta.required' => 'Uraikan informasi yang Anda minta.',
        ]);

        if (! $this->captcha->verifikasi($request)) {
            return back()->withErrors([
                'nama_pemohon' => 'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.',
            ]);
        }

        $permohonan = PpidRequest::create([
            ...$data,
            'village_id' => $this->village->id(),
            'nomor_registrasi' => PpidRequest::buatNomorRegistrasi(),
            'status' => 'diajukan',
        ]);

        // Hanya nomor registrasi yang dibawa kembali — data yang baru dikirim
        // tidak perlu dipantulkan balik ke klien.
        return back()->with('bukti_permohonan', [
            'nomor_registrasi' => $permohonan->nomor_registrasi,
            'status' => $permohonan->status,
            'tanggal_pengajuan' => $permohonan->created_at->toIso8601String(),
        ]);
    }

    /**
     * Melacak status permohonan — PRD 6.14.
     *
     * Nomor registrasi memuat bagian acak sehingga tidak dapat ditebak
     * berurutan; hasil yang ditampilkan pun hanya memuat status dan
     * tanggapan, bukan seluruh isi permohonan.
     */
    public function lacak(Request $request): Response
    {
        $request->validate(['nomor' => ['nullable', 'string', 'max:64']]);
        $nomor = $request->string('nomor')->toString();

        $permohonan = $nomor
            ? PpidRequest::where('village_id', $this->village->id())
                ->where('nomor_registrasi', $nomor)
                ->first()
            : null;

        return Inertia::render('Publik/Ppid/Lacak', [
            'nomor' => $nomor ?: null,
            'permohonan' => $permohonan ? [
                'nomor_registrasi' => $permohonan->nomor_registrasi,
                // Nama pemohon disertakan agar ia yakin melihat permohonannya
                // sendiri; nomor identitas TIDAK pernah dikembalikan.
                'nama_pemohon' => $permohonan->nama_pemohon,
                'informasi_diminta' => $permohonan->informasi_diminta,
                'status' => $permohonan->status,
                'tanggapan_admin' => $permohonan->tanggapan_admin,
                'alasan_penolakan' => $permohonan->alasan_penolakan,
                'dokumen_balasan' => $permohonan->dokumen_balasan
                    ? asset('storage/'.$permohonan->dokumen_balasan) : null,
                'tanggal_pengajuan' => $permohonan->created_at->toIso8601String(),
                'tanggal_tanggapan' => $permohonan->tanggal_tanggapan?->toIso8601String(),
            ] : null,
            // Dibedakan dari "belum mencari": tanpa penanda ini halaman tidak
            // dapat membedakan kunjungan pertama dari nomor yang salah ketik.
            'tidak_ditemukan' => $nomor !== '' && $permohonan === null,
        ]);
    }
}
