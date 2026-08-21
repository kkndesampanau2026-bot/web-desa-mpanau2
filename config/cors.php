<?php

/*
|--------------------------------------------------------------------------
| CORS — arsitektur decoupled (PRD 7.2)
|--------------------------------------------------------------------------
|
| Laravel dan kedua aplikasi React berjalan pada origin berbeda saat
| pengembangan (8000 vs 5173/5174), sehingga CORS wajib dikonfigurasi.
|
| `supports_credentials` harus true agar cookie sesi Sanctum ikut terkirim.
| Konsekuensinya allowed_origins TIDAK BOLEH memakai wildcard '*' — browser
| menolak kombinasi wildcard + credentials. Karena itu origin didaftarkan
| eksplisit lewat env.
|
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_PUBLIC_URL', 'http://localhost:5173'),
        env('FRONTEND_ADMIN_URL', 'http://localhost:5174'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
