<?php

use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Header keamanan dipasang pada SELURUH respons, termasuk galat —
        // sebuah respons 500 pun tidak boleh membocorkan versi PHP.
        $middleware->append(\App\Http\Middleware\HeaderKeamanan::class);

        // Setiap respons halaman dibawa Inertia; middleware ini yang
        // menempelkan data bersama (sesi operator, identitas desa, flash).
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        $middleware->trustProxies(at: '*');

        // Header keamanan dipasang pada SELURUH respons, termasuk galat —
        // sebuah respons 500 pun tidak boleh membocorkan versi PHP.
        $middleware->append(\App\Http\Middleware\HeaderKeamanan::class);
        // ...baris-baris lain di bawahnya tetap seperti semula

        // Sanctum SPA: mengubah request dari domain terdaftar
        // (SANCTUM_STATEFUL_DOMAINS) menjadi request bersesi cookie.
        //
        // Masih dipasang selama sebagian layar CMS memanggil /api/v1 lewat
        // HTTP. Setelah seluruh modul berpindah ke route web + Inertia,
        // baris ini dan berkas routes/api.php ikut hilang.
        $middleware->statefulApi();

        $middleware->alias([
            'catat.kunjungan' => \App\Http\Middleware\CatatKunjungan::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Jalur /api SELALU diperlakukan sebagai JSON, terlepas dari header
        // Accept yang dikirim klien. Tanpa ini Laravel menganggap permintaan
        // tanpa `Accept: application/json` sebagai permintaan browser, lalu
        // mencoba mengalihkan galat 401 ke route bernama `login` — yang tidak
        // ada pada backend headless ini dan berakhir sebagai galat 500.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Seluruh galat pada jalur /api dikembalikan dalam bentuk kontrak
        // ApiResponse::error, bukan halaman HTML Laravel. Tanpa ini, klien React
        // harus menangani dua format galat yang berbeda.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error(
                    'Data yang dikirim tidak valid.', 422, $e->errors()
                ),
                $e instanceof AuthenticationException => ApiResponse::error(
                    'Anda harus masuk untuk mengakses sumber daya ini.', 401
                ),
                $e instanceof AuthorizationException => ApiResponse::error(
                    'Anda tidak memiliki izin untuk tindakan ini.', 403
                ),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'Sumber daya tidak ditemukan.', 404
                ),
                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.', 429
                ),
                // Menangkap sisa galat HTTP yang membawa status sendiri —
                // termasuk Spatie\Permission\Exceptions\UnauthorizedException (403),
                // yang tanpa ini akan lolos dengan format pesan bawaan paket.
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() ?: 'Permintaan tidak dapat diproses.',
                    $e->getStatusCode()
                ),
                default => null,   // galat tak terduga: biarkan Laravel menanganinya
            };
        });

        /*
         * Galat pada jalur halaman dirender sebagai halaman Inertia, lengkap
         * dengan header & footer situs, bukan layar galat bawaan Laravel.
         *
         * Hanya berlaku di luar mode debug: saat mengembangkan, jejak galat
         * Laravel jauh lebih berguna daripada pesan ramah untuk pengunjung.
         */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || config('app.debug')) {
                return $response;
            }

            if (! in_array($response->getStatusCode(), [403, 404, 419, 500, 503], true)) {
                return $response;
            }

            return Inertia::render('Galat', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        });
    })->create();
