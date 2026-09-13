import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type AnchorHTMLAttributes,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type Ref,
  type ReactNode,
  type TextareaHTMLAttributes,
} from 'react'
import { createPortal } from 'react-dom'
import { Check, ChevronDown, Pencil, Trash2, type LucideIcon } from 'lucide-react'
import { DialogKonfirmasi } from '@/Components/Admin/Dialog'

/**
 * Elemen formulir bersama untuk seluruh halaman CMS.
 *
 * Tujuannya bukan sekadar keseragaman tampilan, melainkan memastikan setiap
 * input punya <label> yang tertaut dan galat validasi yang terhubung lewat
 * aria-describedby — syarat aksesibilitas PRD 12.4 yang mudah terlewat bila
 * setiap halaman menulis markup formulirnya sendiri.
 */

export function Kolom({
  label,
  htmlFor,
  galat,
  petunjuk,
  children,
}: {
  label: string
  htmlFor: string
  galat?: string
  petunjuk?: string
  children: ReactNode
}) {
  return (
    <div>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-900">
        {label}
      </label>
      {petunjuk && (
        <p id={`${htmlFor}-petunjuk`} className="mt-0.5 text-xs text-slate-500">
          {petunjuk}
        </p>
      )}
      <div className="mt-1">{children}</div>
      {galat && (
        <p id={`${htmlFor}-galat`} role="alert" className="mt-1 text-sm text-red-600">
          {galat}
        </p>
      )}
    </div>
  )
}

const GAYA_INPUT =
  'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 disabled:bg-slate-50'

export function Input({ galat, ...props }: InputHTMLAttributes<HTMLInputElement> & { galat?: string }) {
  return (
    <input
      {...props}
      aria-invalid={galat ? 'true' : undefined}
      aria-describedby={galat ? `${props.id}-galat` : undefined}
      className={`${GAYA_INPUT} ${galat ? 'border-red-400' : ''}`}
    />
  )
}

export function TextArea({
  galat,
  ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement> & { galat?: string }) {
  return (
    <textarea
      {...props}
      aria-invalid={galat ? 'true' : undefined}
      aria-describedby={galat ? `${props.id}-galat` : undefined}
      className={`${GAYA_INPUT} ${galat ? 'border-red-400' : ''}`}
    />
  )
}

export interface OpsiPilihan {
  value: string
  label: string
}

/**
 * Dropdown bergaya sendiri, pengganti `<select>` bawaan peramban.
 *
 * `<select>` asli tidak bisa diberi sudut membulat atau status hover pada
 * daftar pilihannya — bagian itu digambar sistem operasi, bukan CSS situs.
 * Padanan CMS dari `Pilihan` pada `Components/ui` (situs publik); dipisah
 * karena dua area memakai bahasa desain warna yang berbeda (teal/slate di
 * sini, navy/emas di publik), bukan karena perilakunya beda.
 *
 * Panel pilihannya dirender lewat portal ke `document.body`, posisinya
 * dihitung dari posisi tombol. Tanpa ini, dropdown yang dipasang di dalam
 * kontainer `overflow-x-auto` (tabel yang bisa digulir ke samping — banyak
 * dipakai di layar CMS ini) akan terpotong.
 */
export function Pilihan({
  id,
  value,
  onChange,
  options,
  placeholder = 'Pilih…',
  disabled = false,
  galat,
  ariaLabel,
}: {
  id: string
  value: string
  onChange: (value: string) => void
  options: OpsiPilihan[]
  placeholder?: string
  disabled?: boolean
  galat?: string
  /** Untuk dropdown tanpa `<label>` terlihat, mis. di dalam sel tabel. */
  ariaLabel?: string
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
        aria-label={ariaLabel}
        aria-invalid={galat ? 'true' : undefined}
        aria-describedby={galat ? `${id}-galat` : undefined}
        aria-activedescendant={
          terbuka && options[indexAktif] ? `${id}-opsi-${indexAktif}` : undefined
        }
        className={`${GAYA_INPUT} flex items-center justify-between gap-2 text-left disabled:cursor-not-allowed ${galat ? 'border-red-400' : ''}`}
      >
        <span className={`truncate ${terpilih ? 'text-slate-900' : 'text-slate-400'}`}>
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
            className="fixed z-50 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1.5 shadow-lg"
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
                  className={`flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm transition ${
                    i === indexAktif ? 'bg-teal-50' : ''
                  } ${dipilih ? 'font-semibold text-teal-700' : 'text-slate-700'}`}
                >
                  {opsi.label}
                  {dipilih && (
                    <Check className="size-4 shrink-0 text-teal-700" aria-hidden="true" />
                  )}
                </li>
              )
            })}
          </ul>,
          document.body,
        )}
    </>
  )
}

