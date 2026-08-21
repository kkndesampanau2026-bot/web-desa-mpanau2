<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\BansosRecipient;
use App\Models\BansosType;
use App\Services\BansosSearchService;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
    private const TTL = 3600;

    public function __construct(
        private readonly CurrentVillage $village,
        private readonly BansosSearchService $pencarian,
        private readonly CaptchaVerifier $captcha,
    ) {}

    /** Rekap jumlah penerima per jenis bantuan — agregat, tanpa identitas. */
    public function infografis(): JsonResponse
    {
        $villageId = $this->village->id();

        $data = Cache::remember("infografis:bansos:{$villageId}", self::TTL, function () use ($villageId) {
            $tahunTerbaru = BansosRecipient::where('village_id', $villageId)
                ->max('tahun_anggaran');

            if (! $tahunTerbaru) {
                return null;   // empty-state (PRD 3.2)
            }

            $perJenis = BansosType::query()
                ->where('village_id', $villageId)
                ->where('status_aktif', true)
                ->withCount([
                    'recipients as jumlah_penerima' => fn ($q) => $q
                        ->where('tahun_anggaran', $tahunTerbaru)
                        ->where('status', 'aktif'),
                ])
                ->orderBy('urutan_tampil')
                ->get()
                ->map(fn ($t) => [
                    'jenis_bantuan' => $t->nama,
                    'deskripsi' => $t->deskripsi,
                    'sumber_dana' => $t->sumber_dana,
                    'jumlah_penerima' => $t->jumlah_penerima,
                ]);

            return [
                'tahun_anggaran' => (int) $tahunTerbaru,
                'total_penerima' => BansosRecipient::where('village_id', $villageId)
                    ->where('tahun_anggaran', $tahunTerbaru)
                    ->where('status', 'aktif')
                    ->count(),
                'per_jenis' => $perJenis,
                'tahun_tersedia' => BansosRecipient::where('village_id', $villageId)
                    ->distinct()
                    ->orderByDesc('tahun_anggaran')
                    ->pluck('tahun_anggaran'),
            ];
        });

        return ApiResponse::success($data);
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
