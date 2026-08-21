<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Autentikasi Admin App — PRD 5.1 & 9.
 *
 * Menggunakan Sanctum SPA (cookie-based session), bukan token bearer:
 * Admin App berjalan di domain yang sama sehingga cookie httpOnly lebih aman
 * daripada menyimpan token di localStorage yang rawan XSS.
 */
class AuthController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function login(Request $request): JsonResponse
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

            return ApiResponse::error('Akun Anda dinonaktifkan. Hubungi Admin Utama.', 403);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->logger->log('login', $user, 'Login berhasil');

        return ApiResponse::success($this->profil($user), 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logger->log('logout', Auth::user(), 'Logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(message: 'Logout berhasil.');
    }

    /**
     * Dipanggil Admin App saat bootstrap untuk memulihkan sesi dan
     * membangun menu sesuai permission pengguna.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->profil($request->user()));
    }

    /**
     * @return array<string, mixed>
     */
    private function profil($user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->name,
            'email' => $user->email,
            'no_telepon' => $user->no_telepon,
            'village_id' => $user->village_id,
            'roles' => $user->getRoleNames(),
            // Dikirim agar Admin App dapat menyembunyikan menu/aksi yang tidak
            // diizinkan. Ini murni demi UX — otorisasi sesungguhnya tetap
            // ditegakkan di sisi server pada tiap endpoint.
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
