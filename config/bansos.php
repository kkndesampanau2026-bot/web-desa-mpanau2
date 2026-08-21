<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Modul Bantuan Sosial
|--------------------------------------------------------------------------
|
| Nilai pembatasan laju fitur "Cek Penerima Bansos" (PRD 6.6). Disimpan di
| konfigurasi agar dapat diperketat saat produksi tanpa mengubah kode —
| misalnya bila log pemantauan menunjukkan adanya upaya enumerasi.
|
| Menaikkan angka ini memperlemah pertahanan terhadap penyusunan ulang daftar
| penerima bantuan; ubah hanya dengan alasan yang jelas.
|
*/

return [
    'rate_limit' => (int) env('BANSOS_CHECK_RATE_LIMIT', 10),
    'rate_decay' => (int) env('BANSOS_CHECK_RATE_DECAY', 1),
];
