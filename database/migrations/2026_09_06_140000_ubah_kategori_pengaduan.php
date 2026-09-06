<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kategori pengaduan diganti mengikuti pembagian urusan pemerintahan desa.
 *
 * Dari enam kategori bergaya situs referensi (Umum, Sosial, Keamanan,
 * Kesehatan, Kebersihan, Permintaan) menjadi enam bidang baku: Pendidikan,
 * Kesehatan, Pekerjaan Umum, Perumahan, Trantibum, dan Sosial.
 *
 * Kolomnya ENUM, bukan string. Mengganti daftar di `Complaint::KATEGORI` saja
 * tidak cukup: MySQL akan menolak setiap pengaduan baru karena nilainya tidak
 * terdaftar pada definisi kolom — kegagalan yang hanya muncul saat warga
 * pertama menekan "Kirim".
 *
 * Dikerjakan tiga langkah agar tidak ada baris yang kehilangan nilainya:
 * kolom dilonggarkan dulu menjadi VARCHAR, isinya dipetakan, baru dikunci
 * kembali sebagai ENUM berisi daftar baru. Mengubahnya sekali jalan akan
 * membuat MySQL mengosongkan setiap baris yang nilainya tidak lagi sah.
 *
 * VARCHAR — bukan ENUM gabungan kedua daftar — karena MySQL membandingkan
 * nilai enum MENGIKUTI COLLATION kolom, dan pada utf8mb4_unicode_ci
 * 'Sosial' dan 'SOSIAL' terbaca sebagai nilai kembar. Enum gabungan itu
 * ditolak dengan galat 1291 sebelum satu baris pun sempat dipetakan.
 */
return new class extends Migration
{
    private const LAMA = ['Umum', 'Sosial', 'Keamanan', 'Kesehatan', 'Kebersihan', 'Permintaan'];

    private const BARU = ['PENDIDIKAN', 'KESEHATAN', 'PEK. UMUM', 'PERUMAHAN', 'TRANTIBUM', 'SOSIAL'];

    /**
     * Pemetaan kategori lama ke padanan terdekatnya.
     *
     * "Umum" dan "Permintaan" tidak punya padanan pada daftar baru — keduanya
     * kategori serba-guna, dan SOSIAL adalah bidang terluas yang tersisa.
     * Pemetaan ini menulis ulang kategori yang dipilih warga, jadi ia memang
     * perkiraan; yang dijaga hanyalah agar tidak ada pengaduan yang kehilangan
     * kategorinya sama sekali.
     *
     * @var array<string, string>
     */
    private const PETA = [
        'Umum' => 'SOSIAL',
        'Sosial' => 'SOSIAL',
        'Permintaan' => 'SOSIAL',
        'Keamanan' => 'TRANTIBUM',
        'Kesehatan' => 'KESEHATAN',
        'Kebersihan' => 'PEK. UMUM',
    ];

    public function up(): void
    {
        $this->longgarkan();

        foreach (self::PETA as $lama => $baru) {
            DB::table('complaints')
                ->where('kategori_pengaduan', $lama)
                ->update(['kategori_pengaduan' => $baru]);
        }

        $this->ubahEnum(self::BARU);
    }

    public function down(): void
    {
        $this->longgarkan();

        // Pembalikan bersifat perkiraan: tiga kategori lama meleburkan diri
        // menjadi SOSIAL pada `up()`, dan asal-usulnya tidak disimpan.
        $balik = [
            'SOSIAL' => 'Sosial',
            'TRANTIBUM' => 'Keamanan',
            'KESEHATAN' => 'Kesehatan',
            'PEK. UMUM' => 'Kebersihan',
            'PENDIDIKAN' => 'Umum',
            'PERUMAHAN' => 'Umum',
        ];

        foreach ($balik as $baru => $lama) {
            DB::table('complaints')
                ->where('kategori_pengaduan', $baru)
                ->update(['kategori_pengaduan' => $lama]);
        }

        $this->ubahEnum(self::LAMA);
    }

    /** Melepas batasan enum sementara, agar nilai lama & baru sama-sama sah. */
    private function longgarkan(): void
    {
        DB::statement(
            'ALTER TABLE `complaints` MODIFY `kategori_pengaduan` VARCHAR(50) NOT NULL'
        );
    }

    /**
     * @param  list<string>  $nilai
     */
    private function ubahEnum(array $nilai): void
    {
        // Nilai dikutip lewat PDO, bukan disisipkan mentah: salah satunya
        // memuat titik dan spasi ("PEK. UMUM"), dan daftar enum adalah bagian
        // DDL yang tidak menerima parameter terikat.
        $daftar = collect($nilai)
            ->unique()
            ->map(fn (string $v) => DB::getPdo()->quote($v))
            ->implode(', ');

        DB::statement(
            "ALTER TABLE `complaints` MODIFY `kategori_pengaduan` ENUM({$daftar}) NOT NULL"
        );
    }
};
