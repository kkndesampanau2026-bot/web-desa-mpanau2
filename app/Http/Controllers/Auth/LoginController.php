<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Autentikasi operator CMS — PRD 5.1.
 *
 * Memakai sesi web biasa. Sebelumnya Admin App berdiri di origin lain
 * (localhost:5174) sehingga login harus melewati Sanctum SPA: ambil cookie
 * CSRF lebih dulu, kirim kredensial lewat XHR, lalu pulihkan sesi dengan
 * memanggil `/auth/me` setiap kali aplikasi dimuat. Karena dashboard kini
 * disajikan Laravel sendiri, seluruh tarian itu tidak diperlukan — cukup
 * `Auth::attempt` dan redirect, dan identitas operator ikut pada setiap
 * respons sebagai prop bersama.
 */
class LoginController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function tampilkan(): Response
    {
        return Inertia::render('Auth/Masuk');
    }

    public function masuk(Request $request): RedirectResponse
    {
        $kredensial = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($kredensial, $request->boolean('remember'))) {
            // Pesan sengaja tidak membedakan "email tidak ada" dari "password salah"
            // agar tidak dapat dipakai mengenumerasi akun operator yang valid.
            $this->logger->log('failed_login', deskripsi: 'Percobaan login gagal: '.$kredensial['email']);

            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak sesuai.'],
            ]);
        }

        $user = Auth::user();

        if (! $user->status_aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['Akun Anda dinonaktifkan. Hubungi Admin Utama.'],
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->logger->log('login', $user, 'Login berhasil');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function keluar(Request $request): RedirectResponse
    {
        $this->logger->log('logout', Auth::user(), 'Logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sukses', 'Anda telah keluar.');
    }
}
