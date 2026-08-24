import { useState, type ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { CalendarClock, ChevronDown, Clock, Download, FileText, Siren } from 'lucide-react'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import type { InformasiPpid, JenisInformasiPpid } from '@/types/api'

/**
 * Ketiga jenis informasi PPID dilayani satu komponen — pembedanya jenis dari
 * URL. Tata letak mengikuti Figma: kepala halaman terpusat, tiga "pil" untuk
 * berpindah jenis, lalu akordeon yang mengelompokkan dokumen menurut kategori.
 * Tiap dokumen dapat diunduh (PDF) oleh publik.
 */
const PIL: { jenis: JenisInformasiPpid; ke: string; label: string; ikon: typeof Clock }[] = [
  { jenis: 'berkala', ke: '/ppid/berkala', label: 'Informasi Secara Berkala', ikon: CalendarClock },
  { jenis: 'serta-merta', ke: '/ppid/serta-merta', label: 'Informasi Serta Merta', ikon: Siren },
  { jenis: 'setiap-saat', ke: '/ppid/setiap-saat', label: 'Informasi Setiap Saat', ikon: Clock },
]

export default function Informasi({
  jenis,
  informasi,
}: {
  jenis: JenisInformasiPpid
  informasi: InformasiPpid[]
}) {
  const namaDesa = useHalaman().props.pengaturan?.nama_desa ?? 'Desa Mpanau'
  const judul = PIL.find((p) => p.jenis === jenis)?.label ?? 'Informasi Publik'
  const grup = kelompokKategori(informasi)

  return (
    <>
      <Head title={judul} />

      <div className="mx-auto max-w-4xl px-6 py-14">
        {/* Kepala halaman terpusat. */}
        <header className="text-center">
          <p className="text-sm font-semibold tracking-[0.14em] text-gold-dark uppercase">
            Keterbukaan Informasi Publik
          </p>
          <h1 className="font-heading mt-2 text-3xl font-bold text-navy sm:text-4xl">
            PPID {namaDesa}
          </h1>
          <p className="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-500">
            Pejabat Pengelola Informasi dan Dokumentasi (PPID) {namaDesa} menyediakan informasi
            publik sesuai UU Keterbukaan Informasi Publik.
          </p>
        </header>

        {/* Pil pemilih jenis informasi. */}
        <nav aria-label="Jenis informasi" className="mt-10 flex flex-wrap justify-center gap-3">
          {PIL.map((pil) => {
            const aktif = pil.jenis === jenis

            return (
              <Link
                key={pil.jenis}
                href={pil.ke}
                aria-current={aktif ? 'page' : undefined}
                className={`inline-flex items-center gap-2 rounded-full border-2 px-5 py-2.5 text-sm font-semibold transition ${
                  aktif
                    ? 'border-navy bg-navy text-white'
                    : 'border-navy/20 bg-white text-navy hover:border-navy/40'
                }`}
              >
                <pil.ikon className="size-4 shrink-0" aria-hidden="true" />
                {pil.label}
              </Link>
            )
          })}
        </nav>

        {/* Daftar dokumen dikelompokkan per kategori. */}
        <div className="mt-10 space-y-3">
          {grup.length === 0 ? (
            <p className="rounded-xl border border-dashed border-navy/20 bg-white/60 px-6 py-12 text-center text-slate-500">
              Belum ada dokumen pada kategori informasi ini.
            </p>
          ) : (
            grup.map((g, i) => (
              <Akordeon key={g.kategori} kategori={g.kategori} dokumen={g.dokumen} awalBuka={i === 0} />
            ))
          )}
        </div>
      </div>
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

/** Kartu dokumen di dalam kategori — dapat diklik untuk mengunduh PDF. */
function Dokumen({ info }: { info: InformasiPpid }) {
  const isi = (
    <>
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

      {info.file && (
        <span className="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-gold-dark">
          <Download className="size-4" aria-hidden="true" />
          <span className="sr-only sm:not-sr-only">Unduh</span>
        </span>
      )}
    </>
  )

  const kelas = 'flex items-start justify-between gap-3 rounded-lg bg-cream p-3'

  return info.file ? (
    <a
      href={info.file}
      target="_blank"
      rel="noopener noreferrer"
      className={`${kelas} transition hover:bg-gold/10`}
    >
      {isi}
    </a>
  ) : (
    <div className={kelas}>{isi}</div>
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
