<?php

namespace Tests\Feature\Sementara;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CekApbdesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Diarahkan ke basis data pengembangan: tujuannya mereproduksi galat
        // yang dilihat operator, bukan menguji skenario bersih.
        config(['database.connections.mysql.database' => 'desa_mpanau']);
        DB::purge('mysql');
    }

    public function test_halaman_publik_apbdes(): void
    {
        $this->withoutExceptionHandling();

        try {
            $res = $this->get('/infografis/apbdes');
            dump('PUBLIK STATUS: '.$res->getStatusCode());
        } catch (\Throwable $e) {
            dump('PUBLIK GAGAL: '.get_class($e).': '.$e->getMessage());
            dump($e->getFile().':'.$e->getLine());
        }
    }

    public function test_halaman_admin_apbdes(): void
    {
        $this->withoutExceptionHandling();

        $user = User::where('email', 'superadmin@desa.test')->firstOrFail();

        try {
            $res = $this->actingAs($user)->get('/admin/apbdes');
            dump('ADMIN STATUS: '.$res->getStatusCode());
        } catch (\Throwable $e) {
            dump('ADMIN GAGAL: '.get_class($e).': '.$e->getMessage());
            dump($e->getFile().':'.$e->getLine());
        }
    }

    public function test_endpoint_api_apbdes(): void
    {
        $user = User::where('email', 'superadmin@desa.test')->firstOrFail();

        foreach ([
            '/api/v1/admin/apbdes/tahun',
            '/api/v1/admin/apbdes/kategori',
            '/api/v1/admin/apbdes/items',
        ] as $jalur) {
            $res = $this->actingAs($user)->getJson($jalur);
            dump($jalur.' → '.$res->getStatusCode().' '.substr($res->getContent(), 0, 200));
        }
    }
}
