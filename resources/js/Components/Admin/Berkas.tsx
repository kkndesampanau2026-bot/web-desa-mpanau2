import { useEffect, useId, useRef, useState, type ChangeEvent } from 'react'
import { urlBerkas } from '@/lib/api'
import {
  aturanBerkas,
  MAKS_FOTO_SEKALI_UNGGAH,
  periksaBerkas,
  ukuranTerbaca,
  type JenisBerkas,
} from '@/lib/berkas'

/**
 * Input berkas untuk seluruh halaman CMS.
 *
 * Dua hal yang mudah keliru dan sengaja ditangani di sini:
 *
 * 1. Membiarkan kolom berkas kosong berarti "jangan ubah", BUKAN "hapus".
 *    Komponen menampilkan berkas yang sedang terpasang agar operator melihat
 *    bahwa fotonya tetap ada meski ia tidak memilih berkas baru.
 * 2. Pratinjau dibuat lewat `URL.createObjectURL`, yang menahan berkas di
 *    memori sampai dicabut. Tanpa `revokeObjectURL`, memilih-ulang foto puluhan
 *    kali dalam satu sesi CMS membocorkan memori sebesar berkas-berkasnya.
 */

/** Ikon garis sederhana — proyek ini tidak memuat pustaka ikon. */
function IkonUnggah({ className = '' }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
    >
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <path d="M17 8l-5-5-5 5" />
      <path d="M12 3v12" />
    </svg>
  )
}

function IkonDokumen({ className = '' }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
    >
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
      <path d="M14 2v6h6" />
    </svg>
  )
}

function IkonSilang({ className = '' }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      aria-hidden="true"
      className={className}
    >
      <path d="M18 6 6 18M6 6l12 12" />
    </svg>
  )
}

/**
 * Tautan ke berkas yang sudah tersimpan di server.
 *
 * Dibuka di tab baru: operator kerap memeriksa isi PDF di tengah pengisian
 * formulir, dan menavigasi tab yang sama akan membuang seluruh isian.
 */
export function TautanBerkas({
  path,
  label = 'Lihat berkas',
}: {
  path: string | null | undefined
  label?: string
}) {
  const url = urlBerkas(path)

  if (!url) return null

  return (
    <a
      href={url}
      target="_blank"
      rel="noopener noreferrer"
      className="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-600 underline hover:text-slate-900"
    >
      <IkonDokumen className="h-3.5 w-3.5" />
      {label}
    </a>
  )
}

/** Pratinjau berkas yang sedang dipilih atau sudah tersimpan. */
function Pratinjau({
  sumber,
  jenis,
  keterangan,
}: {
  sumber: string | null
  jenis: JenisBerkas
  keterangan: string
}) {
  if (jenis === 'dokumen' || !sumber) {
    return (
      <span className="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-400">
        <IkonDokumen className="h-6 w-6" />
      </span>
    )
  }

  return (
    <img
      src={sumber}
      alt={keterangan}
      className="h-16 w-16 shrink-0 rounded-lg border border-slate-200 object-cover"
    />
  )
}

