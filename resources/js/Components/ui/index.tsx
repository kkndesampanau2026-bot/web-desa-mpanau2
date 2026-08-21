import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'

/**
 * Komponen UI bersama — satu sumber kebenaran bagi bahasa desain situs.
 *
 * Tujuannya bukan sekadar mengurangi pengulangan, melainkan mencegah setiap
 * halaman menumbuhkan gaya tombol dan kartunya sendiri. Begitu itu terjadi,
 * situs kehilangan kesan satu kesatuan dan terasa seperti kumpulan halaman
 * yang dikerjakan terpisah.
 */

// ---------------------------------------------------------------------------
// Struktur halaman
// ---------------------------------------------------------------------------

/**
 * Kepala halaman bergaya institusional: latar navy dengan garis emas.
 *
 * Dipakai di puncak tiap halaman agar pengunjung selalu tahu ia berada di
 * bagian mana, tanpa harus membaca navigasi.
 */
export function KepalaHalaman({
  eyebrow,
  judul,
  deskripsi,
  aksi,
}: {
  eyebrow?: string
  judul: string
  deskripsi?: string
  aksi?: ReactNode
}) {
  return (
    <header className="border-b-4 border-gold bg-navy">
      <div className="mx-auto max-w-6xl px-6 py-12 sm:py-14">
        {eyebrow && (
          <p className="mb-3 text-xs font-semibold tracking-widest text-gold uppercase sm:text-sm">
            {eyebrow}
          </p>
        )}

        <div className="flex flex-wrap items-end justify-between gap-6">
          <div className="max-w-3xl">
            <h1 className="font-heading text-3xl leading-tight font-bold text-white sm:text-4xl">
              {judul}
            </h1>
            {deskripsi && (
              <p className="mt-3 leading-relaxed text-white/80">{deskripsi}</p>
            )}
          </div>
          {aksi}
        </div>
      </div>
    </header>
  )
}

/** Pembungkus isi halaman dengan lebar & jarak yang konsisten. */
export function IsiHalaman({
  children,
  lebar = 'sedang',
}: {
  children: ReactNode
  lebar?: 'sempit' | 'sedang' | 'lebar'
}) {
  const maks = {
    sempit: 'max-w-3xl',
    sedang: 'max-w-5xl',
    lebar: 'max-w-6xl',
  }[lebar]

  return <div className={`mx-auto ${maks} px-6 py-12 sm:py-14`}>{children}</div>
}

