<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\LampiranPengaduanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manajemen Pengaduan — PRD 5.18 & 9.4.
 */
class PengaduanController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly LampiranPengaduanService $lampiran,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(Complaint::STATUS)],
            'kategori' => ['nullable', Rule::in(Complaint::KATEGORI)],
        ]);

        $pengaduan = Complaint::query()
            ->where('village_id', $this->village->id())
            ->withCount('attachments')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when(
                $request->string('kategori')->toString(),
                fn ($q, $k) => $q->where('kategori_pengaduan', $k)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        // Daftar sengaja TIDAK memuat nomor telepon pelapor — kontak baru
        // dibuka saat admin benar-benar membuka satu pengaduan.
        $pengaduan->setCollection($pengaduan->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'nomor_tiket' => $p->nomor_tiket,
            'nama' => $p->nama,
            'kategori_pengaduan' => $p->kategori_pengaduan,
            'isi_ringkas' => mb_substr($p->isi_pengaduan, 0, 120),
            'status' => $p->status,
            'jumlah_lampiran' => $p->attachments_count,
            'tanggal_pengaduan' => $p->created_at->toIso8601String(),
        ]));

        return ApiResponse::paginated($pengaduan);
    }

    /**
     * Detail pengaduan beserta kontak pelapor.
     *
     * Nomor telepon warga adalah data pribadi, jadi pembukaannya dicatat
     * sebagai jejak akses — sama seperti membuka NIK pada modul lain.
     */
    public function show(Complaint $complaint): JsonResponse
    {
        $this->pastikanMilikDesaIni($complaint);

        $this->logger->logAccess(
            $complaint,
            "Membuka pengaduan {$complaint->nomor_tiket}"
        );

        return ApiResponse::success([
            'id' => $complaint->id,
            'nomor_tiket' => $complaint->nomor_tiket,
            'nama' => $complaint->nama,
            'no_telepon_wa' => $complaint->no_telepon_wa,
            'kategori_pengaduan' => $complaint->kategori_pengaduan,
            'isi_pengaduan' => $complaint->isi_pengaduan,
            'status' => $complaint->status,
            'tanggapan_admin' => $complaint->tanggapan_admin,
            'alasan_penolakan' => $complaint->alasan_penolakan,
            'tanggal_pengaduan' => $complaint->created_at->toIso8601String(),
            'tanggal_tanggapan' => $complaint->tanggal_tanggapan?->toIso8601String(),
            'lampiran' => $complaint->attachments->map(fn ($l) => [
                'id' => $l->id,
                'nama_asli' => $l->nama_asli,
                'mime_type' => $l->mime_type,
                'ukuran_byte' => $l->ukuran_byte,
                // Tidak ada URL langsung: lampiran hanya dapat diambil lewat
                // endpoint unduhan di bawah, yang memeriksa permission.
            ]),
        ]);
    }

    public function tanggapi(Request $request, Complaint $complaint): JsonResponse
    {
        $this->pastikanMilikDesaIni($complaint);

        $data = $request->validate([
            'status' => ['required', Rule::in(Complaint::STATUS)],
            'tanggapan_admin' => ['nullable', 'string', 'max:2000'],
            'alasan_penolakan' => [
                Rule::requiredIf(fn () => $request->input('status') === 'ditolak'),
                'nullable', 'string', 'max:2000',
            ],
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi agar '
                .'pelapor mengetahui dasar keputusannya.',
        ]);

        $sebelum = $complaint->getOriginal();

        $complaint->update([
            ...$data,
            'ditangani_oleh' => $request->user()->id,
            'tanggal_tanggapan' => now(),
        ]);

        $this->logger->log(
            'updated', $complaint,
            "Menanggapi pengaduan {$complaint->nomor_tiket}: status {$data['status']}",
            $sebelum, $complaint->getAttributes()
        );

        return ApiResponse::success(
            ['nomor_tiket' => $complaint->nomor_tiket, 'status' => $complaint->status],
            'Tanggapan berhasil disimpan.'
        );
    }

    /**
     * Mengunduh lampiran.
     *
     * Berkas berada pada disk privat, sehingga hanya dapat diambil lewat jalur
     * ini yang sudah melewati autentikasi + permission. Header Content-Type
     * dipaksakan dari MIME yang tercatat saat unggah, dan berkas selalu
     * dikirim sebagai attachment agar peramban tidak pernah merendernya
     * inline — sebuah SVG atau HTML berbahaya tidak akan tereksekusi di
     * domain kita.
     */
    public function unduhLampiran(Complaint $complaint, ComplaintAttachment $attachment): StreamedResponse
    {
        $this->pastikanMilikDesaIni($complaint);

        abort_unless($attachment->complaint_id === $complaint->id, 404);
        abort_unless(Storage::disk(LampiranPengaduanService::DISK)->exists($attachment->path), 404);

        $this->logger->logAccess(
            $complaint,
            "Mengunduh lampiran pengaduan {$complaint->nomor_tiket}: {$attachment->nama_asli}"
        );

        return Storage::disk(LampiranPengaduanService::DISK)->download(
            $attachment->path,
            $attachment->nama_asli,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'attachment',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /** Rekap jumlah pengaduan per status & kategori untuk dashboard. */
    public function rekap(): JsonResponse
    {
        $villageId = $this->village->id();

        return ApiResponse::success([
            'per_status' => collect(Complaint::STATUS)->mapWithKeys(
                fn ($s) => [$s => Complaint::where('village_id', $villageId)
                    ->where('status', $s)->count()]
            ),
            'per_kategori' => collect(Complaint::KATEGORI)->mapWithKeys(
                fn ($k) => [$k => Complaint::where('village_id', $villageId)
                    ->where('kategori_pengaduan', $k)->count()]
            ),
            'belum_ditanggapi' => Complaint::where('village_id', $villageId)
                ->where('status', 'baru')->count(),
        ]);
    }

    private function pastikanMilikDesaIni(Complaint $complaint): void
    {
        abort_unless($complaint->village_id === $this->village->id(), 404);
    }
}
