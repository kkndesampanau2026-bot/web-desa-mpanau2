<?php

namespace App\Http\Controllers\Admin;

use App\Exports\RekapPengaduanExport;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduh rekap pengaduan warga sebagai berkas Excel.
 *
 * Berdiri sebagai controller tersendiri, bukan method tambahan pada
 * `Api\V1\Admin\PengaduanController`: yang dihasilkannya berkas, bukan JSON
 * ber-kontrak `ApiResponse`, dan menaruhnya di jalur /api berarti satu-satunya
 * endpoint di sana yang tidak mengikuti kontrak itu.
 *
 * Isinya data pribadi pelapor — nama, nomor WhatsApp, isi aduan — sehingga:
 *  1. dijaga permission `respond-complaint`, sama dengan layar Pengaduan;
 *  2. SETIAP pengunduhan dicatat pada audit trail, lengkap dengan penyaring
 *     yang dipakai dan berapa baris yang terbawa. Ekspor adalah cara termudah
 *     memindahkan seluruh data pelapor keluar dari sistem, dan jejaknya tidak
 *     boleh lebih tipis daripada jejak membuka satu pengaduan.
 */
class EksporPengaduanController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CurrentVillage $village,
    ) {}

    public function __invoke(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Complaint::STATUS)],
            'kategori' => ['nullable', Rule::in(Complaint::KATEGORI)],
        ]);

        $villageId = $this->village->id();
        $status = $data['status'] ?? null;
        $kategori = $data['kategori'] ?? null;

        $ekspor = new RekapPengaduanExport($villageId, $status, $kategori);

        $jumlah = $ekspor->collection()->count();

        $this->logger->log(
            'exported',
            null,
            "Rekap pengaduan diunduh ({$jumlah} baris)",
            dataSesudah: [
                'jumlah_baris' => $jumlah,
                'penyaring_status' => $status,
                'penyaring_kategori' => $kategori,
            ],
        );

        return $ekspor->download($this->namaBerkas($status, $kategori));
    }

    private function namaBerkas(?string $status, ?string $kategori): string
    {
        // Penyaring ikut pada nama berkas: operator kerap mengunduh beberapa
        // rekap berturut-turut, dan tiga berkas bernama sama di folder unduhan
        // tidak dapat dibedakan lagi setelahnya.
        $bagian = array_filter(['rekap-pengaduan', $status, $kategori]);

        $nama = collect($bagian)
            ->map(fn (string $b) => str($b)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->value())
            ->implode('-');

        return $nama.'-'.now()->format('Ymd-His').'.xlsx';
    }
}
