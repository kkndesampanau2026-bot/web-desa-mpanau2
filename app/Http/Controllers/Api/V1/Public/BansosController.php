<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\BansosSearchService;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use App\Services\DataInfografis;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bantuan Sosial — endpoint publik (PRD 9.2).
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada endpoint yang
 * mengembalikan daftar penerima. PRD 6.6 menegaskan larangan itu, dan
 * cara paling andal menegakkannya adalah dengan tidak pernah menulis
 * endpointnya — bukan dengan menyaring keluarannya.
 */
class BansosController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly BansosSearchService $pencarian,
        private readonly CaptchaVerifier $captcha,
        private readonly DataInfografis $infografis,
    ) {}

    /** Rekap jumlah penerima per jenis bantuan — agregat, tanpa identitas. */
    public function infografis(): JsonResponse
    {
        return ApiResponse::success($this->infografis->bansos());
    }

    /**
     * Cek Penerima Bansos — PRD 6.6 & 10.3.
     *
     * Rate limiting dipasang di lapisan route. Seluruh logika perlindungan
     * privasi ada di BansosSearchService; controller hanya memvalidasi bentuk
     * masukan dan meneruskannya.
     */
    public function cekPenerima(Request $request): JsonResponse
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
            return ApiResponse::error(
                'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.',
                422
            );
        }

        $hasil = $this->pencarian->cari(
            $data['nama'],
            $data['empat_digit_nik'],
            $this->village->id(),
            $request,
        );

        // Selalu 200, baik ditemukan maupun tidak. Membedakan status HTTP
        // (mis. 404 saat tidak ada) akan membocorkan keberadaan data lewat
        // kode respons, meski badan pesannya sudah dibuat netral.
        return ApiResponse::success($hasil, $hasil['pesan']);
    }
}
