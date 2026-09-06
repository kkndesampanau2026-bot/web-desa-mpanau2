import { useState, type ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { ChevronDown, FileText } from 'lucide-react'
import { AksiBerkasPdf, IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { InformasiPpid, JenisInformasiPpid } from '@/types/api'

/**
 * Ketiga jenis informasi PPID dilayani satu komponen — pembedanya jenis dari
 * URL, dan nama jenis itulah yang menjadi judul halaman (H1) di bilah navy.
 *
 * Pemilihan jenis dilakukan dari kartu kategori di halaman /ppid, bukan dari
 * halaman ini; bilah tab PPID menandai bahwa pengunjung sedang berada di
 * "Informasi Publik".
 */
const JENIS: Record<JenisInformasiPpid, string> = {
  berkala: 'Informasi Secara Berkala',
  'serta-merta': 'Informasi Serta Merta',
  'setiap-saat': 'Informasi Setiap Saat',
}

export default function Informasi({
  jenis,
  informasi,
}: {
  jenis: JenisInformasiPpid
  informasi: InformasiPpid[]
}) {
  const judul = JENIS[jenis]
  const grup = kelompokKategori(informasi)

  return (
    <>
      <Head title={judul} />

      <KepalaHalaman
        lebar="sedang"
        eyebrow="Keterbukaan Informasi Publik"
        judul={judul}
        deskripsi="Dokumen yang disediakan PPID Desa sesuai UU Nomor 14 Tahun 2008 tentang Keterbukaan Informasi Publik. Tekan sebuah kategori untuk melihat berkasnya."
      />


      <IsiHalaman lebar="sedang">
        {/* Daftar dokumen dikelompokkan per kategori. */}
        <div className="space-y-3">
          {grup.length === 0 ? (
            <p className="rounded-xl border border-dashed border-navy/20 bg-white px-6 py-12 text-center text-slate-500">
              Belum ada dokumen pada kategori informasi ini.
            </p>
          ) : (
            grup.map((g, i) => (
              <Akordeon
                key={g.kategori}
                kategori={g.kategori}
                dokumen={g.dokumen}
                awalBuka={i === 0}
              />
            ))
          )}
        </div>
      </IsiHalaman>
    </>
  )
}

/** Satu kategori pada akordeon; dapat dibuka-tutup untuk memperlihatkan dokumen. */
function Akordeon({
  kategori,
  dokumen,
  awalBuka,
}: {
  kategori: string
  dokumen: InformasiPpid[]
  awalBuka: boolean
}) {
  const [buka, setBuka] = useState(awalBuka)

  return (
    <div className="overflow-hidden rounded-xl border border-black/10 bg-white shadow-sm">
      <button
        type="button"
        onClick={() => setBuka((b) => !b)}
        aria-expanded={buka}
        className="flex w-full items-center justify-between gap-3 px-5 py-4 text-left"
      >
        <span className="flex min-w-0 items-center gap-3">
          <FileText className="size-[18px] shrink-0 text-gold" aria-hidden="true" />
          <span className="font-heading text-base font-bold text-navy">{kategori}</span>
        </span>
        <ChevronDown
          className={`size-[18px] shrink-0 text-slate-400 transition-transform ${
            buka ? 'rotate-180' : ''
          }`}
          aria-hidden="true"
        />
      </button>

      {buka && (
        <div className="border-t border-slate-100 px-5 pt-4 pb-4">
          <ul className="space-y-3">
            {dokumen.map((d, i) => (
              <li key={i}>
                <Dokumen info={d} />
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

/**
 * Kartu dokumen di dalam kategori.
 *
 * Dua aksi terpisah, bukan satu baris yang dapat diklik. Sebelumnya seluruh
 * baris adalah tautan berlabel "Unduh" yang sebenarnya MEMBUKA berkas di tab
 * baru — peramban menyajikan PDF secara inline selama tidak diminta
 * mengunduhnya. Labelnya karena itu menjanjikan hal yang tidak dilakukannya,
 * dan tidak ada cara menyimpan berkas tanpa melewati penampil PDF lebih dulu.
 */
function Dokumen({ info }: { info: InformasiPpid }) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-3 rounded-lg bg-cream p-3">
      <div className="min-w-0 flex-1">
        <p className="font-semibold text-navy">{info.judul}</p>
        {info.deskripsi && (
          <p className="mt-0.5 text-sm leading-relaxed text-slate-600">{info.deskripsi}</p>
        )}
        <p className="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-400">
          {info.periode && <span>{info.periode}</span>}
          {info.periode && <span aria-hidden="true">·</span>}
          <span>Diperbarui: {formatTanggal(info.tanggal_publish)}</span>
        </p>
      </div>

      {info.file && <AksiBerkasPdf file={info.file} nama={info.judul} />}
    </div>
  )
}

/**
 * Mengelompokkan dokumen menurut kategori, mempertahankan urutan dari server
 * (terbaru lebih dulu). Dokumen tanpa kategori dikumpulkan di "Dokumen Lainnya".
 */
function kelompokKategori(
  items: InformasiPpid[],
): { kategori: string; dokumen: InformasiPpid[] }[] {
  const peta = new Map<string, InformasiPpid[]>()

  for (const item of items) {
    const kategori = item.kategori?.trim() || 'Dokumen Lainnya'
    const daftar = peta.get(kategori) ?? []
    daftar.push(item)
    peta.set(kategori, daftar)
  }

  return [...peta.entries()].map(([kategori, dokumen]) => ({ kategori, dokumen }))
}

Informasi.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
