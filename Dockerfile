# syntax=docker/dockerfile:1
#
# Citra produksi Website Profil Desa Mpanau (Laravel 12 + Inertia + React 19).
#
# Satu kontainer menjalankan tiga proses lewat supervisor: nginx, php-fpm, dan
# `queue:work`. Antrean digabung ke sini — bukan layanan Railway terpisah —
# karena satu-satunya pekerjaan terantre adalah notifikasi Telegram Surat
# Pengantar (app/Jobs/KirimNotifikasiSurat.php); memisahkannya berarti membayar
# satu layanan penuh untuk beban yang nyaris nol.

# ---------------------------------------------------------------------------
# Tahap 1 — aset frontend (TypeScript + Vite)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS aset

WORKDIR /app

# Nama situs ikut ter-bundel ke dalam JS (resources/js/app.tsx), jadi ia harus
# ada saat build, bukan saat runtime.
ARG VITE_APP_NAME="Website Profil Desa Digital"
# Dikosongkan dengan sengaja: dua layar CMS (Penduduk, Pengaduan) menyusun
# tautan unduhan dari VITE_API_URL. Nilai kosong membuat tautannya relatif
# terhadap origin, sehingga tetap benar di domain mana pun. Bila variabel ini
# TIDAK didefinisikan sama sekali, kode jatuh ke 'http://localhost:8000' dan
# unduhan rusak di produksi.
ARG VITE_API_URL=""

COPY package.json package-lock.json ./
RUN npm ci

COPY tsconfig.json tsconfig.app.json tsconfig.node.json vite.config.ts ./
COPY resources ./resources

# Ditulis sebagai berkas .env agar Vite membacanya lewat jalur yang sama
# seperti di mesin pengembang — tidak bergantung pada perilaku process.env.
RUN printf 'VITE_APP_NAME="%s"\nVITE_API_URL=%s\n' "$VITE_APP_NAME" "$VITE_API_URL" > .env \
    && npm run build

# ---------------------------------------------------------------------------
# Tahap 2 — runtime PHP
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS app

# Ekstensi wajib menurut composer.json & paket yang dipakai:
#   gd + exif  -> MediaService (konversi WebP, orientasi foto)
#   zip        -> maatwebsite/excel (impor/ekspor Kependudukan)
#   pdo_mysql  -> basis data
#   bcmath     -> perhitungan APBDes
#   intl       -> pemformatan angka & tanggal lokal id-ID
#   opcache    -> wajib untuk performa PHP di produksi
#   pcntl      -> sinyal berhenti yang bersih untuk queue:work
RUN apk add --no-cache \
        nginx supervisor bash tzdata fcgi \
        icu-libs libzip libpng libjpeg-turbo libwebp freetype oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev \
        libwebp-dev freetype-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath exif gd intl opcache pcntl pdo_mysql zip \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependensi PHP dipasang lebih dulu dan terpisah dari kode aplikasi supaya
# lapisan ini tetap ter-cache selama composer.lock tidak berubah.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress

COPY . .
COPY --from=aset /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --no-interaction

COPY docker/php.ini /usr/local/etc/php/conf.d/99-desa.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p /run/nginx /var/lib/nginx/tmp /var/log/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx \
                                  /var/www/html/storage /var/www/html/bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8080

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
