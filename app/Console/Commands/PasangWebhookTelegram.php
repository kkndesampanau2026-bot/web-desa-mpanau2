<?php

namespace App\Console\Commands;

use App\Services\TelegramBot;
use Illuminate\Console\Command;

/**
 * Memasang / memeriksa / melepas webhook bot Telegram.
 *
 * Webhook Telegram dipasang sekali per lingkungan lewat panggilan HTTP ke Bot
 * API, bukan lewat berkas konfigurasi. Tanpa perintah ini, langkah tersebut
 * harus dijalankan dengan curl bertangan — dan token bot ikut tertinggal di
 * riwayat shell.
 *
 * Telegram MENOLAK webhook yang bukan HTTPS, sehingga di lingkungan
 * pengembangan lokal perintah ini perlu dijalankan dengan URL dari terowongan
 * (ngrok, cloudflared) — bukan http://localhost.
 */
class PasangWebhookTelegram extends Command
{
    protected $signature = 'surat:telegram-webhook
                            {aksi=pasang : pasang | info | lepas}
                            {--url= : URL dasar situs, mis. https://desampanau.id}';

    protected $description = 'Memasang, memeriksa, atau melepas webhook bot Telegram Surat Pengantar';

    public function handle(TelegramBot $bot): int
    {
        if (! $bot->aktif()) {
            $this->error('TELEGRAM_BOT_TOKEN belum diatur pada berkas .env.');

            return self::FAILURE;
        }

        return match ($this->argument('aksi')) {
            'pasang' => $this->pasang($bot),
            'info' => $this->info_($bot),
            'lepas' => $this->lepas($bot),
            default => $this->gagalAksi(),
        };
    }

    private function pasang(TelegramBot $bot): int
    {
        $rahasia = (string) config('telegram.webhook_secret');

        if ($rahasia === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET belum diatur.');
            $this->line('Tanpa rahasia ini, alamat webhook menjadi endpoint POST terbuka.');
            $this->line('Buat nilai acak, misalnya dengan: php artisan key:generate --show');

            return self::FAILURE;
        }

        /*
         * Telegram membatasi secret_token pada A-Z a-z 0-9 _ - saja, 1-256
         * karakter. Diperiksa di sini supaya kesalahan terbaca sebagai
         * "rahasia Anda memuat karakter terlarang" — bukan sebagai
         * "Telegram menolak" yang menyisakan tebak-tebakan antara URL,
         * token bot, dan rahasia.
         */
        if (! preg_match('/^[A-Za-z0-9_-]{1,256}$/', $rahasia)) {
            $ilegal = preg_replace('/[A-Za-z0-9_-]/', '', $rahasia);

            $this->error('TELEGRAM_WEBHOOK_SECRET memuat karakter yang tidak diterima Telegram.');
            $this->line('Karakter terlarang yang ditemukan: '
                .implode(' ', array_unique(str_split($ilegal))));
            $this->line('Yang diizinkan hanya huruf, angka, garis bawah, dan tanda hubung.');
            $this->newLine();
            $this->line('Hasil `php artisan key:generate --show` TIDAK dapat dipakai:');
            $this->line('ia memuat ":" dan "=". Buat yang sah dengan:');
            $this->line('  php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"');

            return self::FAILURE;
        }

        $dasar = rtrim($this->option('url') ?: (string) config('app.url'), '/');
        $url = $dasar.'/telegram/webhook';

        if (! str_starts_with($url, 'https://')) {
            $this->error("Telegram hanya menerima webhook HTTPS. URL saat ini: {$url}");
            $this->line('Untuk pengembangan lokal, jalankan terowongan (ngrok/cloudflared)');
            $this->line('lalu ulangi dengan: php artisan surat:telegram-webhook --url=https://xxx.ngrok.io');

            return self::FAILURE;
        }

        if (! $bot->setWebhook($url, $rahasia)) {
            $this->error('Telegram menolak pemasangan webhook. Periksa storage/logs.');

            return self::FAILURE;
        }

        $this->info("Webhook terpasang: {$url}");

        return self::SUCCESS;
    }

    private function info_(TelegramBot $bot): int
    {
        $hasil = $bot->infoWebhook();

        if ($hasil === null) {
            $this->error('Gagal membaca info webhook.');

            return self::FAILURE;
        }

        foreach ($hasil as $kunci => $nilai) {
            $this->line(str_pad((string) $kunci, 28).': '.json_encode($nilai));
        }

        return self::SUCCESS;
    }

    private function lepas(TelegramBot $bot): int
    {
        $bot->hapusWebhook();
        $this->info('Webhook dilepas.');

        return self::SUCCESS;
    }

    private function gagalAksi(): int
    {
        $this->error('Aksi tidak dikenali. Gunakan: pasang | info | lepas');

        return self::FAILURE;
    }
}