export function InputBerkas({
  label,
  jenis,
  berkas,
  onPilih,
  pathTersimpan,
  petunjuk,
  galat,
  wajib = false,
}: {
  label: string
  jenis: JenisBerkas
  berkas: File | null
  onPilih: (berkas: File | null) => void
  /** Path berkas yang sudah tersimpan di server, bila ada. */
  pathTersimpan?: string | null
  petunjuk?: string
  /** Galat validasi dari server. */
  galat?: string
  wajib?: boolean
}) {
  const id = useId()
  const input = useRef<HTMLInputElement>(null)
  const [galatLokal, setGalatLokal] = useState<string | null>(null)
  const [pratinjau, setPratinjau] = useState<string | null>(null)

  const aturan = aturanBerkas(jenis)
  const urlTersimpan = urlBerkas(pathTersimpan)

  useEffect(() => {
    if (!berkas || jenis !== 'gambar') {
      setPratinjau(null)
      return
    }

    const url = URL.createObjectURL(berkas)
    setPratinjau(url)

    return () => URL.revokeObjectURL(url)
  }, [berkas, jenis])

  function pilih(e: ChangeEvent<HTMLInputElement>) {
    const dipilih = e.target.files?.[0] ?? null

    if (!dipilih) {
      setGalatLokal(null)
      onPilih(null)
      return
    }

    const masalah = periksaBerkas(dipilih, jenis)

    if (masalah) {
      setGalatLokal(masalah)
      onPilih(null)
      // Nilai input dikosongkan agar memilih berkas YANG SAMA sekali lagi
      // tetap memicu event change — jika tidak, operator yang mengecilkan
      // ukuran berkasnya lalu memilih ulang tidak akan mendapat respons apa pun.
      e.target.value = ''
      return
    }

    setGalatLokal(null)
    onPilih(dipilih)
  }

  function bersihkan() {
    setGalatLokal(null)
    onPilih(null)
    if (input.current) input.current.value = ''
  }

  const pesanGalat = galatLokal ?? galat

  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {label}
        {wajib && <span className="text-red-600"> *</span>}
      </label>

      <p id={`${id}-petunjuk`} className="mt-0.5 text-xs text-slate-500">
        {petunjuk ? `${petunjuk} ` : ''}
        {aturan.format}, maksimal {aturan.maksMb} MB.
        {pathTersimpan && ' Biarkan kosong bila tidak ingin mengganti.'}
      </p>

      <div className="mt-2 flex items-center gap-3">
        <Pratinjau
          sumber={pratinjau ?? urlTersimpan}
          jenis={jenis}
          keterangan={berkas ? `Pratinjau ${label}` : `${label} yang tersimpan`}
        />

        <div className="min-w-0 flex-1">
          <input
            ref={input}
            id={id}
            type="file"
            accept={aturan.accept}
            required={wajib && !berkas}
            onChange={pilih}
            aria-describedby={pesanGalat ? `${id}-galat` : `${id}-petunjuk`}
            aria-invalid={pesanGalat ? 'true' : undefined}
            className={`block w-full cursor-pointer rounded-lg border text-sm text-slate-600 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 ${
              pesanGalat ? 'border-red-400' : 'border-slate-300'
            }`}
          />

          <p className="mt-1 truncate text-xs text-slate-500">
            {berkas ? (
              <>
                <span className="font-medium text-slate-700">{berkas.name}</span>{' '}
                ({ukuranTerbaca(berkas.size)}) — belum diunggah
              </>
            ) : pathTersimpan ? (
              'Berkas saat ini akan dipertahankan.'
            ) : (
              'Belum ada berkas.'
            )}
          </p>

          {!berkas && <TautanBerkas path={pathTersimpan} label="Lihat berkas saat ini" />}
        </div>

        {berkas && (
          <button
            type="button"
            onClick={bersihkan}
            aria-label={`Batalkan pilihan ${label}`}
            className="shrink-0 rounded-lg border border-slate-300 p-2 text-slate-500 transition hover:bg-slate-50 hover:text-slate-800"
          >
            <IkonSilang className="h-4 w-4" />
          </button>
        )}
      </div>

      {pesanGalat && (
        <p id={`${id}-galat`} role="alert" className="mt-1 text-sm text-red-600">
          {pesanGalat}
        </p>
      )}
    </div>
  )
}

/**
 * Pemilih banyak gambar sekaligus, untuk galeri album dan foto wisata/produk.
 *
 * Berkas yang dipilih DITAMBAHKAN ke daftar, bukan menggantikannya: dialog
 * berkas Windows hanya bisa memilih dari satu folder dalam satu kali buka,
 * sedangkan foto kegiatan desa lazim tersebar di beberapa folder.
 */
