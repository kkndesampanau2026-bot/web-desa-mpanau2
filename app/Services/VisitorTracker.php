<?php

namespace App\Services;

use App\Models\VisitorDailySummary;
use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pencatat & pembaca statistik kunjungan — PRD 6.16.
 *
 * Prinsip privasi: alamat IP tidak pernah disimpan. Yang dicatat adalah hash
 * dari (IP + user agent + APP_KEY + tanggal). Karena tanggal ikut masuk ke
 * dalam hash, nilai untuk pengunjung yang sama berubah setiap hari — sehingga
 * log ini tidak dapat dipakai melacak seseorang lintas hari, hanya menghitung
 * pengunjung unik per hari sebagaimana dibutuhkan PRD.
 */
class VisitorTracker
{
    /** Umur cache ringkasan statistik (detik). */
    private const TTL_STATISTIK = 300;

    /**
     * `$villageId` sengaja WAJIB, bukan nullable: MySQL memperlakukan NULL
     * sebagai nilai yang selalu berbeda di dalam unique index, sehingga baris
     * dengan village_id NULL akan lolos dari dedup dan menggandakan hitungan
     * setiap kali halaman dimuat ulang.
     */
    public function catat(Request $request, int $villageId): void
    {
        $tanggal = today();
        $hash = $this->sessionHash($request, $tanggal);

        // insertOrIgnore mengandalkan unique index (village_id, tanggal,
        // session_hash) untuk menegakkan dedup. Ini bebas kondisi balapan,
        // berbeda dari pola "cek dulu lalu simpan".
        $tersimpan = DB::table('visitor_logs')->insertOrIgnore([
            'village_id' => $villageId,
            'tanggal' => $tanggal->toDateString(),
            'session_hash' => $hash,
            'halaman' => mb_substr($request->path(), 0, 255),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Hanya kunjungan unik yang menambah agregat harian.
        if ($tersimpan > 0) {
            $this->tambahRingkasanHarian($villageId, $tanggal);
            Cache::forget($this->kunciCache($villageId));
        }
    }

    /**
     * Ringkasan yang tampil di widget publik — PRD 6.16 menyebut 7 kategori
     * persis: Hari Ini, Kemarin, Minggu Ini, Minggu Lalu, Bulan Ini, Bulan
     * Lalu, dan Total.
     *
     * @return array<string, int>
     */
    public function ringkasan(?int $villageId = null): array
    {
        return Cache::remember(
            $this->kunciCache($villageId),
            self::TTL_STATISTIK,
            function () use ($villageId) {
                $hariIni = today();

                // Pekan dianggap mulai Senin, mengikuti konvensi umum di Indonesia.
                $awalMingguIni = $hariIni->copy()->startOfWeek(Carbon::MONDAY);
                $awalMingguLalu = $awalMingguIni->copy()->subWeek();
                $awalBulanIni = $hariIni->copy()->startOfMonth();
                $awalBulanLalu = $awalBulanIni->copy()->subMonth();

                return [
                    'hari_ini' => $this->jumlahAntara($villageId, $hariIni, $hariIni),
                    'kemarin' => $this->jumlahAntara(
                        $villageId, $hariIni->copy()->subDay(), $hariIni->copy()->subDay()
                    ),
                    'minggu_ini' => $this->jumlahAntara($villageId, $awalMingguIni, $hariIni),
                    'minggu_lalu' => $this->jumlahAntara(
                        $villageId, $awalMingguLalu, $awalMingguIni->copy()->subDay()
                    ),
                    'bulan_ini' => $this->jumlahAntara($villageId, $awalBulanIni, $hariIni),
                    'bulan_lalu' => $this->jumlahAntara(
                        $villageId, $awalBulanLalu, $awalBulanIni->copy()->subDay()
                    ),
                    'total' => (int) VisitorDailySummary::query()
                        ->when($villageId, fn ($q) => $q->where('village_id', $villageId))
                        ->sum('jumlah_unique_visit'),
                ];
            }
        );
    }

    private function jumlahAntara(?int $villageId, Carbon $dari, Carbon $sampai): int
    {
        return (int) VisitorDailySummary::query()
            ->when($villageId, fn ($q) => $q->where('village_id', $villageId))
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->sum('jumlah_unique_visit');
    }

    private function tambahRingkasanHarian(int $villageId, Carbon $tanggal): void
    {
        // Satu pernyataan INSERT ... ON DUPLICATE KEY UPDATE: penambahan
        // dilakukan oleh database sendiri, sehingga aman terhadap beberapa
        // request yang tiba bersamaan tanpa perlu transaksi atau kunci.
        DB::table('visitor_daily_summary')->upsert(
            [[
                'village_id' => $villageId,
                'tanggal' => $tanggal->toDateString(),
                'jumlah_unique_visit' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['village_id', 'tanggal'],
            [
                'jumlah_unique_visit' => DB::raw('jumlah_unique_visit + 1'),
                'updated_at' => now(),
            ]
        );
    }

    private function sessionHash(Request $request, Carbon $tanggal): string
    {
        return hash_hmac(
            'sha256',
            $request->ip().'|'.$request->userAgent().'|'.$tanggal->toDateString(),
            config('app.key')
        );
    }

    private function kunciCache(?int $villageId): string
    {
        return 'visitor_stats:'.($villageId ?? 'global');
    }
}
