import { useState, type FormEvent, type ReactNode } from 'react'
import { router, useForm } from '@inertiajs/react'
import { KeyRound, ShieldAlert, UserPlus, X } from 'lucide-react'
import {
  AksiBaris,
  Kartu,
  Kolom,
  Input,
  Pemberitahuan,
  Pilihan,
  Tombol,
} from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import { useHalaman } from '@/types/inertia'

/**
 * CMS Pengelolaan Akun Operator — PRD 5.19.
 *
 * Datanya datang sebagai prop Inertia dari `PenggunaController`, bukan lewat
 * XHR ke `/api/v1` seperti layar CMS lain yang belum bermigrasi.
 *
 * `dapat_disunting` dihitung SERVER, bukan disimpulkan ulang di sini. Aturan
 * "siapa boleh menyunting siapa" adalah aturan keamanan; menyalinnya ke sisi
 * klien berarti dua salinan yang cepat atau lambat berbeda isi. Yang di sini
 * hanya menentukan tombolnya digambar atau tidak — penolakannya tetap datang
 * dari server.
 */
interface BarisPengguna {
  id: number
  nama: string
  email: string
  no_telepon: string | null
  peran: string | null
  status_aktif: boolean
  terakhir_masuk: string | null
  diri_sendiri: boolean
  dapat_disunting: boolean
}

const KOSONG = {
  nama: '',
  email: '',
  no_telepon: '',
  peran: '',
  status_aktif: true,
  password: '',
  password_confirmation: '',
}

