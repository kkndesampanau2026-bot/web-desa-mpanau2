import type { FormEvent } from 'react'
import { Head, useForm } from '@inertiajs/react'

/**
 * Halaman masuk operator CMS.
 *
 * `useForm` milik Inertia menggantikan tiga hal sekaligus yang dulu ditulis
 * tangan: state tiap field, penanda "sedang mengirim", dan pembongkaran galat
 * validasi dari respons axios. Redirect setelah berhasil pun tidak lagi
 * diputuskan di sini — server yang menentukan tujuannya lewat
 * `redirect()->intended()`.
 */
export default function Masuk() {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
  })

  function tanganiSubmit(event: FormEvent) {
    event.preventDefault()
    post('/admin/masuk')
  }

  return (
    <div className="area-admin flex min-h-screen items-center justify-center bg-slate-100 px-4">
      <Head title="Masuk Dashboard" />

      <div className="w-full max-w-sm rounded-xl bg-white p-8 shadow-sm">
        <h1 className="text-xl font-semibold text-slate-900">Masuk Dashboard</h1>
        <p className="mt-1 text-sm text-slate-500">Website Profil Desa Digital</p>

        <form onSubmit={tanganiSubmit} className="mt-6 space-y-4" noValidate>
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-slate-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              required
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
            />
            {/*
              Satu tempat untuk dua jenis kegagalan: kredensial salah dan akun
              dinonaktifkan. Keduanya dilempar server sebagai galat validasi
              pada field email, sehingga pesannya muncul tepat di bawah kolom
              yang bersangkutan.
            */}
            {errors.email && (
              <p role="alert" className="mt-1 text-sm text-red-600">
                {errors.email}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-slate-700">
              Kata Sandi
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
            />
            {errors.password && (
              <p role="alert" className="mt-1 text-sm text-red-600">
                {errors.password}
              </p>
            )}
          </div>

          <button
            type="submit"
            disabled={processing}
            className="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
          >
            {processing ? 'Memproses…' : 'Masuk'}
          </button>
        </form>
      </div>
    </div>
  )
}
