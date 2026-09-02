<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master dusun/RW — PRD 8.2.
 */
class Dusun extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id',
        'nama',
        'nama_kepala_dusun',
        'urutan_tampil',
    ];

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * Sebutan dusun tanpa kata "Dusun" di depannya.
     *
     * Blangko surat sudah memuat kata itu pada kalimatnya sendiri — "DUSUN/RW
     * ….", "Ketua Dusun ….", "…/DUSUN ….". Bila nama yang tersimpan juga
     * berbunyi "Dusun 4", hasilnya terbaca "Ketua Dusun Dusun 4".
     *
     * Awalannya dipangkas di sini, bukan dengan memaksa desa menyimpan nama
     * tanpa kata "Dusun": nama itu juga tampil apa adanya pada halaman
     * Infografis dan Peta, di mana justru kata itulah yang memperjelas.
     */
    public function sebutanRingkas(): string
    {
        return trim(preg_replace('/^(dusun|dsn\.?)\s+/i', '', (string) $this->nama) ?? '')
            ?: (string) $this->nama;
    }

    /** RT yang berada di bawah dusun ini. */
    public function rts(): HasMany
    {
        return $this->hasMany(Rt::class);
    }

    public function letterOfficials(): HasMany
    {
        return $this->hasMany(LetterOfficial::class);
    }

    /**
     * Kepala Dusun yang sedang menjabat menurut master pejabat surat.
     *
     * Berbeda dari kolom `nama_kepala_dusun` yang hanya teks tampilan: yang
     * ini membawa serta chat ID Telegram dan tanda tangannya, sehingga inilah
     * yang dipakai alur persetujuan.
     */
    public function kepalaDusun(): ?LetterOfficial
    {
        return $this->letterOfficials()
            ->where('role', LetterOfficial::ROLE_KEPALA_DUSUN)
            ->where('is_active', true)
            ->first();
    }
}
