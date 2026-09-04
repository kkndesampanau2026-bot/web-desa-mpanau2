<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\PpidInformationItem;
use App\Models\PpidLegalBasis;
use App\Models\PpidRequest;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * CMS PPID — PRD 5.17 & 9.4.
 */
class PpidController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    // ------------------------------------------------------------------
    // Dasar hukum
    // ------------------------------------------------------------------

    public function dasarHukumIndex(): JsonResponse
    {
        $data = PpidLegalBasis::where('village_id', $this->village->id())
            ->orderBy('urutan_tampil')
            ->get();

        return ApiResponse::success($data);
    }

    public function dasarHukumSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'judul_regulasi' => ['required', 'string', 'max:255'],
            'nomor_regulasi' => ['nullable', 'string', 'max:255'],
            'tahun' => ['nullable', 'integer', 'min:1945', 'max:2100'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
            'file_pdf' => MediaService::aturanDokumen(),
        ], MediaService::pesanValidasi('file_pdf'));

        if ($request->hasFile('file_pdf')) {
            $data['file_pdf'] = $this->media->simpanDokumen($request->file('file_pdf'), 'ppid');
        }

        $dasar = PpidLegalBasis::create([...$data, 'village_id' => $this->village->id()]);

        Cache::forget("ppid:dasar-hukum:{$this->village->id()}");
        $this->logger->log('created', $dasar, "Menambah dasar hukum PPID: {$dasar->judul_regulasi}");

        return ApiResponse::success($dasar, 'Dasar hukum berhasil ditambahkan.', 201);
    }

    public function dasarHukumUbah(Request $request, PpidLegalBasis $ppidLegalBasis): JsonResponse
    {
        abort_unless($ppidLegalBasis->village_id === $this->village->id(), 404);

        $data = $request->validate([
            'judul_regulasi' => ['required', 'string', 'max:255'],
            'nomor_regulasi' => ['nullable', 'string', 'max:255'],
            'tahun' => ['nullable', 'integer', 'min:1945', 'max:2100'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0'],
            'file_pdf' => MediaService::aturanDokumen(),
        ], MediaService::pesanValidasi('file_pdf'));

        // Berkas hanya diganti bila operator benar-benar memilih yang baru;
        // menyimpan formulir tanpa memilih berkas TIDAK boleh menghapus PDF
        // yang sudah terlanjur dibagikan tautannya.
        if ($request->hasFile('file_pdf')) {
            $data['file_pdf'] = $this->media->simpanDokumen(
                $request->file('file_pdf'), 'ppid', $ppidLegalBasis->file_pdf
            );
        } else {
            unset($data['file_pdf']);
        }

        $sebelum = $ppidLegalBasis->getOriginal();
        $ppidLegalBasis->update($data);

        Cache::forget("ppid:dasar-hukum:{$this->village->id()}");
        $this->logger->log(
            'updated', $ppidLegalBasis, "Memperbarui dasar hukum PPID: {$ppidLegalBasis->judul_regulasi}",
            $sebelum, $ppidLegalBasis->getAttributes()
        );

        return ApiResponse::success($ppidLegalBasis, 'Dasar hukum berhasil diperbarui.');
    }

    public function dasarHukumHapus(PpidLegalBasis $ppidLegalBasis): JsonResponse
    {
        abort_unless($ppidLegalBasis->village_id === $this->village->id(), 404);

        $judul = $ppidLegalBasis->judul_regulasi;

        $this->media->hapus($ppidLegalBasis->file_pdf);
        $ppidLegalBasis->delete();

        Cache::forget("ppid:dasar-hukum:{$this->village->id()}");
        $this->logger->log('deleted', $ppidLegalBasis, "Menghapus dasar hukum PPID: {$judul}");

        return ApiResponse::success(message: 'Dasar hukum berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Informasi berkala / serta-merta / setiap saat
    // ------------------------------------------------------------------

    public function informasiIndex(Request $request): JsonResponse
    {
        $data = PpidInformationItem::where('village_id', $this->village->id())
            ->when(
                $request->string('jenis')->toString(),
                fn ($q, $jenis) => $q->where('jenis', $jenis)
            )
            ->orderByDesc('tanggal_publish')
            ->get();

        return ApiResponse::success($data);
    }

    public function informasiSimpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['required', Rule::in(PpidInformationItem::JENIS)],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'periode' => ['nullable', 'string', 'max:255'],
            // Hanya bermakna untuk informasi serta-merta.
            'tingkat_urgensi' => ['nullable', Rule::in(['rendah', 'sedang', 'tinggi'])],
            'tanggal_publish' => ['nullable', 'date'],
            'status_tampil' => ['nullable', 'boolean'],
            'file' => MediaService::aturanDokumen(),
        ], MediaService::pesanValidasi('file'));

        if ($request->hasFile('file')) {
            $data['file'] = $this->media->simpanDokumen($request->file('file'), 'ppid');
        }

        $item = PpidInformationItem::create([
            ...$data,
            'village_id' => $this->village->id(),
            'tanggal_publish' => $data['tanggal_publish'] ?? now()->toDateString(),
        ]);

        $this->bersihkanCacheInformasi();
        $this->logger->log('created', $item, "Menambah informasi PPID ({$item->jenis}): {$item->judul}");

        return ApiResponse::success($item, 'Informasi publik berhasil ditambahkan.', 201);
    }

    public function informasiUbah(Request $request, PpidInformationItem $ppidInformationItem): JsonResponse
    {
        abort_unless($ppidInformationItem->village_id === $this->village->id(), 404);

        $data = $request->validate([
            'jenis' => ['required', Rule::in(PpidInformationItem::JENIS)],
            'judul' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'periode' => ['nullable', 'string', 'max:255'],
            'tingkat_urgensi' => ['nullable', Rule::in(['rendah', 'sedang', 'tinggi'])],
            'tanggal_publish' => ['nullable', 'date'],
            'status_tampil' => ['nullable', 'boolean'],
            'file' => MediaService::aturanDokumen(),
        ], MediaService::pesanValidasi('file'));

        if ($request->hasFile('file')) {
            $data['file'] = $this->media->simpanDokumen(
                $request->file('file'), 'ppid', $ppidInformationItem->file
            );
        } else {
            unset($data['file']);
        }

        $sebelum = $ppidInformationItem->getOriginal();
        $ppidInformationItem->update($data);

        $this->bersihkanCacheInformasi();
        $this->logger->log(
            'updated', $ppidInformationItem, "Memperbarui informasi PPID: {$ppidInformationItem->judul}",
            $sebelum, $ppidInformationItem->getAttributes()
        );

        return ApiResponse::success($ppidInformationItem, 'Informasi publik berhasil diperbarui.');
    }

    public function informasiHapus(PpidInformationItem $ppidInformationItem): JsonResponse
    {
        abort_unless($ppidInformationItem->village_id === $this->village->id(), 404);

        $judul = $ppidInformationItem->judul;

        $this->media->hapus($ppidInformationItem->file);
        $ppidInformationItem->delete();

        $this->bersihkanCacheInformasi();
        $this->logger->log('deleted', $ppidInformationItem, "Menghapus informasi PPID: {$judul}");

        return ApiResponse::success(message: 'Informasi publik berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Permohonan informasi
    // ------------------------------------------------------------------

    public function permohonanIndex(Request $request): JsonResponse
    {
        $permohonan = PpidRequest::query()
            ->where('village_id', $this->village->id())
            ->with('penanggungJawab:id,name')
            ->when(
                $request->string('status')->toString(),
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::paginated($permohonan);
    }

    public function permohonanShow(PpidRequest $ppidRequest): JsonResponse
    {
        abort_unless($ppidRequest->village_id === $this->village->id(), 404);

        // Nomor identitas pemohon adalah data pribadi; membukanya dicatat
        // sebagai jejak akses.
        $this->logger->logAccess(
            $ppidRequest,
            "Membuka permohonan informasi {$ppidRequest->nomor_registrasi}"
        );

        return ApiResponse::success([
            ...$ppidRequest->toArray(),
            'no_identitas' => $ppidRequest->no_identitas,
        ]);
    }

    /**
     * Menanggapi permohonan — PRD 6.14.
     *
     * UU KIP mewajibkan penolakan disertai alasan, sehingga aturan validasi di
     * bawah menjadikannya wajib saat status `ditolak`. Ini bukan sekadar
     * kelengkapan data: pemohon berhak mengetahui dasar penolakannya untuk
     * dapat mengajukan keberatan.
     */
    public function permohonanTanggapi(Request $request, PpidRequest $ppidRequest): JsonResponse
    {
        abort_unless($ppidRequest->village_id === $this->village->id(), 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(PpidRequest::STATUS)],
            'tanggapan_admin' => ['nullable', 'string', 'max:2000'],
            'alasan_penolakan' => [
                Rule::requiredIf(fn () => $request->input('status') === 'ditolak'),
                'nullable', 'string', 'max:2000',
            ],
            'dokumen_balasan' => MediaService::aturanDokumen(),
        ], [
            ...MediaService::pesanValidasi('dokumen_balasan'),
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi sesuai '
                .'Undang-Undang Keterbukaan Informasi Publik.',
        ]);

        if ($request->hasFile('dokumen_balasan')) {
            $data['dokumen_balasan'] = $this->media->simpanDokumen(
                $request->file('dokumen_balasan'), 'ppid', $ppidRequest->dokumen_balasan
            );
        } else {
            unset($data['dokumen_balasan']);
        }

        $sebelum = $ppidRequest->getOriginal();

        $ppidRequest->update([
            ...$data,
            'ditangani_oleh' => $request->user()->id,
            'tanggal_tanggapan' => now(),
        ]);

        $this->logger->log(
            'updated', $ppidRequest,
            "Menanggapi permohonan {$ppidRequest->nomor_registrasi}: status {$data['status']}",
            $sebelum, $ppidRequest->getAttributes()
        );

        return ApiResponse::success(
            ['nomor_registrasi' => $ppidRequest->nomor_registrasi, 'status' => $ppidRequest->status],
            'Tanggapan berhasil disimpan.'
        );
    }

    private function bersihkanCacheInformasi(): void
    {
        foreach (PpidInformationItem::JENIS as $jenis) {
            Cache::forget("ppid:informasi:{$this->village->id()}:{$jenis}");
        }
    }
}