/** Judul seksi dengan garis aksen emas. */
export function JudulSeksi({
  children,
  keterangan,
}: {
  children: ReactNode
  keterangan?: string
}) {
  return (
    <div className="mb-6">
      <h2 className="font-heading border-l-4 border-gold pl-3 text-xl font-bold text-navy sm:text-2xl">
        {children}
      </h2>
      {keterangan && <p className="mt-2 text-sm text-slate-600">{keterangan}</p>}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Permukaan
// ---------------------------------------------------------------------------

/** Kartu putih — permukaan dasar seluruh konten. */
export function Kartu({
  children,
  className = '',
  interaktif = false,
}: {
  children: ReactNode
  className?: string
  interaktif?: boolean
}) {
  return (
    <div
      className={`rounded-2xl border border-black/5 bg-white shadow-sm ${
        interaktif ? 'transition hover:shadow-lg' : ''
      } ${className}`}
    >
      {children}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Aksi
// ---------------------------------------------------------------------------

const GAYA_TOMBOL = {
  // Aksi utama: pil emas dengan teks navy — kontras tinggi, mudah ditemukan.
  utama:
    'bg-gold text-navy hover:bg-gold-light font-heading font-bold rounded-full',
  // Aksi sekunder pada latar terang.
  navy: 'bg-navy text-white hover:bg-navy-light font-semibold rounded-lg',
  // Aksi tersier / batal.
  garis:
    'border-2 border-navy/20 bg-white text-navy hover:border-navy/40 font-semibold rounded-lg',
} as const

type GayaTombol = keyof typeof GAYA_TOMBOL

const UKURAN_TOMBOL = {
  kecil: 'px-4 py-1.5 text-sm',
  sedang: 'px-6 py-2.5 text-sm',
  besar: 'px-7 py-3 text-base',
} as const

interface PropsTombolBersama {
  gaya?: GayaTombol
  ukuran?: keyof typeof UKURAN_TOMBOL
  children: ReactNode
  className?: string
}

export function Tombol({
  gaya = 'navy',
  ukuran = 'sedang',
  className = '',
  children,
  ...props
}: PropsTombolBersama & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      {...props}
      className={`inline-flex items-center justify-center gap-2 transition disabled:opacity-60 ${GAYA_TOMBOL[gaya]} ${UKURAN_TOMBOL[ukuran]} ${className}`}
    >
      {children}
    </button>
  )
}

/** Versi tautan dari Tombol — dipakai untuk navigasi, bukan aksi. */
export function TombolTautan({
  ke,
  gaya = 'utama',
  ukuran = 'sedang',
  className = '',
  children,
}: PropsTombolBersama & { ke: string }) {
  return (
    <Link
      href={ke}
      className={`inline-flex items-center justify-center gap-2 transition ${GAYA_TOMBOL[gaya]} ${UKURAN_TOMBOL[ukuran]} ${className}`}
    >
      {children}
    </Link>
  )
}

// ---------------------------------------------------------------------------
// Penanda
// ---------------------------------------------------------------------------

const GAYA_LENCANA = {
  netral: 'bg-navy/10 text-navy',
  emas: 'bg-gold/15 text-gold-dark',
  hijau: 'bg-emerald-100 text-emerald-700',
  kuning: 'bg-amber-100 text-amber-700',
  merah: 'bg-rose-100 text-rose-700',
} as const

/**
 * Lencana status.
 *
 * Selalu memuat TEKS, tidak pernah warna saja — pembaca yang tidak
 * membedakan warna tetap memahami maknanya (PRD 12.4).
 */
export function Lencana({
  gaya = 'netral',
  children,
}: {
  gaya?: keyof typeof GAYA_LENCANA
  children: ReactNode
}) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold ${GAYA_LENCANA[gaya]}`}
    >
      {children}
    </span>
  )
}

/** Chip filter yang dapat dipilih. */
export function ChipFilter({
  aktif,
  onClick,
  children,
}: {
  aktif: boolean
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      onClick={onClick}
      aria-pressed={aktif}
      className={`rounded-full px-4 py-1.5 text-sm font-semibold transition ${
        aktif
          ? 'bg-navy text-white'
          : 'border border-navy/15 bg-white text-navy hover:border-navy/40'
      }`}
    >
      {children}
    </button>
  )
}

// ---------------------------------------------------------------------------
// Formulir
// ---------------------------------------------------------------------------

export const GAYA_INPUT =
  'w-full rounded-lg border-2 border-navy/15 bg-white px-4 py-2.5 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-navy/50'

/**
 * Kolom formulir.
 *
 * Menyatukan label, petunjuk, dan pesan galat dalam satu komponen supaya
 * keterkaitan `htmlFor`/`aria-describedby` tidak pernah terlupa di halaman
 * mana pun.
 */
export function Kolom({
  label,
  htmlFor,
  petunjuk,
  galat,
  wajib,
  children,
}: {
  label: string
  htmlFor: string
  petunjuk?: string
  galat?: string
  wajib?: boolean
  children: ReactNode
}) {
  return (
    <div>
      <label htmlFor={htmlFor} className="block text-sm font-semibold text-navy">
        {label}
        {wajib && (
          <span className="ml-1 text-rose-600" aria-hidden="true">
            *
          </span>
        )}
      </label>

      {petunjuk && (
        <p id={`${htmlFor}-petunjuk`} className="mt-0.5 text-xs text-slate-500">
          {petunjuk}
        </p>
      )}

      <div className="mt-1.5">{children}</div>

      {galat && (
        <p id={`${htmlFor}-galat`} role="alert" className="mt-1.5 text-sm text-rose-700">
          {galat}
        </p>
      )}
    </div>
  )
}

/** Pemberitahuan hasil aksi. */
export function Pemberitahuan({
  jenis,
  children,
}: {
  jenis: 'info' | 'sukses' | 'galat'
  children: ReactNode
}) {
  const gaya = {
    info: 'bg-navy/5 text-navy border-navy/15',
    sukses: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    galat: 'bg-rose-50 text-rose-800 border-rose-200',
  }[jenis]

  return (
    <p role="alert" className={`rounded-lg border px-4 py-3 text-sm ${gaya}`}>
      {children}
    </p>
  )
}
