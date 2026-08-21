<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Pengaduan masyarakat — PRD 6.15. */
class Complaint extends Model
{
    use SoftDeletes;

    /** Enam kategori persis seperti situs referensi (PRD 6.15). */
    public const KATEGORI = [
        'Umum', 'Sosial', 'Keamanan', 'Kesehatan', 'Kebersihan', 'Permintaan',
    ];

    public const STATUS = ['baru', 'diproses', 'selesai', 'ditolak'];

    protected $fillable = [
        'village_id', 'nomor_tiket', 'nama', 'no_telepon_wa',
        'kategori_pengaduan', 'isi_pengaduan', 'status',
        'tanggapan_admin', 'alasan_penolakan', 'ditangani_oleh',
        'tanggal_tanggapan', 'ip_hash',
    ];

    /**
     * Kontak pelapor & hash IP tidak pernah ikut terserialisasi secara tidak
     * sengaja; keduanya hanya dikeluarkan lewat method admin yang eksplisit.
     *
     * @var list<string>
     */
    protected $hidden = ['ip_hash'];

    protected function casts(): array
    {
        return ['tanggal_tanggapan' => 'datetime'];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    /**
     * Nomor tiket PGD-YYYYMMDD-XXXX.
     *
     * Bagian akhir dibuat ACAK, bukan nomor urut, karena nomor tiket adalah
     * satu-satunya kunci menuju halaman pelacakan — nomor berurutan akan
     * memungkinkan siapa pun membaca pengaduan warga lain dengan menebak.
     */
    public static function buatNomorTiket(): string
    {
        do {
            $nomor = 'PGD-'.now()->format('Ymd').'-'.str_pad(
                (string) random_int(0, 9999), 4, '0', STR_PAD_LEFT
            );
        } while (self::withTrashed()->where('nomor_tiket', $nomor)->exists());

        return $nomor;
    }

    /** Nama pelapor tersamar, untuk keperluan tampilan ringkas. */
    public function namaTersamar(): string
    {
        $kata = preg_split('/\s+/', trim($this->nama)) ?: [];

        return collect($kata)
            ->map(fn (string $k, int $i) => $i === 0
                ? $k
                : mb_substr($k, 0, 1).str_repeat('*', max(mb_strlen($k) - 1, 1)))
            ->implode(' ');
    }
}
