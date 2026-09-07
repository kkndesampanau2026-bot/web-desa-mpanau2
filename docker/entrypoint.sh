#!/bin/sh
#
# Penyiapan kontainer sebelum nginx/php-fpm/queue dijalankan supervisor.
# Dijalankan ulang pada SETIAP deploy dan restart, jadi setiap langkah di sini
# harus aman dijalankan berkali-kali.

set -e

cd /var/www/html

echo "==> Menyiapkan direktori storage"
# Volume Railway dipasang pada storage/app dan membayangi isi bawaan citra,
# sehingga sub-direktorinya harus dibuat ulang tiap boot.
mkdir -p storage/app/public \
         storage/app/private \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "==> Menyetel port nginx: ${PORT:-8080}"
sed "s/__PORT__/${PORT:-8080}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

if [ -z "${APP_KEY}" ]; then
    echo "GAGAL: APP_KEY belum diisi. Jalankan 'php artisan key:generate --show' lalu simpan hasilnya sebagai variabel APP_KEY di Railway."
    exit 1
fi

if [ -z "${PII_HASH_KEY}" ]; then
    echo "GAGAL: PII_HASH_KEY belum diisi. Modul Kependudukan memerlukannya untuk membuat blind index NIK/No. KK. Buat dengan 'php artisan pii:key'."
    exit 1
fi

echo "==> Menunggu basis data ${DB_HOST}:${DB_PORT:-3306}"
percobaan=0
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) (getenv("DB_PORT") ?: 3306), $e, $s, 3) ? 0 : 1);' 2>/dev/null; do
    percobaan=$((percobaan + 1))
    if [ "$percobaan" -ge 30 ]; then
        echo "GAGAL: basis data tidak merespons setelah 60 detik."
        exit 1
    fi
    sleep 2
done

echo "==> Membangun ulang cache konfigurasi"
# Cache lama dari citra/volume dibuang lebih dulu agar variabel Railway yang
# baru benar-benar terpakai.
php artisan config:clear
php artisan config:cache
# CATATAN: `route:cache` sengaja TIDAK dijalankan. routes/api.php memuat dua
# route yang aksinya berupa closure (/api/v1/health dan /api/v1/admin/ping),
# dan Laravel menolak men-serialisasi closure — perintahnya akan gagal.
php artisan view:cache

echo "==> Menautkan storage publik"
php artisan storage:link --force

echo "==> Menjalankan migrasi"
php artisan migrate --force

# Peran & izin disinkronkan pada SETIAP boot, terlepas dari JALANKAN_SEEDER.
#
# RolePermissionSeeder bersifat idempoten (findOrCreate + syncPermissions) dan
# tidak menyentuh konten maupun akun — isinya lebih dekat ke skema daripada ke
# data. Tanpa langkah ini, peran atau izin baru yang ditambahkan di kode tidak
# akan pernah ada di produksi selama JALANKAN_SEEDER bernilai false, dan
# gejalanya menyesatkan: menu muncul tetapi setiap halamannya berbalas 403.
echo "==> Menyinkronkan peran & izin"
php artisan db:seed --force --class='Database\Seeders\RolePermissionSeeder'

# Kategori berita, dengan alasan yang sama seperti peran & izin: data master
# yang idempoten, tidak menimpa konten apa pun, dan tanpanya modul Berita tidak
# dapat dipakai — operator tidak punya satu pun kategori untuk dipilih, dan
# filter kategori di situs publik tidak memunculkan chip apa pun.
echo "==> Menyinkronkan kategori berita"
php artisan db:seed --force --class='Database\Seeders\KategoriBeritaSeeder'

