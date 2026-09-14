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
 * Mengunduh satu lampiran pengaduan.
 *
 * Berdiri sebagai route WEB, bukan `/api/v1/*` — dan itu bukan soal selera.
 * Tautannya dibuka peramban sebagai navigasi biasa, sedangkan jalur /api
 * dijaga guard `sanctum`. Sanctum baru mengakui sesi login bila permintaannya
 * dianggap "stateful", dan penilaian itu bersandar pada header `Origin` atau
 * `Referer`. Navigasi tab baru tidak mengirim `Origin`, dan `rel="noreferrer"`
 * pada tautannya ikut membuang `Referer` — sehingga permintaan sah dari
 * operator yang sudah masuk dijawab:
 *
 *     {"success":false,"message":"Anda harus masuk untuk mengakses sumber daya ini."}
 *
 * Gejalanya menyesatkan karena layar Pengaduan di sekitarnya tetap berfungsi:
 * XHR-nya membawa `Origin`, unduhan tidak. Lewat route web, autentikasinya
 * cukup cookie sesi biasa dan tidak bergantung pada header apa pun.
 *
 * Isinya berkas milik warga pada disk privat, jadi penjagaannya sama dengan
 * layar Pengaduan (`respond-complaint`), dan setiap pembukaan tercatat pada
 * audit trail — lampiran kerap memuat foto rumah, wajah, atau dokumen pelapor.
 */
class LampiranPengaduanController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CurrentVillage $village,
    ) {}

    public function __invoke(Complaint $complaint, ComplaintAttachment $attachment): StreamedResponse
    {
        // Tiga penjagaan berurutan: desa yang benar, lampiran yang memang milik
        // pengaduan itu (menukar id tidak membuka berkas milik aduan lain), dan
        // berkasnya masih ada di disk.
        abort_unless($complaint->village_id === $this->village->id(), 404);
        abort_unless($attachment->complaint_id === $complaint->id, 404);
        abort_unless(Storage::disk(LampiranPengaduanService::DISK)->exists($attachment->path), 404);

        $this->logger->logAccess(
            $complaint,
            "Mengunduh lampiran pengaduan {$complaint->nomor_tiket}: {$attachment->nama_asli}"
        );

        /*
         * Selalu dikirim sebagai attachment dengan MIME yang tercatat saat
         * unggah, disertai `nosniff`: berkas dari publik tidak boleh pernah
         * dirender inline pada domain ini (docs/DEVIASI.md §C13).
         */
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
}
