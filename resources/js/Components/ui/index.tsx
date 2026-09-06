import { useEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { Link } from '@inertiajs/react'
import { Check, ChevronDown, Download, Eye, type LucideIcon } from 'lucide-react'
import { useHalaman } from '@/types/inertia'

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
 * Gutter kiri-kanan — SATU nilai untuk seluruh situs.
 *
 * Dulu setiap pembungkus menulis `px-6` apa pun ukuran layarnya: pada ponsel
 * 24px memakan lebar yang sudah sempit, sedangkan pada monitor lebar isi
 * halaman berhenti tepat di tepi kotak tanpa napas. Nilainya kini menanjak
 * mengikuti layar, dan karena dipakai bersama header, isi, serta footer,
 * ketiganya berbagi garis tepi yang sama.
 *
 * Angka terbesarnya (88px mulai 1536px) diturunkan dari situs desa yang
 * dijadikan acuan pemilik produk: pada monitor 1902px, isinya bermula di 90px
 * dari tepi kiri — sekitar 4,7% lebar layar.
 */
const GUTTER = 'px-5 sm:px-6 lg:px-10 xl:px-16 2xl:px-22'

/**
 * Pembungkus kerangka: lebar situs + gutter.
 *
 * Dipakai langsung (sebagai kelas) oleh bagian yang bukan komponen React
 * tersendiri — bilah atas, navigasi, dan footer pada `LayoutPublik`.
 *
 * Perhatikan `max-w-situs` bernilai 120rem/1920px, bukan lebar "nyaman baca":
 * halaman sengaja MENGIKUTI lebar layar dengan margin tepi tipis, bukan
 * berhenti di 1280px lalu memusat — pada monitor 1920px, cara lama menyisakan
 * 320px kosong di kiri dan kanan. Batas 1920px tetap ada supaya pada layar
 * ultrawide barisan kartu tidak melar tanpa henti.
 */
export const GAYA_WADAH = `mx-auto w-full max-w-situs ${GUTTER}`

/**
 * Ketiga lebar kolom yang boleh dipakai halaman publik.
 *
 * Kolom yang lebih sempit dari kerangka DIPUSATKAN (`mx-auto` pada pemakainya).
 * Sempat dicoba rata kiri agar tepinya segaris dengan logo, tetapi pada monitor
 * lebar hasilnya justru timpang: formulir selebar 736px menempel ke kiri dan
 * menyisakan ±1000px kosong di kanan.
 *
 * Yang membuat pemusatan ini tetap rapi — dan inilah bagian yang mudah
 * terlewat — `KepalaHalaman` menerima lebar yang SAMA dengan `IsiHalaman` di
 * bawahnya. Tanpa itu, judul tetap menempel ke kiri sementara isinya di tengah,
 * dan setiap halaman punya dua tepi kiri yang berbeda.
 */
const LEBAR = {
  /** Teks panjang & formulir — panjang baris tetap nyaman dibaca. */
  sempit: 'max-w-baca',
  /** Daftar pendek yang akan terlihat melar bila dibiarkan selebar situs. */
  sedang: 'max-w-sedang',
  /** Kartu, tabel, grafik — selebar kerangka. */
  lebar: 'max-w-full',
} as const

/**
 * Kepala halaman bergaya institusional: latar navy dengan garis emas.
 *
 * Dipakai di puncak SETIAP halaman publik, tanpa kecuali. Sebelumnya sebagian
 * halaman memakai bilah ini sementara yang lain menaruh judul terpusat di
 * dalam isi, dan sisanya cuma `<h1>` polos — tiga pola berbeda yang membuat
 * pengunjung kehilangan pegangan tiap kali berpindah halaman. Judulnya rata
 * kiri pada garis yang sama dengan logo di navigasi.
 */
export function KepalaHalaman({
  eyebrow,
  judul,
  deskripsi,
  aksi,
  kembali,
  meta,
  lebar = 'lebar',
}: {
  eyebrow?: string
  judul: string
  deskripsi?: string
  aksi?: ReactNode
  /** Tautan kembali ke daftar induk — dipakai halaman detail. */
  kembali?: { ke: string; label: string }
  /** Baris keterangan di bawah judul (tanggal, penulis, jumlah dibaca). */
  meta?: ReactNode
  /**
   * WAJIB sama dengan `lebar` pada `IsiHalaman` halaman ini. Bilah navy tetap
   * membentang penuh; yang mengikuti lebar kolom hanyalah teks di dalamnya,
   * supaya judul dan isi berbagi satu tepi kiri.
   */
  lebar?: keyof typeof LEBAR
}) {
  return (
    <header className="border-b-4 border-gold bg-navy">
      <div className={`${GAYA_WADAH} py-8 sm:py-10 lg:py-12`}>
        <div className={`mx-auto ${LEBAR[lebar]}`}>
          {kembali && (
            <Link
              href={kembali.ke}
              className="mb-5 inline-flex items-center gap-1.5 text-sm font-semibold text-white/60 transition hover:text-gold"
            >
              <span aria-hidden="true">&larr;</span>
              {kembali.label}
            </Link>
          )}

          {eyebrow && (
            <p className="mb-2 text-xs font-semibold tracking-[0.12em] text-gold uppercase">
              {eyebrow}
            </p>
          )}

          <div className="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div className="max-w-2xl">
              <h1 className="font-heading text-2xl leading-tight font-bold text-white sm:text-3xl lg:text-4xl">
                {judul}
              </h1>
              {deskripsi && (
                <p className="mt-2.5 text-sm leading-relaxed text-white/75 sm:text-base">
                  {deskripsi}
                </p>
              )}
              {meta && (
                <div className="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-white/55">
                  {meta}
                </div>
              )}
            </div>
            {aksi}
          </div>
        </div>
      </div>
    </header>
  )
}

/**
 * Pembungkus isi halaman.
 *
 * Jarak atas-bawahnya menanjak mengikuti layar dan menjadi SATU-SATUNYA jarak
 * vertikal di ujung halaman — footer tidak menambah marginnya sendiri, supaya
 * ruang di antara keduanya tidak terhitung dua kali.
 */
export function IsiHalaman({
  children,
  lebar = 'sedang',
}: {
  children: ReactNode
  lebar?: keyof typeof LEBAR
}) {
  return (
    <div className={`${GAYA_WADAH} py-10 sm:py-12 lg:py-14`}>
      <div className={`mx-auto ${LEBAR[lebar]}`}>{children}</div>
    </div>
  )
}

export interface ItemTab {
  ke: string
  label: string
  ikon?: LucideIcon
  /** Cocok persis saja — dipakai item "Beranda" yang kalau tidak akan menyala di seluruh sub-halaman. */
  ujung?: boolean
  /** Jalur lain yang ikut menyalakan tab ini (mis. tiga jenis informasi PPID di balik satu tab). */
  jalurLain?: string[]
}

/**
 * Sub-navigasi modul — dipakai Infografis dan PPID.
 *
 * Dulu keduanya menggambar bilahnya sendiri dengan bentuk yang berbeda: satu
 * memakai pil bulat terpusat, satunya tab bergaris bawah rata kiri. Pengunjung
 * yang berpindah dari Infografis ke PPID karena itu menghadapi dua bahasa
 * navigasi di situs yang sama. Bentuknya kini satu.
 *
 * Deretan pil ini BAGIAN DARI ISI HALAMAN, bukan bilah tersendiri yang
 * menempel di bawah header. Sempat dibuat lengket — dan hasilnya dua batang
 * navigasi bertumpuk begitu halaman digulir: navigasi situs di atas, bilah
 * modul berlatar sendiri tepat di bawahnya. Dua batang itu memakan tinggi
 * layar ponsel dan membuat pengunjung ragu mana navigasi yang utama. Kini ia
 * menggulir bersama isinya, tanpa latar dan garis pemisah sendiri, sehingga
 * yang menetap di layar hanya satu navigasi.
 */
export function BilahTab({ items, label }: { items: ItemTab[]; label: string }) {
  const jalur = useHalaman().url.split('?')[0]

  return (
    <div className={`${GAYA_WADAH} pt-8 sm:pt-10`}>
      {/*
        Pil bulat berbingkai, terisi navy saat aktif, terpusat — bentuk asli
        pemilih dimensi Infografis, kini dipakai PPID juga.

        Gulir mendatar tetap dipertahankan: enam pil berlabel penuh tidak muat
        sebaris pada ponsel, dan membiarkannya membungkus ke baris kedua
        membuat tinggi baris ini berubah-ubah antar halaman.

        Pemusatannya lewat `w-max mx-auto` pada lapisan dalam, BUKAN
        `justify-center` pada kotak yang menggulir. Margin auto hanya membagi
        ruang yang berlebih, sehingga ia menjadi nol begitu deretan pil lebih
        lebar dari layar; `justify-center` justru mendorong luapan ke kedua
        sisi dan membuat pil pertama tidak bisa dicapai betapapun digulir.
      */}
      <nav aria-label={label} className="gulir-mendatar -mx-1 overflow-x-auto px-1 py-1">
        <div className="mx-auto flex w-max gap-2 sm:gap-3">
          {items.map((tab) => {
            const aktif = tab.ujung
              ? jalur === tab.ke
              : jalur === tab.ke ||
                jalur.startsWith(`${tab.ke}/`) ||
                (tab.jalurLain?.includes(jalur) ?? false)

            return (
              <Link
                key={tab.ke}
                href={tab.ke}
                aria-current={aktif ? 'page' : undefined}
                className={`inline-flex shrink-0 items-center gap-2 rounded-full border-2 px-4 py-2 text-sm font-semibold whitespace-nowrap transition sm:px-5 sm:py-2.5 ${
                  aktif
                    ? 'border-navy bg-navy text-white'
                    : 'border-navy/20 bg-white text-navy hover:border-navy/40'
                }`}
              >
                {tab.ikon && <tab.ikon className="size-4 shrink-0" aria-hidden="true" />}
                {tab.label}
              </Link>
            )
          })}
        </div>
      </nav>
    </div>
  )
}

/** Judul seksi dengan garis aksen emas. */
export function JudulSeksi({
  children,
  keterangan,
  aksi,
  id,
}: {
  children: ReactNode
  keterangan?: string
  /** Tautan pendamping di ujung kanan, mis. "Lihat semua". */
  aksi?: ReactNode
  id?: string
}) {
  return (
    <div className="mb-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
      <div>
        <h2
          id={id}
          className="font-heading border-l-[3px] border-gold pl-3 text-lg font-bold text-navy sm:text-xl lg:text-2xl"
        >
          {children}
        </h2>
        {keterangan && <p className="mt-2 max-w-2xl pl-3 text-sm text-slate-600">{keterangan}</p>}
      </div>
      {aksi}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Permukaan
// ---------------------------------------------------------------------------

/**
 * Kartu putih — permukaan dasar seluruh konten.
 *
 * Sudutnya `rounded-xl` dan bayangannya tipis. Sebelumnya `rounded-2xl` +
 * `hover:shadow-lg`: pada halaman berisi dua belas kartu, sudut selebar itu
 * dan bayangan yang mengembang saat disentuh membuat daftar terasa seperti
 * tumpukan kartu permainan, bukan dokumen resmi. Perubahan keadaannya kini
 * lewat garis tepi — lebih tenang, dan tetap jelas terbaca sebagai "dapat
 * ditekan".
 */
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
      className={`rounded-xl border border-navy/10 bg-white shadow-sm ${
        interaktif ? 'transition duration-200 hover:border-navy/25 hover:shadow-md' : ''
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
  utama: 'bg-gold text-navy hover:bg-gold-light font-heading font-bold rounded-full',
  // Aksi sekunder pada latar terang.
  navy: 'bg-navy text-white hover:bg-navy-light font-semibold rounded-lg',
  // Aksi tersier / batal.
  garis: 'border-2 border-navy/20 bg-white text-navy hover:border-navy/40 font-semibold rounded-lg',
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


/**
 * Pasangan aksi untuk satu berkas PDF publik: melihat & mengunduh.
 *
 * Dipakai di seluruh halaman PPID (dasar hukum, daftar informasi, dan dokumen
 * balasan permohonan), karena itu ia tinggal di sini alih-alih disalin tiga
 * kali.
 *
 * Kedua tombol menunjuk URL yang SAMA; yang membedakan hanya atribut
 * `download`. Sebelumnya hanya ada satu tautan berlabel "Unduh" yang
 * sebenarnya membuka berkas di tab baru — peramban menyajikan PDF secara
 * inline selama tidak diminta menyimpannya. Labelnya menjanjikan hal yang
 * tidak dilakukannya, dan tidak ada jalan menyimpan berkas tanpa melewati
 * penampil PDF lebih dulu.
 *
 * `download` hanya berlaku untuk sumber se-origin. Itu terpenuhi di sini:
 * URL-nya dibangun `asset()` dari APP_URL, bukan menunjuk penyimpanan luar.
 *
 * Nama dokumen ikut sebagai teks tersembunyi. Tanpa itu, pembaca layar pada
 * halaman berisi belasan berkas hanya mendengar "Lihat, Unduh, Lihat, Unduh…"
 * tanpa tahu milik dokumen yang mana.
 */
export function AksiBerkasPdf({
  file,
  nama,
  labelUnduh = 'Unduh',
  className = '',
}: {
  file: string
  /** Judul dokumen — untuk label aksesibilitas, tidak ditampilkan. */
  nama: string
  labelUnduh?: string
  className?: string
}) {
  const gaya =
    'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold transition'

  return (
    <div className={`flex flex-wrap items-center gap-2 ${className}`}>
      <a
        href={file}
        target="_blank"
        rel="noopener noreferrer"
        className={`${gaya} bg-navy text-white hover:bg-navy-light`}
      >
        <Eye className="size-4" aria-hidden="true" />
        Lihat
        <span className="sr-only">dokumen {nama}</span>
      </a>

      <a
        href={file}
        download
        className={`${gaya} border-2 border-navy/15 text-navy hover:border-navy/35`}
      >
        <Download className="size-4" aria-hidden="true" />
        {labelUnduh}
        <span className="sr-only">dokumen {nama}</span>
      </a>
    </div>
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
            style={{
              top: posisi.top + 6,
              left: posisi.left,
              width: posisi.width,
            }}
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