export default function PenggunaPage({
  daftar_pengguna: daftar,
  peran_tersedia: peranTersedia,
}: {
  daftar_pengguna: BarisPengguna[]
  peran_tersedia: string[]
}) {
  const { flash } = useHalaman().props

  /** Null = formulir tertutup. 0 = membuat akun baru. Selain itu = id yang disunting. */
  const [sedangDisunting, setSedangDisunting] = useState<number | null>(null)

  const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
    ...KOSONG,
  })

  const mode = sedangDisunting === null ? 'tutup' : sedangDisunting === 0 ? 'baru' : 'sunting'

  function bukaBaru() {
    reset()
    clearErrors()
    setData({ ...KOSONG, peran: peranTersedia[0] ?? '' })
    setSedangDisunting(0)
  }

  function bukaSunting(baris: BarisPengguna) {
    clearErrors()
    setData({
      nama: baris.nama,
      email: baris.email,
      no_telepon: baris.no_telepon ?? '',
      peran: baris.peran ?? '',
      status_aktif: baris.status_aktif,
      // Selalu kosong saat membuka: terisi berarti menyetel ulang kata sandi.
      password: '',
      password_confirmation: '',
    })
    setSedangDisunting(baris.id)
  }

  function tutup() {
    setSedangDisunting(null)
    reset()
    clearErrors()
  }

  function tanganiSubmit(event: FormEvent) {
    event.preventDefault()

    if (mode === 'baru') {
      post('/admin/pengguna', { preserveScroll: true, onSuccess: tutup })
    } else if (mode === 'sunting') {
      put(`/admin/pengguna/${sedangDisunting}`, { preserveScroll: true, onSuccess: tutup })
    }
  }

  function hapus(baris: BarisPengguna) {
    router.delete(`/admin/pengguna/${baris.id}`, { preserveScroll: true })
  }

  return (
    <div className="space-y-6">
      {flash?.sukses && <Pemberitahuan jenis="sukses" pesan={flash.sukses} />}

      {/*
        Peringatan ini bukan hiasan: layar inilah satu-satunya tempat peran
        dibagikan, dan peran "Admin Utama" mencakup hak membuat akun — artinya
        memberikannya berarti menyerahkan kendali penuh atas dashboard.
      */}
      <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <ShieldAlert className="mt-0.5 size-5 shrink-0 text-amber-600" aria-hidden="true" />
        <div className="text-sm text-amber-900">
          <p className="font-semibold">Beri peran sekecil yang dibutuhkan.</p>
          <p className="mt-0.5 text-amber-800">
            <strong>Operator Utama</strong> dapat mengelola seluruh modul — termasuk data penduduk
            dan bantuan sosial — tetapi tidak dapat membuat atau mengubah akun. Pilih{' '}
            <strong>Admin Utama</strong> hanya bila orang tersebut memang perlu menambah akun
            operator lain.
          </p>
        </div>
      </div>

      <div className="flex items-center justify-between gap-4">
        <p className="text-sm text-slate-500">
          {daftar.length} akun terdaftar pada dashboard desa ini.
        </p>

        {mode === 'tutup' && (
          <Tombol type="button" onClick={bukaBaru}>
            <UserPlus className="size-4" aria-hidden="true" />
            Tambah Akun
          </Tombol>
        )}
      </div>

      {mode !== 'tutup' && (
        <Kartu
          judul={mode === 'baru' ? 'Akun Operator Baru' : 'Sunting Akun'}
          ikon={UserPlus}
          anak={
            <form onSubmit={tanganiSubmit} className="space-y-5" noValidate>
              <div className="grid gap-5 sm:grid-cols-2">
                <Kolom label="Nama Lengkap" htmlFor="nama" galat={errors.nama}>
                  <Input
                    id="nama"
                    value={data.nama}
                    onChange={(e) => setData('nama', e.target.value)}
                    galat={errors.nama}
                  />
                </Kolom>

                <Kolom label="Email" htmlFor="email" galat={errors.email}>
                  <Input
                    id="email"
                    type="email"
                    autoComplete="off"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    galat={errors.email}
                  />
                </Kolom>

                <Kolom
                  label="No. Telepon"
                  htmlFor="no_telepon"
                  petunjuk="Opsional."
                  galat={errors.no_telepon}
                >
                  <Input
                    id="no_telepon"
                    value={data.no_telepon}
                    onChange={(e) => setData('no_telepon', e.target.value)}
                    galat={errors.no_telepon}
                  />
                </Kolom>

                <Kolom label="Peran" htmlFor="peran" galat={errors.peran}>
                  <Pilihan
                    id="peran"
                    value={data.peran}
                    onChange={(v) => setData('peran', v)}
                    options={peranTersedia.map((p) => ({ value: p, label: p }))}
                    galat={errors.peran}
                  />
                </Kolom>

                <Kolom
                  label={mode === 'baru' ? 'Kata Sandi' : 'Kata Sandi Baru'}
                  htmlFor="password"
                  petunjuk={
                    mode === 'baru'
                      ? 'Minimal 8 karakter, memuat huruf dan angka.'
                      : 'Kosongkan bila kata sandi tidak diganti.'
                  }
                  galat={errors.password}
                >
                  <Input
                    id="password"
                    type="password"
                    autoComplete="new-password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    galat={errors.password}
                  />
                </Kolom>

                <Kolom label="Ulangi Kata Sandi" htmlFor="password_confirmation">
                  <Input
                    id="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                  />
                </Kolom>
              </div>

              <label className="flex w-fit items-center gap-2.5 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={data.status_aktif}
                  onChange={(e) => setData('status_aktif', e.target.checked)}
                  className="size-4 rounded border-slate-300 text-teal-600 focus:ring-teal-600/30"
                />
                Akun aktif — dapat masuk ke dashboard
              </label>

              <div className="flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                <Tombol type="submit" disabled={processing}>
                  {processing ? 'Menyimpan…' : mode === 'baru' ? 'Buat Akun' : 'Simpan Perubahan'}
                </Tombol>
                <Tombol type="button" variasi="sekunder" onClick={tutup}>
                  <X className="size-4" aria-hidden="true" />
                  Batal
                </Tombol>
              </div>
            </form>
          }
        />
      )}

      <Kartu
        judul="Daftar Akun"
        ikon={KeyRound}
        anak={
          daftar.length === 0 ? (
            <p className="py-6 text-center text-sm text-slate-500">Belum ada akun terdaftar.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[46rem] text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-xs tracking-wide text-slate-500 uppercase">
                    <th scope="col" className="pb-2 font-semibold">
                      Nama
                    </th>
                    <th scope="col" className="pb-2 font-semibold">
                      Peran
                    </th>
                    <th scope="col" className="pb-2 font-semibold">
                      Status
                    </th>
                    <th scope="col" className="pb-2 font-semibold">
                      Terakhir Masuk
                    </th>
                    <th scope="col" className="pb-2 text-right font-semibold">
                      Aksi
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {daftar.map((baris) => (
                    <tr key={baris.id} className="border-b border-slate-100 last:border-b-0">
                      <td className="py-3 pr-4">
                        <p className="font-medium text-slate-900">
                          {baris.nama}
                          {baris.diri_sendiri && (
                            <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                              Anda
                            </span>
                          )}
                        </p>
                        <p className="text-xs break-all text-slate-500">{baris.email}</p>
                      </td>

                      <td className="py-3 pr-4 text-slate-700">{baris.peran ?? '—'}</td>

                      <td className="py-3 pr-4">
                        <span
                          className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                            baris.status_aktif
                              ? 'bg-green-50 text-green-700'
                              : 'bg-slate-100 text-slate-500'
                          }`}
                        >
                          {baris.status_aktif ? 'Aktif' : 'Nonaktif'}
                        </span>
                      </td>

                      <td className="py-3 pr-4 text-slate-500">{baris.terakhir_masuk ?? 'Belum pernah'}</td>

                      <td className="py-3">
                        {baris.dapat_disunting ? (
                          <AksiBaris
                            nama={`akun ${baris.nama}`}
                            onSunting={() => bukaSunting(baris)}
                            onHapus={() => hapus(baris)}
                          />
                        ) : (
                          <p className="text-right text-xs text-slate-400">
                            {baris.diri_sendiri ? 'Lewat Akun Saya' : 'Tidak berwenang'}
                          </p>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        }
      />
    </div>
  )
}

PenggunaPage.layout = (page: ReactNode) => (
  <LayoutAdmin judul="Akun Operator">{page}</LayoutAdmin>
)
