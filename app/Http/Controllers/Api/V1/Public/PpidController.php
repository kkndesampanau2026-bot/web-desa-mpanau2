<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\PpidInformationItem;
use App\Models\PpidLegalBasis;
use App\Models\PpidRequest;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * PPID — endpoint publik (PRD 9.4).
 */
class PpidController extends Controller
{
    private const TTL = 3600;

    public function __construct(
        private readonly CurrentVillage $village,
        private readonly CaptchaVerifier $captcha,
    ) {}

    public function dasarHukum(): JsonResponse
    {
        $villageId = $this->village->id();

        $data = Cache::remember(
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

        return ApiResponse::success($data);
    }

    /** Daftar dokumen menurut jenis: berkala, serta-merta, atau setiap saat. */
    public function informasi(Request $request): JsonResponse
    {
        $request->validate([
            'jenis' => ['required', Rule::in(PpidInformationItem::JENIS)],
        ], [
            'jenis.in' => 'Jenis informasi harus berkala, serta-merta, atau setiap-saat.',
        ]);

        $jenis = $request->string('jenis')->toString();
        $villageId = $this->village->id();

        $data = Cache::remember(
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

        return ApiResponse::success($data);
    }

    /**
     * Mengajukan permohonan informasi — PRD 6.14 & 10.4.
     *
     * Tanpa login: warga cukup menerima nomor registrasi untuk melacak
     * statusnya kemudian.
     */
    public function ajukanPermohonan(Request $request): JsonResponse
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
            return ApiResponse::error(
                'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.',
                422
            );
        }

        $permohonan = PpidRequest::create([
            ...$data,
            'village_id' => $this->village->id(),
            'nomor_registrasi' => PpidRequest::buatNomorRegistrasi(),
            'status' => 'diajukan',
        ]);

        // Hanya nomor registrasi yang dikembalikan — data yang baru dikirim
        // tidak perlu dipantulkan balik ke klien.
        return ApiResponse::success(
            [
                'nomor_registrasi' => $permohonan->nomor_registrasi,
                'status' => $permohonan->status,
                'tanggal_pengajuan' => $permohonan->created_at->toIso8601String(),
            ],
            'Permohonan informasi berhasil diajukan. Simpan nomor registrasi Anda '
            .'untuk melacak status permohonan.',
            201
        );
    }

    /**
     * Melacak status permohonan — PRD 6.14.
     *
     * Nomor registrasi memuat bagian acak sehingga tidak dapat ditebak
     * berurutan; hasil yang dikembalikan pun hanya memuat status dan
     * tanggapan, bukan seluruh isi permohonan.
     */
    public function lacakPermohonan(string $nomorRegistrasi): JsonResponse
    {
        $permohonan = PpidRequest::where('village_id', $this->village->id())
            ->where('nomor_registrasi', $nomorRegistrasi)
            ->first();

        if (! $permohonan) {
            return ApiResponse::error('Nomor registrasi tidak ditemukan.', 404);
        }

        return ApiResponse::success([
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
        ]);
    }
}