# Cache aplikasi dikosongkan pada setiap deploy.
#
# Bukan kerapian. Cache memakai driver `database`, jadi isinya BERTAHAN
# melewati deploy — termasuk ketika kode baru mengubah BENTUK data yang
# tersimpan di sana. Itu persis yang terjadi saat banner beranda berubah dari
# satu string menjadi daftar: selama satu jam berikutnya, situs tetap
# menyajikan bentuk lama dari cache, dan gejalanya menyesatkan karena tidak
# ada galat apa pun — fiturnya hanya "belum jalan".
#
# Aman dijalankan: seluruh isi cache adalah data turunan yang dihitung ulang
# dari basis data saat pertama diminta. Satu-satunya yang bukan — penautan
# balasan Telegram — memang berumur pendek dan pulih dengan menekan tombol
# TOLAK sekali lagi (lihat TelegramWebhookController).
#
# Dijalankan SESUDAH migrasi: driver database menghapus isi tabel `cache`,
# yang pada pemasangan baru belum ada sebelum migrasi berjalan.
echo "==> Mengosongkan cache aplikasi"
php artisan cache:clear

if [ "${JALANKAN_SEEDER}" = "true" ]; then
    echo "==> Menjalankan seeder"
    php artisan db:seed --force
fi

# Seeder membuat empat akun operator contoh dan selalu menyetel status_aktif
# menjadi true. Daftar di bawah dimatikan setiap boot supaya akun demo tidak
# pernah bisa dipakai masuk dari internet, bahkan bila seeder terlanjur
# dijalankan ulang.
if [ -n "${AKUN_DEMO_NONAKTIF}" ]; then
    echo "==> Menonaktifkan akun demo: ${AKUN_DEMO_NONAKTIF}"
    php artisan tinker --execute="\App\Models\User::whereIn('email', array_map('trim', explode(',', '${AKUN_DEMO_NONAKTIF}')))->update(['status_aktif' => false]);"
fi

# Webhook Telegram dipasang ulang pada SETIAP boot.
#
# Pemasangannya berupa panggilan HTTP ke Bot API, bukan berkas konfigurasi —
# jadi ia tidak ikut terbawa citra maupun repo, dan tidak pernah terjadi hanya
# karena TELEGRAM_BOT_TOKEN sudah diisi di Railway. Selama langkah ini tidak
# ada, pengajuan surat TETAP sampai ke ponsel Ketua RT (itu panggilan keluar,
# cukup bermodal token) tetapi penekanan APPROVE/TOLAK tidak menuju ke mana
# pun: Telegram tidak tahu alamat mana yang harus dihubungi. Gejalanya persis
# "notifikasinya masuk, tombolnya tidak berfungsi".
#
# Diletakkan di sini karena layanan Railway ini tidak punya shell, sehingga
# perintahnya tidak dapat dijalankan sekali dengan tangan. Efek sampingnya
# menguntungkan: webhook ikut pulih sendiri setiap kali domain berganti.
#
# Alamatnya diambil dari RAILWAY_PUBLIC_DOMAIN, bukan APP_URL — Railway sendiri
# yang menetapkannya, sehingga ia tidak dapat tertinggal salah saat domain
# berubah.
#
# Kegagalannya TIDAK menghentikan boot: seluruh situs tidak boleh gagal menyala
# hanya karena Telegram sedang tidak dapat dihubungi.
if [ -n "${TELEGRAM_BOT_TOKEN}" ]; then
    echo "==> Memasang webhook Telegram"

    if [ -n "${RAILWAY_PUBLIC_DOMAIN}" ]; then
        alamat="--url=https://${RAILWAY_PUBLIC_DOMAIN}"
    else
        alamat=""
    fi

    if ! php artisan surat:telegram-webhook pasang ${alamat}; then
        echo "PERINGATAN: webhook Telegram gagal dipasang. Persetujuan surat lewat bot TIDAK akan berjalan sampai ini berhasil."
    fi

    # Dicetak apa adanya supaya sebab kegagalan berikutnya terbaca langsung
    # dari log deploy: `last_error_message` menyebut persis apa yang ditolak
    # Telegram (mis. rahasia tidak cocok), dan `pending_update_count`
    # menunjukkan penekanan tombol yang menumpuk karena belum pernah sampai.
    php artisan surat:telegram-webhook info || true
else
    echo "==> Webhook Telegram dilewati: TELEGRAM_BOT_TOKEN belum diisi."
fi

echo "==> Aplikasi siap. Menjalankan nginx, php-fpm, dan pekerja antrean."
exec supervisord -c /etc/supervisor/supervisord.conf
