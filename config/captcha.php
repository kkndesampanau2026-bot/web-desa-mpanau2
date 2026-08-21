<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi CAPTCHA (Cloudflare Turnstile)
|--------------------------------------------------------------------------
|
| Melindungi formulir publik dari automasi — PRD 6.6 & 12.2.
|
| Bila `secret_key` kosong, verifikasi CAPTCHA DILEWATI dan formulir tetap
| berfungsi dengan pertahanan lain (rate limiting, pencocokan dua faktor,
| pesan netral). Ini disengaja agar desa dapat memasang situsnya lebih dulu
| lalu menambahkan CAPTCHA setelah memperoleh kunci.
|
| Cara memperoleh kunci: dash.cloudflare.com → Turnstile → Add site (gratis).
|
*/

return [
    'site_key' => env('TURNSTILE_SITE_KEY'),
    'secret_key' => env('TURNSTILE_SECRET_KEY'),
];
