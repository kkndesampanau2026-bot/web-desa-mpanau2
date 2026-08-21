<?php

namespace App\Models;

use App\Services\PiiCipher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permohonan informasi publik — PRD 6.14.
 *
 * Alur status mengikuti UU No. 14/2008:
 *   diajukan → diverifikasi → diproses → selesai
 *                                      ↘ ditolak (alasan wajib diisi)
 */
class PpidRequest extends Model
{
    /** Urutan status yang sah; dipakai untuk mencegah lompatan mundur. */
    public const ALUR_STATUS = ['diajukan', 'diverifikasi', 'diproses', 'selesai'];

    public const STATUS = [...self::ALUR_STATUS, 'ditolak'];

    protected $fillable = [
        'village_id',
        'nomor_registrasi',
        'nama_pemohon',
        'no_identitas',
        'kontak',
        'alamat',
        'informasi_diminta',
        'tujuan_penggunaan',
        'cara_memperoleh',
        'status',
        'tanggapan_admin',
        'alasan_penolakan',
        'dokumen_balasan',
        'ditangani_oleh',
        'tanggal_tanggapan',
    ];

    /** Nomor identitas pemohon tidak pernah ikut terserialisasi. */
    protected $hidden = ['no_identitas', 'no_identitas_hash'];

    protected function casts(): array
    {
        return [
            'no_identitas' => 'encrypted',
            'tanggal_tanggapan' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PpidRequest $permohonan) {
            if ($permohonan->isDirty('no_identitas')) {
                $permohonan->no_identitas_hash = app(PiiCipher::class)
                    ->hash($permohonan->no_identitas);
            }
        });
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    /**
     * Membuat nomor registrasi — format PPID-YYYYMMDD-XXXX.
     *
     * Bagian acak dipakai alih-alih nomor urut, supaya seseorang tidak dapat
     * menebak nomor registrasi milik pemohon lain lalu membaca status
     * permohonannya lewat halaman pelacakan publik.
     */
    public static function buatNomorRegistrasi(): string
    {
        do {
            $nomor = sprintf('PPID-%s-%04d', now()->format('Ymd'), random_int(0, 9999));
        } while (self::where('nomor_registrasi', $nomor)->exists());

        return $nomor;
    }
}
