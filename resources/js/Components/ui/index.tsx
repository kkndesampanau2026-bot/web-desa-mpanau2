import { useEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { Link } from '@inertiajs/react'
import { Check, ChevronDown } from 'lucide-react'

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
      <div className="mx-auto max-w-situs px-6 py-12 sm:py-14">
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
    lebar: 'max-w-situs',
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

export interface OpsiPilihan {
  value: string
  label: string
}

/**
 * Dropdown bergaya sendiri, pengganti `<select>` bawaan peramban.
 *
 * `<select>` asli tidak bisa diberi sudut membulat maupun status hover pada
 * daftar pilihannya — bagian itu digambar sistem operasi, bukan CSS situs.
 * Mengikuti pola WAI-ARIA "listbox button": tombol berperan sebagai
 * `combobox`, panelnya `listbox`, dan navigasi keyboard (panah, Enter, Esc,
 * Home/End) berjalan tanpa fokus asli berpindah ke tiap opsi.
 *
 * Form ini memakai `noValidate` (validasi ditegakkan server), sehingga tidak
 * ada validasi HTML5 bawaan `<select required>` yang hilang akibat penggantian
 * ini.
 *
 * Panel pilihannya dirender lewat portal ke `document.body`, posisinya
 * dihitung dari posisi tombol. Tanpa ini, dropdown yang dipasang di dalam
 * kontainer `overflow-x-auto` (tabel yang bisa digulir ke samping) akan
 * terpotong — `<select>` asli tidak kena masalah ini karena daftarnya
 * digambar sebagai overlay level sistem operasi, di luar tata letak halaman.
 */
export function Pilihan({
  id,
  value,
  onChange,
  options,
  placeholder = 'Pilih…',
  disabled = false,
  className,
}: {
  id: string
  value: string
  onChange: (value: string) => void
  options: OpsiPilihan[]
  placeholder?: string
  disabled?: boolean
  /**
   * Menggantikan gaya tombol bawaan sepenuhnya (bukan ditambahkan) — dipakai
   * pada kasus langka bentuknya berbeda dari kolom formulir biasa, mis.
   * dropdown berbentuk pil pada pemilih tahun infografis. Diganti, bukan
   * digabung, supaya tidak ada dua utilitas warna latar yang saling
   * berebutan seperti pada kelas Tailwind yang dikompilasi.
   */
  className?: string
}) {
  const [terbuka, setTerbuka] = useState(false)
  const [indexAktif, setIndexAktif] = useState(0)
  const [posisi, setPosisi] = useState({ top: 0, left: 0, width: 0 })
  const tombolRef = useRef<HTMLButtonElement>(null)
  const panelRef = useRef<HTMLUListElement>(null)

  const terpilih = options.find((o) => o.value === value) ?? null

  useEffect(() => {
    if (!terbuka) return

    function tutupJikaDiLuar(e: MouseEvent) {
      const target = e.target as Node
      if (tombolRef.current?.contains(target)) return
      if (panelRef.current?.contains(target)) return
      setTerbuka(false)
    }

    /*
     * Panel ditutup saat HALAMAN digulir, karena posisinya dihitung sekali
     * saat dibuka dan tidak ikut bergerak mengikuti tombol.
     *
     * Gulir DI DALAM panel dikecualikan. Listener ini memakai fase capture
     * supaya gulir dari kontainer mana pun ikut tertangkap (mis. tabel yang
     * dapat digulir), dan tanpa pengecualian ini daftar pilihan yang panjang
     * tertutup tepat pada saat pengguna mencoba menggulirnya.
     */
    function tutupSaatGulir(e: Event) {
      const target = e.target
      if (target instanceof Node && panelRef.current?.contains(target)) return
      setTerbuka(false)
    }

    document.addEventListener('mousedown', tutupJikaDiLuar)
    window.addEventListener('scroll', tutupSaatGulir, true)
    window.addEventListener('resize', tutupSaatGulir)

    return () => {
      document.removeEventListener('mousedown', tutupJikaDiLuar)
      window.removeEventListener('scroll', tutupSaatGulir, true)
      window.removeEventListener('resize', tutupSaatGulir)
    }
  }, [terbuka])

  // Sorotan panah keyboard harus ikut menggulirkan panel; kalau tidak,
  // sorotan berpindah ke opsi yang berada di luar pandangan.
  useEffect(() => {
    if (!terbuka) return
    document.getElementById(`${id}-opsi-${indexAktif}`)?.scrollIntoView({ block: 'nearest' })
  }, [terbuka, indexAktif, id])

  function buka() {
    if (disabled) return
    const r = tombolRef.current?.getBoundingClientRect()
    if (r) setPosisi({ top: r.bottom, left: r.left, width: r.width })
    const i = options.findIndex((o) => o.value === value)
    setIndexAktif(i >= 0 ? i : 0)
    setTerbuka(true)
  }

  function pindahIndex(i: number) {
    setIndexAktif(Math.max(0, Math.min(options.length - 1, i)))
  }

  function pilih(i: number) {
    if (options[i]) onChange(options[i].value)
    setTerbuka(false)
  }

  function tanganiKey(e: React.KeyboardEvent) {
    if (disabled) return

    if (!terbuka) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
        e.preventDefault()
        buka()
      }
      return
    }

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault()
        pindahIndex(indexAktif + 1)
        break
      case 'ArrowUp':
        e.preventDefault()
        pindahIndex(indexAktif - 1)
        break
      case 'Home':
        e.preventDefault()
        pindahIndex(0)
        break
      case 'End':
        e.preventDefault()
        pindahIndex(options.length - 1)
        break
      case 'Enter':
      case ' ':
        e.preventDefault()
        pilih(indexAktif)
        break
      case 'Escape':
        e.preventDefault()
        setTerbuka(false)
        break
      case 'Tab':
        setTerbuka(false)
        break
    }
  }

  return (
    <>
      <button
        ref={tombolRef}
        type="button"
        id={id}
        disabled={disabled}
        onClick={() => (terbuka ? setTerbuka(false) : buka())}
        onKeyDown={tanganiKey}
        role="combobox"
        aria-expanded={terbuka}
        aria-haspopup="listbox"
        aria-controls={`${id}-listbox`}
        aria-activedescendant={
          terbuka && options[indexAktif] ? `${id}-opsi-${indexAktif}` : undefined
        }
        className={
          className ??
          `${GAYA_INPUT} flex items-center justify-between gap-2 text-left disabled:cursor-not-allowed disabled:opacity-60`
        }
      >
        <span className={`truncate ${terpilih ? 'text-slate-800' : 'text-slate-400'}`}>
          {terpilih ? terpilih.label : placeholder}
        </span>
        <ChevronDown
          className={`size-4 shrink-0 text-slate-400 transition-transform ${terbuka ? 'rotate-180' : ''}`}
          aria-hidden="true"
        />
      </button>

      {terbuka &&
        createPortal(
          <ul
            ref={panelRef}
            id={`${id}-listbox`}
            role="listbox"
            aria-labelledby={id}
            style={{ top: posisi.top + 6, left: posisi.left, width: posisi.width }}
            className="fixed z-50 max-h-64 overflow-y-auto rounded-xl border border-navy/10 bg-white py-1.5 shadow-lg"
          >
            {options.map((opsi, i) => {
              const dipilih = opsi.value === value

              return (
                <li
                  key={opsi.value}
                  id={`${id}-opsi-${i}`}
                  role="option"
                  aria-selected={dipilih}
                  onMouseEnter={() => setIndexAktif(i)}
                  onClick={() => pilih(i)}
                  className={`flex cursor-pointer items-center justify-between gap-2 px-4 py-2 text-sm transition ${
                    i === indexAktif ? 'bg-navy/5' : ''
                  } ${dipilih ? 'font-semibold text-navy' : 'text-slate-700'}`}
                >
                  {opsi.label}
                  {dipilih && <Check className="size-4 shrink-0 text-navy" aria-hidden="true" />}
                </li>
              )
            })}
          </ul>,
          document.body,
        )}
    </>
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
