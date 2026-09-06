<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\LampiranPengaduanService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membuka satu lampiran pengaduan di peramban.
 *
 * Padanan berbasis SESI dari `Api\V1\Admin\PengaduanController::unduhLampiran`.
 * Keduanya dibutuhkan karena jalurnya berbeda: endpoint /api/v1 hanya menjadi
 * bersesi bila permintaannya membawa header `Origin`/`Referer` dari domain
 * terdaftar (Sanctum). Tautan yang diklik dari BERKAS EXCEL tidak mengirim
 * header itu, sehingga endpoint lama selalu membalas 401 — tautan pada rekap
 * akan selalu mati bila mengarah ke sana.
 *
 * Disajikan `inline`, bukan `attachment`, supaya PDF terbuka langsung di tab
 * peramban. Itu melonggarkan penjagaan yang disengaja pada endpoint lama
 * ("berkas selalu dikirim sebagai attachment agar peramban tidak pernah
 * merendernya inline"), jadi penggantinya ditegakkan di sini: MIME yang
 * tercatat diperiksa ULANG terhadap daftar izin sebelum disajikan inline, dan
 * apa pun di luar daftar itu tetap dipaksa terunduh. Daftar itu hanya memuat
 * JPEG, PNG, WebP, dan PDF — tidak ada SVG maupun HTML yang dapat membawa
 * skrip.
 */
class LampiranPengaduanController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CurrentVillage $village,
    ) {}

    public function __invoke(Complaint $pengaduan, ComplaintAttachment $lampiran): StreamedResponse
    {
        abort_unless($pengaduan->village_id === $this->village->id(), 404);
        abort_unless($lampiran->complaint_id === $pengaduan->id, 404);

        $disk = Storage::disk(LampiranPengaduanService::DISK);
        abort_unless($disk->exists($lampiran->path), 404);

        $this->logger->logAccess(
            $pengaduan,
            "Membuka lampiran pengaduan {$pengaduan->nomor_tiket}: {$lampiran->nama_asli}"
        );

        $amanInline = array_key_exists(
            (string) $lampiran->mime_type,
            LampiranPengaduanService::MIME_DIIZINKAN
        );

        return $disk->response($lampiran->path, $lampiran->nama_asli, [
            'Content-Type' => $lampiran->mime_type,
            'Content-Disposition' => ($amanInline ? 'inline' : 'attachment')
                .'; filename="'.addslashes($lampiran->nama_asli).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
