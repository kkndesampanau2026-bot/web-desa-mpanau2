<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Penulis audit trail — PRD 12.2.
 *
 * Dipakai oleh seluruh modul yang menyentuh data sensitif (residents,
 * bansos_recipients, APBDes-realisasi) serta peristiwa autentikasi.
 *
 * Dua hal yang dijaga di sini:
 *  1. Kolom PII (NIK, No. KK, dsb.) tidak pernah ikut tersimpan mentah —
 *     lihat REDACTED_KEYS. Tanpa penapisan ini, audit trail justru menjadi
 *     tabel bayangan berisi data pribadi yang tidak terenkripsi.
 *  2. Alamat IP disimpan ter-hash, konsisten dengan pola session_hash pada
 *     visitor_logs (PRD 6.16).
 */
class ActivityLogger
{
    /**
     * Kunci yang nilainya diganti '[REDACTED]' sebelum masuk log.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'nik',
        'nik_hash',
        'no_kk',
        'no_kk_hash',
        'password',
        'password_confirmation',
        'remember_token',
        'no_identitas',
    ];

    public function log(
        string $aksi,
        ?Model $subjek = null,
        ?string $deskripsi = null,
        ?array $dataSebelum = null,
        ?array $dataSesudah = null,
        ?Model $pelaku = null,
    ): ActivityLog {
        $user = Auth::user();
        $request = request();

        return ActivityLog::create([
            'village_id' => $user->village_id ?? $pelaku?->village_id ?? null,
            'user_id' => $user?->id,
            'user_nama' => $user?->name ?? $pelaku?->nama,
            'user_email' => $user?->email,
            // Pelaku non-sesi: lihat logSebagai().
            'pelaku_tipe' => $pelaku ? $pelaku::class : null,
            'pelaku_id' => $pelaku?->getKey(),
            'aksi' => $aksi,
            'subjek_tipe' => $subjek ? $subjek::class : null,
            'subjek_id' => $subjek?->getKey(),
            'deskripsi' => $deskripsi,
            'data_sebelum' => $dataSebelum ? $this->redact($dataSebelum) : null,
            'data_sesudah' => $dataSesudah ? $this->redact($dataSesudah) : null,
            'ip_hash' => $this->hashIp($request),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Mencatat aksi yang pelakunya BUKAN operator bersesi.
     *
     * Sampai kini setiap baris audit berasal dari operator yang login di CMS,
     * sehingga `Auth::user()` selalu cukup. Persetujuan Surat Pengantar
     * memutus asumsi itu: pelakunya Ketua RT atau Kepala Dusun yang menekan
     * tombol di Telegram dan memang tidak punya akun CMS.
     *
     * Dipisahkan dari log() agar pemanggilannya terbaca jelas — bukan aksi
     * anonim, melainkan aksi milik pejabat yang identitasnya sudah
     * diverifikasi lewat chat ID.
     */
    public function logSebagai(
        Model $pelaku,
        string $aksi,
        ?Model $subjek = null,
        ?string $deskripsi = null,
    ): ActivityLog {
        return $this->log($aksi, $subjek, $deskripsi, pelaku: $pelaku);
    }

    /**
     * Mencatat pembacaan data sensitif. Dipisahkan dari log() agar pemanggilan
     * di controller terbaca jelas sebagai jejak AKSES, bukan perubahan —
     * UU PDP menuntut keduanya tercatat.
     */
    public function logAccess(Model $subjek, string $deskripsi): ActivityLog
    {
        return $this->log('viewed', $subjek, $deskripsi);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    private function hashIp(?Request $request): ?string
    {
        $ip = $request?->ip();

        return $ip ? hash_hmac('sha256', $ip, config('app.key')) : null;
    }
}
