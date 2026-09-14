<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aparat Pemerintah Desa — PRD 6.2.
 */
class Official extends Model
{
    use HasFactory;

    protected $fillable = [
        'village_id',
        'nama',
        'jabatan',
        'foto',
        'no_sk_pengangkatan',
        'periode_mulai',
        'periode_selesai',
        'urutan_tampil',
        'tingkat',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'status_aktif' => 'boolean',
            'urutan_tampil' => 'integer',
            'tingkat' => 'integer',
        ];
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * Jenjang jabatan aparat desa, dari puncak ke bawah.
     *
     * Menggantikan kolom `urutan_tampil` & `tingkat` yang dulu diisi tangan
     * pada setiap baris. Keduanya dihapus dari formulir CMS atas permintaan
     * pemilik produk (docs/DEVIASI.md §C22): mengisi dua angka demi menyusun
     * enam nama adalah pekerjaan yang seharusnya tidak pernah ada, dan satu
     * angka yang terlewat membuat Kepala Desa tampil di tengah daftar.
     *
     * Kolom `jabatan` berupa teks bebas, jadi pencocokannya memakai pola awalan
     * — "Kaur Keuangan" dan "Kaur Umum" sama-sama masuk jenjang yang sama.
     * Jabatan yang tidak dikenali jatuh ke jenjang terakhir, bukan hilang.
     *
     * @var array<int, list<string>>
     */
    private const JENJANG = [
        ['Kepala Desa%', 'Penjabat Kepala Desa%', 'Pj%Kepala Desa%'],
        ['Sekretaris%'],
        ['Kaur%', 'Kepala Urusan%'],
        ['Kasi%', 'Kepala Seksi%'],
        ['Kepala Dusun%', 'Kadus%'],
        ['Staf%', 'Pelaksana%'],
    ];

    /**
     * Urutan tampil baku: jenjang jabatan, lalu jabatan, lalu nama.
     *
     * Dipakai halaman publik MAUPUN daftar di CMS, supaya yang dilihat operator
     * sama persis dengan yang dilihat warga.
     */
    public function scopeUrut(Builder $query): Builder
    {
        $kasus = '';
        $ikatan = [];

        foreach (self::JENJANG as $tingkat => $polaJabatan) {
            foreach ($polaJabatan as $pola) {
                // `$tingkat` bilangan bulat dari kode, bukan masukan pengguna.
                $kasus .= " WHEN jabatan LIKE ? THEN {$tingkat}";
                $ikatan[] = $pola;
            }
        }

        return $query
            ->orderByRaw('CASE'.$kasus.' ELSE '.count(self::JENJANG).' END', $ikatan)
            ->orderBy('jabatan')
            ->orderBy('nama');
    }

    /** Yang tampil di halaman publik: hanya aparat aktif. */
    public function scopeTampil(Builder $query): Builder
    {
        return $query->where('status_aktif', true)->urut();
    }
}