export function Tombol({
  variasi = 'utama',
  ...props
}: InputHTMLAttributes<HTMLButtonElement> & { variasi?: 'utama' | 'sekunder' | 'bahaya' }) {
  const gaya = {
    utama: 'bg-teal-600 text-white shadow-sm hover:bg-teal-700',
    sekunder: 'border border-slate-300 text-slate-700 hover:bg-slate-50',
    bahaya: 'border border-red-300 text-red-700 hover:bg-red-50',
  }[variasi]

  return (
    <button
      {...(props as object)}
      className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition disabled:opacity-60 ${gaya}`}
    />
  )
}

// ---------------------------------------------------------------------------
// Aksi berbentuk ikon
// ---------------------------------------------------------------------------

/**
 * Bentuk baku seluruh aksi berikon di dashboard.
 *
 * Ukurannya (ikon 16px + padding 6px) disamakan supaya deretan aksi pada dua
 * layar berbeda tetap sebaris tinggi, dan cukup lebar untuk disentuh di
 * tablet — perangkat yang dipakai sebagian operator desa.
 */
const DASAR_IKON =
  'inline-flex shrink-0 items-center justify-center rounded-md p-1.5 transition disabled:cursor-not-allowed disabled:opacity-50'

const WARNA_IKON = {
  netral: 'text-slate-500 hover:bg-slate-100 hover:text-teal-700',
  utama: 'text-teal-700 hover:bg-teal-50',
  peringatan: 'text-amber-700 hover:bg-amber-50',
  bahaya: 'text-slate-500 hover:bg-red-50 hover:text-red-600',
} as const

interface PropsIkon {
  ikon: LucideIcon
  /**
   * Nama aksi bagi pembaca layar. WAJIB, dan sebaiknya memuat nama barisnya:
   * tombol tanpa teks tidak punya nama lain, dan "Hapus" tanpa objek tidak
   * memberi tahu apa yang akan terhapus (PRD 12.4).
   */
  label: string
  /** Tooltip, bila `label` terlalu panjang untuk ditampilkan apa adanya. */
  judul?: string
  gaya?: keyof typeof WARNA_IKON
  className?: string
}

/** Aksi berikon — pengganti tautan teks "Ubah"/"Hapus"/"Unduh" di tiap layar. */
export function TombolIkon({
  ikon: Ikon,
  label,
  judul,
  gaya = 'netral',
  className = '',
  ...props
}: PropsIkon & ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      type="button"
      {...props}
      title={judul ?? label}
      aria-label={label}
      className={`${DASAR_IKON} ${WARNA_IKON[gaya]} ${className}`}
    >
      <Ikon className="size-4" aria-hidden="true" />
    </button>
  )
}

/** Padanan `TombolIkon` untuk aksi yang berupa tautan (unduh berkas, buka tab baru). */
export function TautanIkon({
  ikon: Ikon,
  label,
  judul,
  gaya = 'netral',
  className = '',
  ...props
}: PropsIkon & AnchorHTMLAttributes<HTMLAnchorElement>) {
  return (
    <a
      {...props}
      title={judul ?? label}
      aria-label={label}
      className={`${DASAR_IKON} ${WARNA_IKON[gaya]} ${className}`}
    >
      <Ikon className="size-4" aria-hidden="true" />
    </a>
  )
}

/**
 * Aksi pada satu baris daftar: sunting & hapus.
 *
 * Sebelumnya tiap layar menulis sendiri dua tautan teks ("Sunting", "Hapus")
 * dengan gaya yang berbeda-beda — dan sebagian layar tidak punya keduanya
 * sama sekali. Satu komponen membuat letak, warna, dan ikonnya sama di seluruh
 * dashboard, sekaligus memastikan hal yang paling mudah terlupa pada tombol
 * berisi ikon saja: NAMA yang terbaca pembaca layar. `aria-label`-nya memuat
 * nama barisnya, sehingga "Hapus" tidak terdengar sebagai perintah tanpa objek.
 */
export function AksiBaris({
  nama,
  onSunting,
  onHapus,
  sedangProses = false,
  pesanHapus,
  anak,
}: {
  /** Nama baris — dipakai pada label aksesibilitas & konfirmasi hapus. */
  nama: string
  onSunting?: () => void
  onHapus?: () => void
  sedangProses?: boolean
  /**
   * Keterangan pada dialog konfirmasi hapus. Diisi bila akibatnya tidak
   * sesederhana "data hilang" — mis. baris yang dinonaktifkan alih-alih
   * dihapus, atau penghapusan yang dapat ditolak server.
   */
  pesanHapus?: string
  /**
   * Aksi berikon lain milik baris ini (unduh, buka, lihat di situs publik),
   * ditempatkan sebelum Sunting. Lewat sini, bukan di samping komponen,
   * supaya seluruh aksi satu baris berbagi satu jarak antar-ikon.
   */
  anak?: ReactNode
}) {
  const [tanyaHapus, setTanyaHapus] = useState(false)

  return (
    <div className="flex items-center justify-end gap-1">
      {anak}

      {onSunting && (
        <TombolIkon
          ikon={Pencil}
          label={`Sunting ${nama}`}
          judul="Sunting"
          onClick={onSunting}
          disabled={sedangProses}
        />
      )}

      {onHapus && (
        <>
          <TombolIkon
            ikon={Trash2}
            gaya="bahaya"
            label={`Hapus ${nama}`}
            judul="Hapus"
            onClick={() => setTanyaHapus(true)}
            disabled={sedangProses}
          />

          {/*
            Konfirmasi dipasang di sini, bukan di tiap pemanggil: penghapusan
            pada dashboard ini tidak punya pembatalan. Karena komponen ini
            dipakai hampir seluruh layar CMS, memasangnya di sini pula yang
            membuat seluruhnya beralih dari `confirm()` bawaan peramban
            sekaligus.
          */}
          <DialogKonfirmasi
            terbuka={tanyaHapus}
            judul={`Hapus ${nama}?`}
            pesan={pesanHapus ?? 'Data yang dihapus tidak dapat dikembalikan.'}
            onBatal={() => setTanyaHapus(false)}
            onKonfirmasi={() => {
              setTanyaHapus(false)
              onHapus()
            }}
          />
        </>
      )}
    </div>
  )
}

/**
 * Menggulirkan formulir ke pandangan saat tombol "Sunting" ditekan.
 *
 * Pada layar CMS, formulir berada di ATAS daftarnya. Menekan Sunting pada
 * baris ke-30 karena itu mengisi formulir yang berada jauh di luar layar:
 * tidak ada yang berubah di hadapan operator, dan ia menekan tombol itu
 * berkali-kali menyangka tidak berfungsi.
 *
 * `ref` dipasang pada kartu formulir (lewat prop `wadahRef` milik `Kartu`),
 * `gulir()` dipanggil di dalam penangan Sunting. Dipakai bersama, bukan
 * disalin sebagai `window.scrollTo({ top: 0 })` seperti layar-layar terdahulu:
 * menggulir ke PUNCAK halaman hanya kebetulan benar selama formulirnya elemen
 * pertama.
 */
export function useGulirKeForm<T extends HTMLElement = HTMLElement>() {
  const ref = useRef<T>(null)

  /*
   * Digulir SATU FRAME setelah dipanggil, bukan seketika.
   *
   * Sebagian layar memunculkan formulirnya pada saat yang sama dengan menekan
   * tombol sunting — `{formTerbuka && <Kartu …>}` pada Penduduk dan Akun
   * Operator. Pada detik `gulir()` dipanggil, React belum me-render ulang,
   * sehingga `ref.current` masih null dan gulirnya diam-diam tidak terjadi.
   * Menunggu satu frame membuat pemanggilnya tetap satu baris, tanpa perlu
   * menyiapkan useEffect sendiri-sendiri.
   *
   * `useCallback` menjaga identitasnya tetap, supaya boleh menjadi dependensi
   * useEffect — dipakai formulir yang MENGGANTIKAN daftarnya (Berita &
   * penerima bansos), yang menggulir dirinya sendiri sekali saat muncul.
   */
  const gulir = useCallback(() => {
    requestAnimationFrame(() => {
      ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  }, [])

  return { ref, gulir }
}

export function Kartu({
  judul,
  ikon: Ikon,
  anak,
  wadahRef,
}: {
  judul?: string
  ikon?: LucideIcon
  anak: ReactNode
  /**
   * Untuk menggulirkan kartu ini ke pandangan — lihat `useGulirKeForm`.
   * Disediakan di sini supaya pemakainya tidak perlu membungkus kartu dengan
   * `<div>` tambahan hanya demi menempelkan ref.
   */
  wadahRef?: Ref<HTMLElement>
}) {
  return (
    <section
      ref={wadahRef}
      className="scroll-mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
    >
      {judul && (
        <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-100 px-6 py-4">
          {Ikon && <Ikon className="size-5 shrink-0 text-teal-700" aria-hidden="true" />}
          <h2 className="text-lg font-semibold tracking-tight text-slate-900">{judul}</h2>
        </div>
      )}
      <div className="p-6">{anak}</div>
    </section>
  )
}

/** Notifikasi hasil aksi — sukses atau gagal. */
export function Pemberitahuan({ jenis, pesan }: { jenis: 'sukses' | 'galat'; pesan: string }) {
  return (
    <p
      role="alert"
      className={`rounded-lg px-3 py-2 text-sm ${
        jenis === 'sukses' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'
      }`}
    >
      {pesan}
    </p>
  )
}
