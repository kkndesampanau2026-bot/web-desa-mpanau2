<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Membuat PII_HASH_KEY — kunci HMAC untuk blind index data pribadi.
 *
 * Kunci ini sengaja TERPISAH dari APP_KEY: APP_KEY mengenkripsi nilai NIK,
 * sedangkan kunci ini membentuk hash pencariannya. Bila keduanya sama, satu
 * kebocoran kunci membuka dua lapis perlindungan sekaligus.
 *
 * Mengganti kunci ini membuat seluruh blind index yang sudah ada tidak lagi
 * cocok, sehingga pencarian NIK berhenti berfungsi sampai di-index ulang —
 * karena itu perintah ini menolak menimpa kunci yang sudah terisi kecuali
 * dipaksa.
 */
class GeneratePiiKey extends Command
{
    protected $signature = 'pii:key {--force : Timpa kunci yang sudah ada}';

    protected $description = 'Membuat kunci HMAC untuk blind index data pribadi (NIK, No. KK)';

    public function handle(): int
    {
        $kunciBaru = 'base64:'.base64_encode(random_bytes(32));
        $berkasEnv = base_path('.env');

        if (! file_exists($berkasEnv)) {
            $this->error('Berkas .env tidak ditemukan.');

            return self::FAILURE;
        }

        $isi = file_get_contents($berkasEnv);
        $sudahTerisi = preg_match('/^PII_HASH_KEY=(.+)$/m', $isi, $cocok)
            && ! str_contains($cocok[1], 'CHANGE_ME');

        if ($sudahTerisi && ! $this->option('force')) {
            $this->warn('PII_HASH_KEY sudah terisi.');
            $this->line(
                'Menggantinya membuat seluruh blind index yang ada tidak cocok lagi, '
                .'sehingga pencarian NIK & deduplikasi impor berhenti berfungsi '
                .'sampai data di-index ulang.'
            );
            $this->line('Gunakan --force bila Anda memang bermaksud menggantinya.');

            return self::FAILURE;
        }

        $isi = preg_match('/^PII_HASH_KEY=.*$/m', $isi)
            ? preg_replace('/^PII_HASH_KEY=.*$/m', 'PII_HASH_KEY='.$kunciBaru, $isi)
            : $isi.PHP_EOL.'PII_HASH_KEY='.$kunciBaru.PHP_EOL;

        file_put_contents($berkasEnv, $isi);

        $this->info('PII_HASH_KEY berhasil dibuat.');
        $this->line('Kunci ini melindungi data pribadi warga — jangan pernah di-commit.');

        return self::SUCCESS;
    }
}
