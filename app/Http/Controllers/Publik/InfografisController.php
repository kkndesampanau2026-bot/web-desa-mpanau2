<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\BansosSearchService;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use App\Services\DataInfografis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Infografis publik — PRD 9.2 & Bagian 11.
 *
 * Enam sub-halaman di bawah satu induk, mengikuti pola situs referensi.
 * Seluruhnya membaca data AGREGAT: tidak satu pun menyentuh tabel berisi data
 * pribadi (`residents`, `bansos_recipients` per individu). Perhatikan pula apa
 * yang tidak ada di kelas ini — tidak ada route yang mengembalikan daftar
 * penerima bantuan. PRD 6.6 melarangnya, dan cara paling andal menegakkan
 * larangan itu adalah dengan tidak pernah menulis jalurnya.
 */
class InfografisController extends Controller
{
    public function __construct(
        private readonly DataInfografis $infografis,
        private readonly CurrentVillage $village,
        private readonly BansosSearchService $pencarian,
        private readonly CaptchaVerifier $captcha,
    ) {}

    public function penduduk(): Response
    {
        return Inertia::render('Publik/Infografis/Penduduk', [
            'data' => $this->infografis->penduduk(),
        ]);
    }

    public function apbdes(Request $request): Response
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return Inertia::render('Publik/Infografis/Apbdes', [
            'data' => $this->infografis->apbdes($request->integer('tahun') ?: null),
        ]);
    }

    public function stunting(): Response
    {
        return Inertia::render('Publik/Infografis/Stunting', [
            'data' => $this->infografis->stunting(),
        ]);
    }

    public function idm(Request $request): Response
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return Inertia::render('Publik/Infografis/Idm', [
            'data' => $this->infografis->idm($request->integer('tahun') ?: null),
        ]);
    }

    public function sdgs(Request $request): Response
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return Inertia::render('Publik/Infografis/Sdgs', [
            'data' => $this->infografis->sdgs($request->integer('tahun') ?: null),
        ]);
    }

    /** Rekap jumlah penerima per jenis bantuan — agregat, tanpa identitas. */
    public function bansos(Request $request): Response
    {
        return Inertia::render('Publik/Infografis/Bansos', [
            'data' => $this->infografis->bansos(),
            /*
             * Hasil pencarian dititipkan lewat flash session oleh `cekPenerima`
             * di bawah, bukan disimpan di state React. Menyegarkan halaman
             * karenanya membuang hasil pencarian — memang disengaja: hasil ini
             * menyangkut status bantuan seseorang dan tidak sepatutnya tetap
             * terpampang pada layar bersama di kantor desa.
             */
            'hasil_cek' => $request->session()->get('hasil_cek'),
        ]);
    }

    /**
     * Cek Penerima Bansos — PRD 6.6 & 10.3.
     *
     * Rate limiting dipasang di lapisan route. Seluruh logika perlindungan
     * privasi ada di BansosSearchService; controller hanya memvalidasi bentuk
     * masukan dan meneruskannya.
     */
    public function cekPenerima(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:255'],
            // Empat digit, bukan NIK penuh — meminta NIK lengkap justru
            // membiasakan warga menyerahkan data pribadi ke formulir web.
            'empat_digit_nik' => ['required', 'string', 'regex:/^\d{4}$/'],
            ...$this->captcha->aturanValidasi(),
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.min' => 'Nama lengkap terlalu pendek.',
            'empat_digit_nik.required' => 'Empat digit terakhir NIK wajib diisi.',
            'empat_digit_nik.regex' => 'Masukkan tepat 4 digit terakhir NIK Anda.',
        ]);

        // Diperiksa SETELAH validasi bentuk, sebelum menyentuh basis data.
        // Bila CAPTCHA tidak dikonfigurasi, pemeriksaan ini selalu lolos.
        if (! $this->captcha->verifikasi($request)) {
            return back()->withErrors([
                'nama' => 'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.',
            ]);
        }

        $hasil = $this->pencarian->cari(
            $data['nama'],
            $data['empat_digit_nik'],
            $this->village->id(),
            $request,
        );

        // Selalu redirect biasa, baik ditemukan maupun tidak. Membedakan
        // perlakuan (mis. galat saat tidak ada) akan membocorkan keberadaan
        // data lewat perilaku halaman, meski pesannya sudah dibuat netral.
        return back()->with('hasil_cek', $hasil);
    }

}
