<?php

namespace App\Models;

use App\Services\PiiCipher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengajuan Surat Pengantar RT/Dusun.
 *
 * Statusnya sengaja ditulis HURUF BESAR dan berbahasa mesin
 * (MENUNGGU_APPROVAL_RT), bukan label ramah warga. Label untuk warga hidup di
 * sisi React (`Pages/Publik/Surat/status.ts`), sehingga kalimatnya dapat
 * diperhalus kapan saja tanpa menyentuh basis data — dan tanpa risiko satu
 * baris lama tertinggal memakai kata yang sudah tidak dipakai lagi.
 */
class LetterRequest extends Model
{
    public const MENUNGGU_RT = 'MENUNGGU_APPROVAL_RT';

    public const MENUNGGU_KADUS = 'MENUNGGU_APPROVAL_KADUS';

    public const DISETUJUI = 'DISETUJUI';

    public const DITOLAK = 'DITOLAK';

    public const STATUS = [
        self::MENUNGGU_RT,
        self::MENUNGGU_KADUS,
        self::DISETUJUI,
        self::DITOLAK,
    ];

    /** Pilihan formulir — sepadan dengan blangko surat desa. */
    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Lainnya'];

    public const STATUS_PERKAWINAN = ['Menikah', 'Janda', 'Duda', 'Lajang'];

    public const WARGA_NEGARA = ['WNI', 'WNA'];

    protected $fillable = [
        'village_id', 'ticket_number', 'uuid',
        'nik', 'nik_hash', 'nama', 'tempat_lahir', 'tanggal_lahir',
        'pekerjaan', 'agama', 'status_perkawinan', 'warga_negara',
        'alamat', 'maksud_keperluan',
        'rt_id', 'dusun_id', 'status',
        'approved_rt_by', 'approved_rt_at',
        'approved_kadus_by', 'approved_kadus_at',
        'rejection_reason', 'rejected_by', 'rejected_by_official_id', 'rejected_at',
        'pdf_path', 'nomor_surat', 'nomor_urut', 'ip_hash',
    ];

    /**
     * NIK, blind index, path berkas, dan hash IP tidak pernah ikut
     * terserialisasi. Halaman publik hanya menerima bentuk tersamar yang
     * disusun controller secara eksplisit.
     *
     * @var list<string>
     */
    protected $hidden = ['nik', 'nik_hash', 'ip_hash', 'pdf_path'];

    protected function casts(): array
    {
        return [
            // Enkripsi at-rest, sepola dengan `residents` (UU PDP).
            'nik' => 'encrypted',
            'tanggal_lahir' => 'date',
            'approved_rt_at' => 'datetime',
            'approved_kadus_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    public function penyetujuRt(): BelongsTo
    {
        return $this->belongsTo(LetterOfficial::class, 'approved_rt_by');
    }

    public function penyetujuKadus(): BelongsTo
    {
        return $this->belongsTo(LetterOfficial::class, 'approved_kadus_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LetterApprovalLog::class);
    }

    public function selesai(): bool
    {
        return in_array($this->status, [self::DISETUJUI, self::DITOLAK], true);
    }

    /** NIK tersamar untuk ditampilkan: 727103********13. */
    public function nikTersamar(): string
    {
        return app(PiiCipher::class)->samarkan($this->nik) ?? '';
    }
}
