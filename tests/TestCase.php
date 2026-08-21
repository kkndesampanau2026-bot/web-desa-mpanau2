<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sanctum SPA hanya memasang middleware sesi bila permintaan berasal
        // dari origin yang terdaftar pada SANCTUM_STATEFUL_DOMAINS — deteksinya
        // membaca header Origin/Referer. Helper test seperti postJson() tidak
        // mengirim header itu, sehingga tanpa baris ini endpoint login gagal
        // dengan "Session store not set on request".
        //
        // Menyetelnya di sini membuat seluruh test berperilaku seperti
        // permintaan sungguhan dari Admin App di browser.
        $this->withHeader('Origin', config('app.url'));
    }
}
