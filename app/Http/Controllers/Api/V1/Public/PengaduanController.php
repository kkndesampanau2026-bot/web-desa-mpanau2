<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\CaptchaVerifier;
use App\Services\CurrentVillage;
use App\Services\LampiranPengaduanService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pengaduan Masyarakat — endpoint publik (PRD 9.4 & 10.2).
 *
 * Seperti PPID, seluruh alur berjalan tanpa login: warga mengirim pengaduan,
 * menerima nomor tiket, lalu memakai nomor itu untuk memantau tindak lanjut.
 *
 * Tidak ada endpoint yang mengembalikan daftar pengaduan. Isi pengaduan kerap
 * menyangkut orang lain, sehingga daftar semacam itu akan berubah menjadi
 * papan pengumuman keluhan antarwarga.
 */
class PengaduanController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly LampiranPengaduanService $lampiran,
        private readonly CaptchaVerifier $captcha,
    ) {}

    /** Kategori pengaduan untuk mengisi pilihan pada formulir. */
    public function kategori(): JsonResponse
    {
        return ApiResponse::success(Complaint::KATEGORI);
    }

    public function ajukan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            // Nomor Indonesia; tanda pisah diperbolehkan karena warga lazim
            // menuliskannya dengan spasi atau strip.
            'no_telepon_wa' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{8,}$/'],
            'kategori_pengaduan' => ['required', Rule::in(Complaint::KATEGORI)],
            'isi_pengaduan' => ['required', 'string', 'min:10', 'max:5000'],
            ...LampiranPengaduanService::aturanValidasi(),
            ...$this->captcha->aturanValidasi(),
        ], [
            'nama.required' => 'Nama wajib diisi.',
            'no_telepon_wa.required' => 'Nomor telepon/WhatsApp wajib diisi.',
            'no_telepon_wa.regex' => 'Nomor telepon tidak dikenali. Gunakan format 08xx atau +62xx.',
            'kategori_pengaduan.required' => 'Pilih kategori pengaduan.',
            'isi_pengaduan.required' => 'Isi pengaduan wajib diisi.',
            'isi_pengaduan.min' => 'Uraikan pengaduan Anda sedikit lebih jelas.',
            ...LampiranPengaduanService::pesanValidasi(),
        ]);

        if (! $this->captcha->verifikasi($request)) {
            return ApiResponse::error(
                'Verifikasi keamanan gagal. Silakan muat ulang halaman dan coba lagi.',
                422
            );
        }

        // Dibungkus transaksi agar pengaduan tidak pernah tersimpan setengah
        // jadi bila penyimpanan lampiran gagal di tengah jalan.
        $pengaduan = DB::transaction(function () use ($data, $request) {
            $pengaduan = Complaint::create([
                'village_id' => $this->village->id(),
                'nomor_tiket' => Complaint::buatNomorTiket(),
                'nama' => $data['nama'],
                'no_telepon_wa' => $data['no_telepon_wa'],
                'kategori_pengaduan' => $data['kategori_pengaduan'],
                'isi_pengaduan' => $data['isi_pengaduan'],
                'status' => 'baru',
                // IP disimpan ter-hash saja — cukup untuk mengenali banjir
                // pengaduan dari satu sumber tanpa menyimpan identitas jaringan.
                'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')),
            ]);

            if ($request->hasFile('lampiran')) {
                $this->lampiran->simpan($pengaduan, $request->file('lampiran'));
            }

            return $pengaduan;
        });

        return ApiResponse::success(
            [
                'nomor_tiket' => $pengaduan->nomor_tiket,
                'status' => $pengaduan->status,
                'tanggal_pengaduan' => $pengaduan->created_at->toIso8601String(),
                'jumlah_lampiran' => $pengaduan->attachments()->count(),
            ],
            'Pengaduan berhasil dikirim. Simpan nomor tiket Anda untuk memantau '
            .'tindak lanjutnya.',
            201
        );
    }

    /**
     * Pelacakan status berdasarkan nomor tiket — PRD 6.15.
     *
     * Nama pelapor dikembalikan TERSAMAR: nomor tiket bisa saja terbaca orang
     * lain (terkirim salah, terlihat di layar), dan pelapor sendiri tetap
     * mengenali namanya dari bagian yang tampak.
     */
    public function lacak(string $nomorTiket): JsonResponse
    {
        $pengaduan = Complaint::where('village_id', $this->village->id())
            ->where('nomor_tiket', $nomorTiket)
            ->first();

        if (! $pengaduan) {
            return ApiResponse::error('Nomor tiket tidak ditemukan.', 404);
        }

        return ApiResponse::success([
            'nomor_tiket' => $pengaduan->nomor_tiket,
            'nama' => $pengaduan->namaTersamar(),
            'kategori_pengaduan' => $pengaduan->kategori_pengaduan,
            'isi_pengaduan' => $pengaduan->isi_pengaduan,
            'status' => $pengaduan->status,
            'tanggapan_admin' => $pengaduan->tanggapan_admin,
            'alasan_penolakan' => $pengaduan->alasan_penolakan,
            'tanggal_pengaduan' => $pengaduan->created_at->toIso8601String(),
            'tanggal_tanggapan' => $pengaduan->tanggal_tanggapan?->toIso8601String(),
            // Nomor telepon pelapor TIDAK pernah dikembalikan di sini.
        ]);
    }
}
