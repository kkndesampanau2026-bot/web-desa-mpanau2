<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Pengelolaan akun operator — PRD 5.19.
 *
 * Layar pertama dashboard yang datanya datang sebagai prop Inertia, bukan
 * lewat XHR ke `/api/v1/admin/*` seperti modul-modul sebelumnya (lihat
 * `docs/MIGRASI-MONOLIT.md` Fase 4). Modul baru mengikuti pola ini.
 *
 * Seluruh route-nya dijaga permission `manage-users`. Izin itu sengaja tidak
 * diberikan kepada peran `Operator Utama`, yang selain itu sekuat Admin
 * Utama — sebab siapa pun yang dapat membuat akun dapat membuat akun berperan
 * Admin Utama, dan dengan begitu meraih kembali seluruh kewenangan yang
 * ditahan darinya.
 */
class PenggunaController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly CurrentVillage $village,
    ) {}

    public function index(Request $request): Response
    {
        $daftar = User::query()
            ->where(fn ($q) => $q
                ->where('village_id', $this->village->id())
                // Super Admin lintas-tenant tidak terikat satu desa, tetapi
                // tetap harus terlihat: menyembunyikannya membuat daftar akun
                // berbohong tentang siapa saja yang dapat masuk.
                ->orWhereNull('village_id'))
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'nama' => $u->name,
                'email' => $u->email,
                'no_telepon' => $u->no_telepon,
                'peran' => $u->roles->first()?->name,
                'status_aktif' => $u->status_aktif,
                'terakhir_masuk' => $u->last_login_at?->diffForHumans(),
                'diri_sendiri' => $u->id === $request->user()->id,
                // Dihitung di server, bukan ditebak ulang di React: aturan
                // siapa boleh menyunting siapa adalah aturan keamanan, dan
                // satu-satunya salinannya harus yang ditegakkan di sini.
                'dapat_disunting' => $this->bolehMengubah($request->user(), $u),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Pengguna', [
            'daftar_pengguna' => $daftar,
            'peran_tersedia' => $this->peranYangBolehDiberikan($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            // Keunikan diperiksa atas SELURUH baris, termasuk yang sudah
            // di-soft delete. Bukan pilihan gaya: kolom `email` memegang
            // indeks UNIQUE di basis data, sehingga aturan yang mengecualikan
            // baris terhapus akan meloloskan data yang lalu ditolak MySQL
            // sebagai galat 500 — kegagalan tanpa pesan yang berguna.
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'no_telepon' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'peran' => ['required', 'string', Rule::in($this->peranYangBolehDiberikan($request->user()))],
            'status_aktif' => ['boolean'],
        ], [
            // Tanpa pesan ini, operator melihat "email sudah dipakai" padahal
            // tidak ada satu pun akun dengan alamat itu di daftar — pemiliknya
            // sudah dihapus dan karenanya tak terlihat.
            'email.unique' => 'Email ini sudah terpakai, termasuk oleh akun yang telah dihapus. '
                .'Gunakan alamat lain.',
        ]);

        $pengguna = User::create([
            // Super Admin tidak diikat ke satu desa; peran lain selalu terikat
            // desa yang sedang dilayani.
            'village_id' => $data['peran'] === 'Super Admin' ? null : $this->village->id(),
            'name' => $data['nama'],
            'email' => $data['email'],
            'no_telepon' => $data['no_telepon'] ?? null,
            'password' => $data['password'],
            'status_aktif' => $data['status_aktif'] ?? true,
        ]);

        $pengguna->syncRoles([$data['peran']]);

        $this->logger->log(
            'user_dibuat',
            $pengguna,
            "Akun operator dibuat: {$pengguna->email} ({$data['peran']})",
            dataSesudah: $this->potret($pengguna),
        );

        return back()->with('sukses', "Akun {$pengguna->name} berhasil dibuat.");
    }

    public function update(Request $request, User $pengguna): RedirectResponse
    {
        $this->pastikanBolehMengubah($request->user(), $pengguna);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($pengguna->id),
            ],
            'no_telepon' => ['nullable', 'string', 'max:30'],
            // Dikosongkan berarti "biarkan kata sandi lama".
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'peran' => ['required', 'string', Rule::in($this->peranYangBolehDiberikan($request->user()))],
            'status_aktif' => ['boolean'],
        ]);

        $sebelum = $this->potret($pengguna);
        $statusBaru = $data['status_aktif'] ?? true;
        $peranLama = $pengguna->roles->first()?->name;

        // Menurunkan peran atau menonaktifkan pengelola akun terakhir akan
        // mengunci semua orang dari layar ini selamanya — tidak ada jalan
        // pemulihan lewat antarmuka.
        if ($peranLama !== null && $this->peranPengelolaAkun()->contains($peranLama)) {
            $kehilanganWewenang = ! $this->peranPengelolaAkun()->contains($data['peran']);

            if ($kehilanganWewenang || ! $statusBaru) {
                $this->pastikanBukanPengelolaTerakhir($pengguna);
            }
        }

        $pengguna->update([
            'name' => $data['nama'],
            'email' => $data['email'],
            'no_telepon' => $data['no_telepon'] ?? null,
            'status_aktif' => $statusBaru,
            'village_id' => $data['peran'] === 'Super Admin' ? null : $this->village->id(),
            ...(filled($data['password'] ?? null) ? ['password' => $data['password']] : []),
        ]);

        $pengguna->syncRoles([$data['peran']]);

        $this->logger->log(
            'user_diubah',
            $pengguna,
            "Akun operator diubah: {$pengguna->email}"
                .(filled($data['password'] ?? null) ? ' (kata sandi disetel ulang)' : ''),
            dataSebelum: $sebelum,
            dataSesudah: $this->potret($pengguna->fresh()),
        );

        return back()->with('sukses', "Akun {$pengguna->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $pengguna): RedirectResponse
    {
        $this->pastikanBolehMengubah($request->user(), $pengguna);
        $this->pastikanBukanPengelolaTerakhir($pengguna);

        $sebelum = $this->potret($pengguna);
        $nama = $pengguna->name;

        // Soft delete: audit trail menyimpan `subjek_id`, dan menghapus
        // barisnya secara permanen akan meninggalkan jejak audit yang menunjuk
        // ke akun yang tidak dapat lagi diidentifikasi.
        $pengguna->delete();

        $this->logger->log(
            'user_dihapus',
            $pengguna,
            "Akun operator dihapus: {$sebelum['email']}",
            dataSebelum: $sebelum,
        );

        return back()->with('sukses', "Akun {$nama} berhasil dihapus.");
    }

    /**
     * Peran yang boleh DIBERIKAN oleh operator yang sedang masuk.
     *
     * Hanya Super Admin yang boleh mencetak Super Admin baru. Tanpa batas ini,
     * seorang Admin Utama dapat menaikkan dirinya sendiri — lewat akun kedua —
     * ke tingkat yang melewati seluruh pemeriksaan permission (`Gate::before`).
     *
     * @return list<string>
     */
    private function peranYangBolehDiberikan(User $pelaku): array
    {
        return Role::orderBy('id')
            ->pluck('name')
            ->reject(fn (string $peran) => $peran === 'Super Admin' && ! $pelaku->hasRole('Super Admin'))
            ->values()
            ->all();
    }

    /**
     * Peran yang pemegangnya dapat mengelola akun.
     *
     * Super Admin ikut disebut eksplisit karena ia sengaja TIDAK memegang
     * permission apa pun — kewenangannya datang dari `Gate::before`, sehingga
     * kueri berbasis permission tidak akan pernah menemukannya.
     *
     * @return Collection<int, string>
     */
    private function peranPengelolaAkun(): Collection
    {
        return Role::whereHas('permissions', fn ($q) => $q->where('name', 'manage-users'))
            ->pluck('name')
            ->push('Super Admin')
            ->unique();
    }

    private function bolehMengubah(User $pelaku, User $sasaran): bool
    {
        // Akun sendiri disunting lewat "Akun Saya". Melarangnya di sini bukan
        // sekadar soal rapi: layar ini dapat menurunkan peran dan mematikan
        // status aktif, dua cara tercepat mengunci diri sendiri keluar.
        if ($pelaku->id === $sasaran->id) {
            return false;
        }

        // Super Admin hanya boleh disentuh sesama Super Admin.
        if ($sasaran->hasRole('Super Admin') && ! $pelaku->hasRole('Super Admin')) {
            return false;
        }

        return true;
    }

    private function pastikanBolehMengubah(User $pelaku, User $sasaran): void
    {
        if ($this->bolehMengubah($pelaku, $sasaran)) {
            return;
        }

        abort(403, $pelaku->id === $sasaran->id
            ? 'Akun sendiri diubah lewat halaman Akun Saya.'
            : 'Anda tidak berwenang mengubah akun ini.');
    }

    private function pastikanBukanPengelolaTerakhir(User $sasaran): void
    {
        $peranLain = $this->peranPengelolaAkun();

        $tersisa = User::role($peranLain->all())
            ->where('status_aktif', true)
            ->where('id', '!=', $sasaran->id)
            ->count();

        if ($tersisa > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'peran' => ['Ini satu-satunya akun yang dapat mengelola pengguna. '
                .'Buat atau aktifkan pengelola lain sebelum mengubah akun ini.'],
        ]);
    }

    /**
     * Potret akun untuk audit trail.
     *
     * Kata sandi tidak pernah ikut — `ActivityLogger` memang menyaringnya,
     * tetapi mengandalkan penyaring di hilir berarti satu perubahan pada
     * daftar itu diam-diam membocorkan hash ke dalam log.
     *
     * @return array<string, mixed>
     */
    private function potret(User $pengguna): array
    {
        return [
            'nama' => $pengguna->name,
            'email' => $pengguna->email,
            'no_telepon' => $pengguna->no_telepon,
            'peran' => $pengguna->roles->first()?->name,
            'status_aktif' => $pengguna->status_aktif,
        ];
    }
}
