<?php

namespace App\Models;

use App\Services\PiiCipher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Data individu penduduk — PRD 6.3.
 *
 * Model ini memikul dua tanggung jawab yang tidak boleh bocor ke pemanggilnya:
 *
 *  1. NIK & No. KK dienkripsi otomatis lewat `encrypted` cast.
 *  2. Blind index (nik_hash, no_kk_hash) diperbarui otomatis pada event
 *     `saving`. Menaruhnya di model — bukan di controller — memastikan hash
 *     selalu sinkron, termasuk saat data masuk lewat impor CSV, seeder, atau
 *     tinker. Bila ini diserahkan ke controller, satu jalur yang lupa
 *     memanggilnya akan menghasilkan baris yang tak pernah bisa ditemukan.
 */
class Resident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'village_id',
        'dusun_id',
        'nik',
        'no_kk',
        'nama',
        'jenis_kelamin',
        'tanggal_lahir',
        'status_hubungan_kk',
        'pendidikan_terakhir',
        'pekerjaan',
        'status_perkawinan',
        'agama',
        'status_domisili',
        'status_wajib_pilih',
        'status_mutasi',
        'tanggal_mutasi',
    ];

    /**
     * Kolom hash tidak pernah dikirim ke klien: ia setara sidik jari NIK dan
     * bila bocor memungkinkan pencocokan lintas basis data.
     *
     * @var list<string>
     */
    protected $hidden = ['nik', 'no_kk', 'nik_hash', 'no_kk_hash'];

    protected function casts(): array
    {
        return [
            // Enkripsi at-rest sesuai UU PDP (PRD 12.2).
            'nik' => 'encrypted',
            'no_kk' => 'encrypted',
            'tanggal_lahir' => 'date',
            'tanggal_mutasi' => 'date',
            'status_wajib_pilih' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Resident $penduduk) {
            $cipher = app(PiiCipher::class);

            // Cast `encrypted` mengenkripsi saat atribut diisi dan mendekripsi
            // saat dibaca, sehingga $penduduk->nik di sini sudah berupa teks
            // asli — itulah yang harus di-hash, bukan ciphertext-nya.
            //
            // Catatan: karena enkripsi Laravel non-deterministik, isDirty()
            // ikut bernilai true walau nilainya sebenarnya sama. Dampaknya
            // hanya hash dihitung ulang dengan hasil identik — tidak merusak.
            if ($penduduk->isDirty('nik')) {
                $penduduk->nik_hash = $cipher->hash($penduduk->nik);
            }

            if ($penduduk->isDirty('no_kk')) {
                $penduduk->no_kk_hash = $cipher->hash($penduduk->no_kk);
            }

            // Wajib pilih dihitung ulang setiap kali data yang mempengaruhinya
            // berubah — kecuali admin menyetelnya manual pada operasi ini.
            if (
                ! $penduduk->isDirty('status_wajib_pilih')
                && ($penduduk->isDirty('tanggal_lahir') || $penduduk->isDirty('status_perkawinan'))
            ) {
                $penduduk->status_wajib_pilih = $penduduk->hitungWajibPilih();
            }
        });
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    /**
     * Kriteria wajib pilih (PRD 6.3): berusia ≥17 tahun ATAU sudah/pernah
     * kawin. Syarat kedua penting — warga di bawah 17 tahun yang sudah menikah
     * tetap masuk daftar pemilih.
     */
    public function hitungWajibPilih(): bool
    {
        $sudahKawin = in_array(
            $this->status_perkawinan,
            ['Kawin', 'Cerai Hidup', 'Cerai Mati'],
            true
        );

        if ($sudahKawin) {
            return true;
        }

        return $this->tanggal_lahir !== null
            && $this->tanggal_lahir->diffInYears(now()) >= 17;
    }

    /** NIK tersamar untuk ditampilkan kepada petugas berwenang. */
    public function nikTersamar(): ?string
    {
        return app(PiiCipher::class)->samarkan($this->nik);
    }

    /**
     * Mencari berdasarkan NIK lewat blind index.
     *
     * Inilah alasan kolom hash ada: tanpanya, pencarian satu NIK menuntut
     * mendekripsi seluruh baris tabel.
     */
    public function scopeCariNik(Builder $query, string $nik): Builder
    {
        return $query->where('nik_hash', app(PiiCipher::class)->hash($nik));
    }
}
