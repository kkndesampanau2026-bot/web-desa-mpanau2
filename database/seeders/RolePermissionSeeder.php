<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RBAC — PRD 5.2, 7.2, dan 12.2.
 *
 * Permission dikelompokkan per tingkat sensitivitas data, bukan sekadar per
 * modul, karena PRD 12.2 menuntut pemisahan tegas: hanya Admin Utama yang
 * boleh melihat/mengekspor data individu penduduk & penerima bansos,
 * sedangkan Operator Konten dibatasi pada modul non-sensitif.
 *
 * Perhatikan pemisahan `manage-*` dengan `view-*-pii`: seorang operator bisa
 * saja diberi hak mengelola baris data penduduk tanpa otomatis berhak melihat
 * NIK/No. KK utuh. Pemisahan ini yang membuat prinsip minimalisasi data pada
 * UU PDP dapat ditegakkan di tingkat kode, bukan sekadar kebijakan tertulis.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permission dikelompokkan per domain agar mudah ditelusuri balik ke PRD.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSION_GROUPS = [
        // --- Data sensitif (PRD 6.3, 6.6, 12.2) ---
        'kependudukan' => [
            'manage-population-data',   // CRUD & impor CSV residents
            'view-population-pii',      // melihat NIK/No. KK utuh
            'export-population-data',   // ekspor rekap berisi data pribadi
        ],
        'bansos' => [
            'manage-bansos',            // CRUD penerima & master jenis bantuan
            'view-bansos-pii',
        ],

        // --- Keuangan & indeks pembangunan (PRD 6.4, 6.7, 6.8) ---
        'keuangan' => [
            'manage-apbdes',
            'manage-idm',
            'manage-sdgs',
        ],

        // --- Kesehatan (PRD 6.5) ---
        'kesehatan' => [
            'manage-stunting',
        ],

        // --- Profil & pemerintahan (PRD 6.1, 6.2) ---
        'profil' => [
            'manage-village-profile',
            'manage-officials',         // aparat desa & anggota BPD
            'manage-dusun',
        ],

        // --- Konten publik & ekonomi desa (PRD 6.9–6.13) ---
        'konten' => [
            'manage-news',
            'manage-gallery',
            'manage-potential',
            'manage-tourism',
            'manage-product',
            'manage-poi',
        ],

        // --- Keterbukaan informasi publik (PRD 6.14) ---
        'ppid' => [
            'manage-ppid-content',      // dasar hukum, berkala, serta-merta, setiap saat
            'respond-ppid-request',     // menanggapi permohonan informasi
        ],

        // --- Layanan surat warga ---
        // Dipisahkan dari 'pengaduan' karena kewenangannya berbeda: yang
        // mengelola surat memegang master RT, chat ID Telegram, dan tanda
        // tangan pejabat — kunci yang menentukan siapa boleh menyetujui surat
        // atas nama desa. Menggabungkannya dengan hak menanggapi pengaduan
        // akan memberikan kunci itu kepada setiap Operator Konten.
        'surat' => [
            'manage-letter-request',    // pantau pengajuan, buat ulang PDF
            'manage-letter-official',   // master RT, pejabat, chat ID, tanda tangan
        ],

        // --- Layanan pengaduan warga (PRD 6.15) ---
        // Dipisahkan dari grup PPID: PRD 1.3 menugaskan "kelola pengaduan
        // masuk" kepada Operator Konten, sedangkan Operator PPID hanya
        // menangani permohonan informasi. Menggabungkan keduanya akan
        // memberi Operator PPID akses ke kontak pelapor tanpa dasar.
        'pengaduan' => [
            'respond-complaint',
        ],

        // --- Sistem (PRD 5.19–5.21) ---
        'sistem' => [
            'manage-settings',
            'manage-users',
            'view-activity-log',
            'view-dashboard',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Super Admin sengaja tidak diberi permission eksplisit: aksesnya
        // ditangani Gate::before pada AuthServiceProvider agar otomatis
        // mencakup permission baru yang ditambahkan di fase berikutnya.
        Role::findOrCreate('Super Admin', 'web');

        Role::findOrCreate('Admin Utama', 'web')
            ->syncPermissions(self::allPermissions());

        /*
         * Operator Utama — sekuat Admin Utama, KECUALI mengelola akun.
         *
         * Dibuat agar pekerjaan harian desa (mengisi seluruh modul, termasuk
         * data penduduk & bansos) tidak menuntut penyerahan akun Admin Utama.
         * Yang ditahan hanyalah `manage-users`, dan penahanan itu bermakna:
         * siapa pun yang memegangnya dapat membuat akun baru berperan Admin
         * Utama, sehingga memberikannya sama saja dengan memberikan seluruh
         * kewenangan yang tersisa.
         */
        Role::findOrCreate('Operator Utama', 'web')
            ->syncPermissions(self::permissionsOperatorUtama());

        Role::findOrCreate('Operator Konten', 'web')->syncPermissions([
            ...self::PERMISSION_GROUPS['konten'],
            ...self::PERMISSION_GROUPS['pengaduan'],
            'view-dashboard',
        ]);

        Role::findOrCreate('Operator PPID', 'web')->syncPermissions([
            ...self::PERMISSION_GROUPS['ppid'],
            'view-dashboard',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return array_merge(...array_values(self::PERMISSION_GROUPS));
    }

    /**
     * Seluruh permission kecuali pengelolaan akun.
     *
     * Ditulis sebagai selisih dari `allPermissions()`, bukan sebagai daftar
     * tersendiri: permission baru yang ditambahkan pada fase berikutnya harus
     * OTOMATIS ikut ke peran ini. Daftar manual akan diam-diam tertinggal, dan
     * gejalanya membingungkan — modul baru muncul di menu Admin Utama tetapi
     * berbalas 403 bagi Operator Utama.
     *
     * @return list<string>
     */
    public static function permissionsOperatorUtama(): array
    {
        return array_values(array_diff(self::allPermissions(), ['manage-users']));
    }
}
