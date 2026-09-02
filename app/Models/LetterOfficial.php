<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pejabat penanda tangan Surat Pengantar — Ketua RT & Kepala Dusun.
 *
 * Kelas ini memegang dua kunci sekaligus: `telegram_chat_id` yang menentukan
 * SIAPA yang boleh menyetujui, dan `signature_path` yang menentukan tanda
 * tangan siapa yang dibubuhkan. Keduanya tidak pernah ditulis di kode —
 * seluruhnya berasal dari basis data dan dikelola lewat /admin/surat.
 */
class LetterOfficial extends Model
{
    public const ROLE_KETUA_RT = 'KETUA_RT';

    public const ROLE_KEPALA_DUSUN = 'KEPALA_DUSUN';

    public const ROLES = [self::ROLE_KETUA_RT, self::ROLE_KEPALA_DUSUN];

    protected $fillable = [
        'village_id',
        'role',
        'rt_id',
        'dusun_id',
        'nama',
        'jabatan_teks',
        'telegram_chat_id',
        'signature_path',
        'is_active',
    ];

    /**
     * Chat ID & path tanda tangan tidak pernah ikut terserialisasi tanpa
     * disengaja. Keduanya hanya dikeluarkan lewat endpoint admin yang
     * berpermission, tidak pernah ke halaman publik.
     *
     * @var list<string>
     */
    protected $hidden = ['telegram_chat_id', 'signature_path'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Sebutan yang tercetak pada blok tanda tangan surat. */
    public function sebutan(): string
    {
        if (filled($this->jabatan_teks)) {
            return $this->jabatan_teks;
        }

        return $this->role === self::ROLE_KETUA_RT
            ? 'Ketua RT '.($this->rt?->nomor ?? '')
            : 'Ketua Dusun '.($this->dusun?->sebutanRingkas() ?? '');
    }

    /**
     * Apakah pejabat ini berwenang atas pengajuan tertentu.
     *
     * Dipakai sebagai lapis terakhir otorisasi callback Telegram: cocoknya
     * chat ID saja tidak cukup, wilayahnya pun harus sesuai — kalau tidak,
     * Ketua RT 01 dapat menyetujui surat milik warga RT 20 hanya dengan
     * meneruskan tombolnya.
     */
    public function berwenangAtas(LetterRequest $permohonan): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return match ($this->role) {
            self::ROLE_KETUA_RT => $this->rt_id !== null
                && $this->rt_id === $permohonan->rt_id,
            self::ROLE_KEPALA_DUSUN => $this->dusun_id !== null
                && $this->dusun_id === $permohonan->dusun_id,
            default => false,
        };
    }
}
