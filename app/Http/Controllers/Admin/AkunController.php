<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun operator yang sedang masuk — data dirinya sendiri.
 *
 * Dipisahkan tegas dari `PenggunaController`: layar ini TIDAK menyentuh peran
 * maupun status aktif, dan tidak memerlukan permission apa pun selain sudah
 * masuk. Setiap operator — termasuk Operator Konten yang tidak boleh melihat
 * layar mana pun di grup Sistem — harus dapat mengganti kata sandinya sendiri.
 *
 * Menggabungkan keduanya menjadi satu layar akan memaksa pilihan yang salah
 * di antara dua hal: memberi semua orang `manage-users` (yang berarti semua
 * orang bisa membuat akun Admin Utama), atau melarang operator biasa mengganti
 * kata sandinya sendiri.
 */
class AkunController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function tampilkan(Request $request): Response
    {
        $pengguna = $request->user();

        return Inertia::render('Admin/Akun', [
            'akun' => [
                'nama' => $pengguna->name,
                'email' => $pengguna->email,
                'no_telepon' => $pengguna->no_telepon,
                'peran' => $pengguna->getRoleNames()->all(),
                'terakhir_masuk' => $pengguna->last_login_at?->diffForHumans(),
                'bergabung' => $pengguna->created_at?->translatedFormat('j F Y'),
            ],
        ]);
    }

    public function perbarui(Request $request): RedirectResponse
    {
        $pengguna = $request->user();

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($pengguna->id),
            ],
            'no_telepon' => ['nullable', 'string', 'max:30'],
        ]);

        $sebelum = [
            'nama' => $pengguna->name,
            'email' => $pengguna->email,
            'no_telepon' => $pengguna->no_telepon,
        ];

        $pengguna->update([
            'name' => $data['nama'],
            'email' => $data['email'],
            'no_telepon' => $data['no_telepon'] ?? null,
        ]);

        $this->logger->log(
            'akun_diubah',
            $pengguna,
            'Operator memperbarui data akunnya sendiri',
            dataSebelum: $sebelum,
            dataSesudah: $data,
        );

        return back()->with('sukses', 'Data akun berhasil diperbarui.');
    }

    public function ubahSandi(Request $request): RedirectResponse
    {
        $pengguna = $request->user();

        $data = $request->validate([
            'password_lama' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        /*
         * Kata sandi lama diminta meskipun sesi sudah terautentikasi.
         *
         * Yang dicegah adalah pengambilalihan lewat perangkat yang ditinggal
         * terbuka: tanpa langkah ini, siapa pun yang menemukan layar CMS yang
         * masih login dapat mengunci pemilik aslinya keluar dalam dua ketukan.
         */
        if (! Hash::check($data['password_lama'], $pengguna->password)) {
            $this->logger->log(
                'akun_sandi_gagal',
                $pengguna,
                'Percobaan ganti kata sandi dengan kata sandi lama yang salah',
            );

            throw ValidationException::withMessages([
                'password_lama' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $pengguna->update(['password' => $data['password']]);

        $this->logger->log(
            'akun_sandi_diubah',
            $pengguna,
            'Operator mengganti kata sandinya sendiri',
        );

        return back()->with('sukses', 'Kata sandi berhasil diganti.');
    }
}