export function InputBanyakGambar({
  label,
  berkas,
  onUbah,
  petunjuk,
  galat,
  maks = MAKS_FOTO_SEKALI_UNGGAH,
}: {
  label: string
  berkas: File[]
  onUbah: (berkas: File[]) => void
  petunjuk?: string
  galat?: string
  maks?: number
}) {
  const id = useId()
  const [galatLokal, setGalatLokal] = useState<string | null>(null)

  const aturan = aturanBerkas('gambar')

  function pilih(e: ChangeEvent<HTMLInputElement>) {
    const dipilih = Array.from(e.target.files ?? [])
    // Input dikosongkan segera: daftar sesungguhnya disimpan di state, dan
    // tanpa ini memilih berkas yang sama dua kali tidak memicu event apa pun.
    e.target.value = ''

    if (dipilih.length === 0) return

    const masalah: string[] = []
    const lolos: File[] = []

    for (const b of dipilih) {
      const galatBerkas = periksaBerkas(b, 'gambar')
      if (galatBerkas) {
        masalah.push(`${b.name}: ${galatBerkas}`)
      } else {
        lolos.push(b)
      }
    }

    const gabungan = [...berkas, ...lolos]

    if (gabungan.length > maks) {
      masalah.push(`Maksimal ${maks} foto sekali unggah; sisanya diabaikan.`)
    }

    setGalatLokal(masalah.length ? masalah.join(' ') : null)
    onUbah(gabungan.slice(0, maks))
  }

  const pesanGalat = galatLokal ?? galat
  const totalByte = berkas.reduce((n, b) => n + b.size, 0)

  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {label}
      </label>

      <p id={`${id}-petunjuk`} className="mt-0.5 text-xs text-slate-500">
        {petunjuk ? `${petunjuk} ` : ''}
        {aturan.format}, maksimal {aturan.maksMb} MB per foto dan {maks} foto sekali unggah.
      </p>

      <div className="mt-2">
        <label
          htmlFor={id}
          className="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600 transition hover:border-slate-400 hover:bg-slate-50"
        >
          <IkonUnggah className="h-5 w-5 text-slate-400" />
          Pilih foto{berkas.length > 0 ? ' lain' : ''}…
        </label>

        <input
          id={id}
          type="file"
          multiple
          accept={aturan.accept}
          onChange={pilih}
          aria-describedby={pesanGalat ? `${id}-galat` : `${id}-petunjuk`}
          aria-invalid={pesanGalat ? 'true' : undefined}
          className="sr-only"
        />
      </div>

      {berkas.length > 0 && (
        <>
          <ul className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
            {berkas.map((b, i) => (
              <li key={`${b.name}-${b.lastModified}-${i}`}>
                <KartuFotoTerpilih
                  berkas={b}
                  onHapus={() => onUbah(berkas.filter((_, n) => n !== i))}
                />
              </li>
            ))}
          </ul>

          <p className="mt-2 text-xs text-slate-500">
            {berkas.length} foto dipilih · total {ukuranTerbaca(totalByte)}
          </p>
        </>
      )}

      {pesanGalat && (
        <p id={`${id}-galat`} role="alert" className="mt-1 text-sm text-red-600">
          {pesanGalat}
        </p>
      )}
    </div>
  )
}

function KartuFotoTerpilih({ berkas, onHapus }: { berkas: File; onHapus: () => void }) {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    const objek = URL.createObjectURL(berkas)
    setUrl(objek)

    return () => URL.revokeObjectURL(objek)
  }, [berkas])

  return (
    <div className="group relative">
      {url && (
        <img
          src={url}
          alt={berkas.name}
          className="aspect-square w-full rounded-lg border border-slate-200 object-cover"
        />
      )}
      <button
        type="button"
        onClick={onHapus}
        aria-label={`Keluarkan ${berkas.name} dari daftar unggah`}
        className="absolute right-1 top-1 rounded-full bg-white/90 p-1 text-slate-600 shadow-sm transition hover:bg-white hover:text-red-600"
      >
        <IkonSilang className="h-3.5 w-3.5" />
      </button>
    </div>
  )
}
