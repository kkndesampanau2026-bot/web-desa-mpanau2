import { type FormEvent, type ReactNode } from 'react'
import { useForm } from '@inertiajs/react'
import { KeyRound, UserCog } from 'lucide-react'
import { Kartu, Kolom, Input, Pemberitahuan, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import { useHalaman } from '@/types/inertia'

/**
 * Akun operator yang sedang masuk.
 *
 * Sengaja TIDAK memuat peran maupun status aktif: keduanya kewenangan
 * pemegang `manage-users` di layar Akun Operator. Halaman ini terbuka bagi
 * setiap operator tanpa permission tambahan, karena mengganti kata sandi
 * sendiri tidak boleh menuntut hak membuat akun.
 *
 * Dua formulir terpisah, bukan satu: menyatukannya berarti mengubah nama
 * pun menuntut pengetikan kata sandi lama.
 */
interface Akun {
  nama: string
  email: string
  no_telepon: string | null
  peran: string[]
  terakhir_masuk: string | null
  bergabung: string | null
}

export default function AkunPage({ akun }: { akun: Akun }) {
  const { flash } = useHalaman().props

  const profil = useForm({
    nama: akun.nama,
    email: akun.email,
    no_telepon: akun.no_telepon ?? '',
  })

  const sandi = useForm({
    password_lama: '',
    password: '',
    password_confirmation: '',
  })

  function simpanProfil(event: FormEvent) {
    event.preventDefault()
    profil.put('/admin/akun', { preserveScroll: true })
  }

  function simpanSandi(event: FormEvent) {
    event.preventDefault()
    sandi.put('/admin/akun/sandi', {
      preserveScroll: true,
      // Kolom kata sandi dikosongkan setelah berhasil; membiarkannya terisi
      // meninggalkan kata sandi baru terbaca di layar yang mungkin ditinggal.
      onSuccess: () => sandi.reset(),
    })
  }

  return (
    <div className="space-y-6">
      {flash?.sukses && <Pemberitahuan jenis="sukses" pesan={flash.sukses} />}

      <div className="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm">
        <div>
          <p className="text-xs text-slate-500">Peran</p>
          <p className="font-semibold text-slate-900">{akun.peran.join(', ') || '—'}</p>
        </div>
        <div>
          <p className="text-xs text-slate-500">Terakhir masuk</p>
          <p className="font-medium text-slate-700">{akun.terakhir_masuk ?? 'Belum pernah'}</p>
        </div>
        <div>
          <p className="text-xs text-slate-500">Bergabung</p>
          <p className="font-medium text-slate-700">{akun.bergabung ?? '—'}</p>
        </div>
      </div>

      <Kartu
        judul="Data Diri"
        ikon={UserCog}
        anak={
          <form onSubmit={simpanProfil} className="space-y-5" noValidate>
            <div className="grid gap-5 sm:grid-cols-2">
              <Kolom label="Nama Lengkap" htmlFor="nama" galat={profil.errors.nama}>
                <Input
                  id="nama"
                  value={profil.data.nama}
                  onChange={(e) => profil.setData('nama', e.target.value)}
                  galat={profil.errors.nama}
                />
              </Kolom>

              <Kolom
                label="Email"
                htmlFor="email"
                petunjuk="Dipakai untuk masuk ke dashboard."
                galat={profil.errors.email}
              >
                <Input
                  id="email"
                  type="email"
                  autoComplete="username"
                  value={profil.data.email}
                  onChange={(e) => profil.setData('email', e.target.value)}
                  galat={profil.errors.email}
                />
              </Kolom>

              <Kolom
                label="No. Telepon"
                htmlFor="no_telepon"
                petunjuk="Opsional."
                galat={profil.errors.no_telepon}
              >
                <Input
                  id="no_telepon"
                  value={profil.data.no_telepon}
                  onChange={(e) => profil.setData('no_telepon', e.target.value)}
                  galat={profil.errors.no_telepon}
                />
              </Kolom>
            </div>

            <div className="border-t border-slate-200 pt-5">
              <Tombol type="submit" disabled={profil.processing}>
                {profil.processing ? 'Menyimpan…' : 'Simpan Perubahan'}
              </Tombol>
            </div>
          </form>
        }
      />

      <Kartu
        judul="Ganti Kata Sandi"
        ikon={KeyRound}
        anak={
          <form onSubmit={simpanSandi} className="space-y-5" noValidate>
            <div className="grid gap-5 sm:grid-cols-2">
              <Kolom
                label="Kata Sandi Saat Ini"
                htmlFor="password_lama"
                petunjuk="Diminta agar orang lain tidak dapat mengganti sandi dari layar yang Anda tinggal terbuka."
                galat={sandi.errors.password_lama}
              >
                <Input
                  id="password_lama"
                  type="password"
                  autoComplete="current-password"
                  value={sandi.data.password_lama}
                  onChange={(e) => sandi.setData('password_lama', e.target.value)}
                  galat={sandi.errors.password_lama}
                />
              </Kolom>

              <div className="hidden sm:block" aria-hidden="true" />

              <Kolom
                label="Kata Sandi Baru"
                htmlFor="password"
                petunjuk="Minimal 8 karakter, memuat huruf dan angka."
                galat={sandi.errors.password}
              >
                <Input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  value={sandi.data.password}
                  onChange={(e) => sandi.setData('password', e.target.value)}
                  galat={sandi.errors.password}
                />
              </Kolom>

              <Kolom label="Ulangi Kata Sandi Baru" htmlFor="password_confirmation">
                <Input
                  id="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  value={sandi.data.password_confirmation}
                  onChange={(e) => sandi.setData('password_confirmation', e.target.value)}
                />
              </Kolom>
            </div>

            <div className="border-t border-slate-200 pt-5">
              <Tombol type="submit" disabled={sandi.processing}>
                {sandi.processing ? 'Menyimpan…' : 'Ganti Kata Sandi'}
              </Tombol>
            </div>
          </form>
        }
      />
    </div>
  )
}

AkunPage.layout = (page: ReactNode) => <LayoutAdmin judul="Akun Saya">{page}</LayoutAdmin>
