<?php

namespace App\Services;

use App\Models\BansosRecipient;
use App\Models\BansosSearchLog;
use Illuminate\Http\Request;

/**
 * Pencarian mandiri status penerima bansos — PRD 6.6, 10.3 & 15.
 *
 * Fitur publik paling sensitif pada sistem ini: ia menjawab pertanyaan
 * "apakah orang ini menerima bantuan?" kepada siapa pun tanpa login. Seluruh
 * pertahanannya dikumpulkan di kelas ini agar dapat ditinjau sekaligus:
 *
 *  1. DUA faktor wajib cocok — nama lengkap DAN 4 digit terakhir NIK. Nama
 *     saja terlalu mudah ditebak; 4 digit saja hanya 10.000 kemungkinan.
 *
 *  2. Jawaban NETRAL saat tidak cocok. Sistem tidak pernah menyatakan
 *     "NIK tidak terdaftar" atau "nama tidak ditemukan" — keduanya
 *     membocorkan keberadaan/ketiadaan data. Pesan yang sama dipakai untuk
 *     semua kegagalan.
 *
 *  3. Hasil DIMINIMALKAN. Nama disamarkan sebagian, NIK tidak pernah
 *     dikembalikan, nominal hanya tampil bila admin mengizinkannya.
 *
 *  4. Setiap pencarian DICATAT tanpa menyimpan yang dicari, untuk mengenali
 *     pola enumerasi.
 *
 * Rate limiting ditangani middleware di lapisan route (PRD 6.6: 10/menit).
 */
class BansosSearchService
{
    /** Dipakai untuk semua kegagalan — lihat butir 2 di atas. */
    public const PESAN_NETRAL =
        'Data dengan nama dan digit NIK tersebut tidak ditemukan pada daftar '
        .'penerima bantuan yang dipublikasikan. Bila Anda merasa seharusnya '
        .'terdaftar, silakan menghubungi kantor desa.';

    public function __construct(private readonly PiiCipher $cipher) {}

    /**
     * @return array{ditemukan: bool, pesan: string, hasil: list<array<string, mixed>>}
     */
    public function cari(
        string $nama,
        string $empatDigitAkhir,
        int $villageId,
        Request $request,
    ): array {
        $penerima = BansosRecipient::query()
            ->where('village_id', $villageId)
            ->cocokPencarianPublik($nama, $empatDigitAkhir)
            ->with('jenisBantuan:id,nama')
            ->get();

        $this->catatPencarian($request, $villageId, $penerima->isNotEmpty());

        if ($penerima->isEmpty()) {
            return [
                'ditemukan' => false,
                'pesan' => self::PESAN_NETRAL,
                'hasil' => [],
            ];
        }

        return [
            'ditemukan' => true,
            'pesan' => 'Data penerima bantuan ditemukan.',
            'hasil' => $penerima->map(fn (BansosRecipient $p) => [
                // Nama disamarkan sebagian: cukup bagi yang bersangkutan untuk
                // mengenali dirinya, tidak cukup bagi orang lain untuk
                // menyusun daftar penerima.
                'nama' => $this->cipher->samarkanNama($p->nama),
                'jenis_bantuan' => $p->jenisBantuan?->nama,
                'tahun_anggaran' => $p->tahun_anggaran,
                'status' => $p->status,
                // Besaran bantuan hanya tampil bila admin secara sadar
                // mempublikasikannya.
                'nominal' => $p->nominal_publik ? (float) $p->nominal : null,
            ])->values()->all(),
        ];
    }

    /**
     * Mencatat pencarian tanpa menyimpan apa yang dicari.
     *
     * Yang disimpan hanya hash IP dan apakah pencarian berhasil — cukup untuk
     * mengenali satu IP yang menembak ratusan kombinasi, tanpa membangun
     * arsip nama/NIK warga yang justru menambah bahan kebocoran baru.
     */
    private function catatPencarian(Request $request, int $villageId, bool $ditemukan): void
    {
        BansosSearchLog::create([
            'village_id' => $villageId,
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')),
            'ditemukan' => $ditemukan,
        ]);
    }

    /**
     * Jumlah pencarian dari satu IP dalam rentang menit tertentu.
     *
     * Dipakai dashboard admin untuk memantau anomali; rate limiting
     * sesungguhnya ditegakkan middleware, bukan lewat method ini.
     */
    public function jumlahPencarianTerakhir(Request $request, int $villageId, int $menit = 60): int
    {
        return BansosSearchLog::where('village_id', $villageId)
            ->where('ip_hash', hash_hmac('sha256', (string) $request->ip(), config('app.key')))
            ->where('created_at', '>=', now()->subMinutes($menit))
            ->count();
    }
}
