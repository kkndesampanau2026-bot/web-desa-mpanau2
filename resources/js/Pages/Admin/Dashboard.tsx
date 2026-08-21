import type { ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import { useHalaman } from '@/types/inertia'

/**
 * Placeholder. Isi sesungguhnya (ringkasan visitor, pengaduan & permohonan
 * PPID masuk, konten terbaru, grafik tren) menyusul — PRD 5.20.
 *
 * Kerangka halaman (sidebar, header, tombol keluar) sudah pindah ke
 * `LayoutAdmin`, jadi yang tersisa di sini hanyalah isinya.
 */
export default function Dashboard() {
  const pengguna = useHalaman().props.auth.user

  return (
    <>
      <Head title="Dashboard" />

      <div className="rounded-xl border border-slate-200 bg-white p-6">
        <h2 className="font-medium text-slate-900">Arsitektur monolit aktif</h2>
        <p className="mt-1 text-sm text-slate-600">
          Halaman ini disajikan langsung oleh Laravel lewat Inertia — tidak ada lagi
          aplikasi React terpisah maupun permintaan lintas origin ke REST API.
        </p>

        <h3 className="mt-6 text-sm font-medium text-slate-900">
          Izin akun ini ({pengguna?.permissions.length ?? 0})
        </h3>
        <ul className="mt-2 flex flex-wrap gap-1.5">
          {pengguna?.permissions.map((izin) => (
            <li
              key={izin}
              className="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700"
            >
              {izin}
            </li>
          ))}
          {pengguna?.permissions.length === 0 && (
            <li className="text-sm text-slate-500">
              Tidak ada permission eksplisit (akses Super Admin lewat Gate).
            </li>
          )}
        </ul>
      </div>
    </>
  )
}

Dashboard.layout = (page: ReactNode) => <LayoutAdmin>{page}</LayoutAdmin>
