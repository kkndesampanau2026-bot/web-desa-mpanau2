import { useState, type FormEvent, type ReactNode } from 'react'
import { Head, router } from '@inertiajs/react'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { StatusPengaduan } from '@/types/api'
import { GAYA_INPUT_PENGADUAN, LABEL_STATUS_PENGADUAN } from './status'

/**
 * Pelacakan status pengaduan — PRD 6.15.
 *
 * Nomor tiket hidup di query string, bukan state komponen, sehingga tautan
 * "Lacak Pengaduan" pada tanda terima langsung menampilkan hasilnya.
 */
export default function LacakPengaduan({
  tiket,
  pengaduan,
  tidak_ditemukan: tidakDitemukan,
}: {
  tiket: string | null
  pengaduan: StatusPengaduan | null
  tidak_ditemukan: boolean
}) {
  const [masukan, setMasukan] = useState(tiket ?? '')

  return (
    <div className="mx-auto max-w-2xl px-6 py-10">
      <Head title="Lacak Pengaduan" />

      <h1 className="font-heading text-2xl font-bold text-navy">Lacak Pengaduan</h1>
      <p className="mt-2 text-slate-600">
        Masukkan nomor tiket yang Anda terima saat mengirim pengaduan.
      </p>

      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          router.get(
            '/pengaduan/lacak',
            { tiket: masukan.trim() },
            { preserveState: true, preserveScroll: true },
          )
        }}
        className="mt-6 flex flex-wrap items-end gap-3"
      >
        <div className="min-w-64 flex-1">
          <label htmlFor="tiket" className="block text-sm font-medium text-slate-700">
            Nomor Tiket
          </label>
          <input
            id="tiket"
            required
            value={masukan}
            onChange={(e) => setMasukan(e.target.value)}
            placeholder="PGD-20260819-1234"
            className={`${GAYA_INPUT_PENGADUAN} font-mono`}
          />
        </div>
        <button
          type="submit"
          className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white hover:bg-navy-light"
        >
          Lacak
        </button>
      </form>

      {tidakDitemukan && (
        <p role="alert" className="mt-5 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
          Nomor tiket tidak ditemukan.
        </p>
      )}

      {pengaduan && (
        <div className="mt-8 rounded-2xl border border-black/5 bg-white p-5 shadow-sm">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <p className="font-mono text-sm text-slate-600">{pengaduan.nomor_tiket}</p>
            <span className="rounded-full bg-navy/5 px-3 py-1 text-sm font-medium text-slate-800">
              {LABEL_STATUS_PENGADUAN[pengaduan.status]}
            </span>
          </div>

          <dl className="mt-5 space-y-3 text-sm">
            <Baris label="Pelapor" nilai={pengaduan.nama} />
            <Baris label="Kategori" nilai={pengaduan.kategori_pengaduan} />
            <Baris label="Isi Pengaduan" nilai={pengaduan.isi_pengaduan} />
            <Baris
              label="Tanggal Pengaduan"
              nilai={formatTanggal(pengaduan.tanggal_pengaduan)}
            />
            {pengaduan.tanggal_tanggapan && (
              <Baris
                label="Tanggal Tanggapan"
                nilai={formatTanggal(pengaduan.tanggal_tanggapan)}
              />
            )}
            {pengaduan.tanggapan_admin && (
              <Baris label="Tanggapan Petugas" nilai={pengaduan.tanggapan_admin} />
            )}
            {pengaduan.alasan_penolakan && (
              <Baris label="Alasan Penolakan" nilai={pengaduan.alasan_penolakan} />
            )}
          </dl>

          <p className="mt-5 text-xs text-slate-500">
            Nama ditampilkan sebagian demi menjaga kerahasiaan pelapor.
          </p>
        </div>
      )}
    </div>
  )
}

function Baris({ label, nilai }: { label: string; nilai: string }) {
  return (
    <div>
      <dt className="text-slate-500">{label}</dt>
      <dd className="whitespace-pre-line text-slate-800">{nilai}</dd>
    </div>
  )
}

LacakPengaduan.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
