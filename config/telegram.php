<?php

/*
|--------------------------------------------------------------------------
| Bot Telegram — persetujuan Surat Pengantar
|--------------------------------------------------------------------------
|
| Bot ini satu-satunya jalur bagi Ketua RT dan Kepala Dusun untuk menyetujui
| atau menolak permohonan surat; mereka tidak punya akun CMS.
|
| Bila `token` kosong, seluruh pengiriman DILEWATI dengan diam (dicatat ke
| log) dan pengajuan tetap tersimpan normal. Ini disengaja, sepola dengan
| CAPTCHA opsional pada config/captcha.php: desa dapat memasang situsnya lebih
| dulu, lalu menyalakan bot setelah memperoleh token — tanpa membuat formulir
| warga gagal di masa peralihan.
|
| Cara memperoleh token: buka @BotFather di Telegram -> /newbot.
|
| Cara memasang webhook (jalankan sekali, ganti URL sesuai domain):
|   php artisan surat:telegram-webhook
|
*/

return [
    'token' => env('TELEGRAM_BOT_TOKEN'),

    /*
     * Rahasia yang dikirim balik Telegram pada setiap permintaan webhook
     * lewat header X-Telegram-Bot-Api-Secret-Token.
     *
     * Tanpa ini, alamat webhook adalah endpoint POST terbuka yang siapa pun
     * dapat panggil dengan payload buatan sendiri. Meski otorisasi tetap
     * memeriksa chat ID, rahasia ini menahan lalu lintas palsu jauh sebelum
     * mencapai basis data.
     */
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

    /* Detik. Bot API lazim menjawab <1 detik; 10 detik sudah sangat longgar. */
    'timeout' => (int) env('TELEGRAM_TIMEOUT', 10),
];
