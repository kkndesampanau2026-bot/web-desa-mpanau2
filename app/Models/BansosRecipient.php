<?php

namespace App\Models;

use App\Services\PiiCipher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penerima bantuan sosial — PRD 6.6.
 *
 * Seperti pada Resident, enkripsi dan blind index ditangani model — bukan
 * controller — supaya data yang masuk lewat jalur mana pun (form, seeder,
 * tinker) selalu punya hash yang konsisten.
 *
 * Bedanya, di sini ada SATU hash tambahan: `nik4_hash` atas 4 digit terakhir
 * NIK, yang menjadi dasar pencarian mandiri oleh warga.
 */
class BansosRecipient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'village_id',
        'bansos_type_id',
        'dusun_id',
        'nama',
        'nik',
        'no_kk',
        'tahun_anggaran',
        'status',
        'nominal',
        'nominal_publik',
        'keterangan',
    ];

    /** Kolom PII tidak pernah ikut terserialisasi. */
    protected $hidden = ['nik', 'no_kk', 'nik_hash', 'nik4_hash'];

    protected function casts(): array
    {
        return [
            'nik' => 'encrypted',
            'no_kk' => 'encrypted',
            'tahun_anggaran' => 'integer',
            'nominal' => 'decimal:2',
            'nominal_publik' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BansosRecipient $penerima) {
            $cipher = app(PiiCipher::class);

            if ($penerima->isDirty('nik')) {
                $penerima->nik_hash = $cipher->hash($penerima->nik);
                $penerima->nik4_hash = $cipher->hashEmpatDigitTerakhir($penerima->nik);
            }
        });
    }

    public function jenisBantuan(): BelongsTo
    {
        return $this->belongsTo(BansosType::class, 'bansos_type_id');
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    public function nikTersamar(): ?string
    {
        return app(PiiCipher::class)->samarkan($this->nik);
    }

    /**
     * Pencocokan untuk pencarian publik — PRD 6.6.
     *
     * Nama dicocokkan secara persis (setelah normalisasi spasi & kapitalisasi)
     * DAN 4 digit akhir NIK harus cocok. Keduanya wajib: nama saja terlalu
     * mudah ditebak, 4 digit saja hanya 10.000 kemungkinan.
     */
    public function scopeCocokPencarianPublik(
        Builder $query,
        string $nama,
        string $empatDigit,
    ): Builder {
        $cipher = app(PiiCipher::class);

        return $query
            // Perbandingan case-insensitive ditangani collation kolom;
            // yang dinormalkan di sini hanyalah spasi berlebih.
            ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower(trim(preg_replace('/\s+/', ' ', $nama)))])
            ->where('nik4_hash', $cipher->hashEmpatDigitTerakhir($empatDigit))
            ->where('status', 'aktif');
    }
}
