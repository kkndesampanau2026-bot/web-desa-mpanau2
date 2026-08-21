import { useRef, useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { useKategoriPengaduan } from '@/lib/queries'
import { formatTanggal } from '@/lib/format'
import type { StatusPengaduan, TandaTerimaPengaduan } from '@/types/api'

const LABEL_STATUS: Record<StatusPengaduan['status'], string> = {
  baru: 'Baru',
  diproses: 'Sedang Diproses',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
}

/** Formulir pengaduan masyarakat — PRD 6.15 & 10.2. */
export function PengaduanPage() {
  const { data: kategori } = useKategoriPengaduan()
  const inputBerkas = useRef<HTMLInputElement>(null)

  const [form, setForm] = useState({
    nama: '',
    no_telepon_wa: '',
    kategori_pengaduan: '',
    isi_pengaduan: '',
  })
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [berhasil, setBerhasil] = useState<TandaTerimaPengaduan | null>(null)

  const kirim = useMutation({
    mutationFn: async () => {
      // FormData dipakai karena permintaan ini membawa berkas; axios
      // menetapkan boundary multipart-nya sendiri.
      const data = new FormData()
      Object.entries(form).forEach(([k, v]) => data.append(k, v))

      const berkas = inputBerkas.current?.files
      if (berkas) {
        Array.from(berkas).forEach((f) => data.append('lampiran[]', f))
      }

      const r = await api.post<ApiSuccess<TandaTerimaPengaduan>>('/pengaduan', data)
      return r.data.data
    },
    onSuccess: (d) => {
      setGalat(null)
      setBerhasil(d)
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Terjadi kesalahan.', 0)),
  })

  function ubah(kunci: keyof typeof form, nilai: string) {
    setForm((f) => ({ ...f, [kunci]: nilai }))
  }

  if (berhasil) {
    return (
      <div className="mx-auto max-w-2xl px-6 py-12">
        <div className="rounded-2xl border border-black/5 bg-navy/3 p-6 text-center">
          <h1 className="text-xl font-bold text-navy">Pengaduan Terkirim</h1>
          <p className="mt-2 text-sm text-slate-600">
            Simpan nomor tiket berikut untuk memantau tindak lanjut pengaduan Anda.
          </p>

          <p className="mt-6 font-mono text-2xl font-semibold tracking-wide text-navy">
            {berhasil.nomor_tiket}
          </p>

          <p className="mt-2 text-sm text-slate-500">
            Dikirim {formatTanggal(berhasil.tanggal_pengaduan)} · Status{' '}
            {LABEL_STATUS[berhasil.status]}
            {berhasil.jumlah_lampiran > 0 && ` · ${berhasil.jumlah_lampiran} lampiran`}
          </p>

          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Link
              to={`/pengaduan/lacak?tiket=${berhasil.nomor_tiket}`}
              className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-light"
            >
              Lacak Pengaduan
            </Link>
            <button
              onClick={() => {
                setBerhasil(null)
                setForm({
                  nama: '',
                  no_telepon_wa: '',
                  kategori_pengaduan: '',
                  isi_pengaduan: '',
                })
                if (inputBerkas.current) inputBerkas.current.value = ''
              }}
              className="rounded-lg border border-navy/20 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white"
            >
              Kirim Pengaduan Lain
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-6 py-10">
      <h1 className="font-heading text-2xl font-bold text-navy">Pengaduan Masyarakat</h1>
      <p className="mt-2 text-slate-600">
        Sampaikan pengaduan kepada Pemerintah Desa. Anda akan menerima nomor tiket
        untuk memantau tindak lanjutnya tanpa perlu membuat akun.
      </p>

      <p className="mt-3 rounded-lg bg-navy/3 px-4 py-3 text-sm text-slate-600">
        Nama dan nomor telepon Anda hanya dibaca oleh petugas desa yang menangani
        pengaduan, dan tidak ditampilkan di halaman mana pun.
      </p>

      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          kirim.mutate()
        }}
        className="mt-8 space-y-5"
        noValidate
      >
        {galat && !galat.errors && (
          <p role="alert" className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
            {galat.message}
          </p>
        )}

        <Kolom label="Nama Lengkap" nama="nama" galat={galat}>
          <input
            id="nama"
            required
            value={form.nama}
            onChange={(e) => ubah('nama', e.target.value)}
            className={gayaInput}
          />
        </Kolom>

        <Kolom
          label="Nomor Telepon / WhatsApp"
          nama="no_telepon_wa"
          galat={galat}
          petunjuk="Dipakai petugas untuk menghubungi Anda bila perlu keterangan tambahan."
        >
          <input
            id="no_telepon_wa"
            required
            inputMode="tel"
            placeholder="08xx atau +62xx"
            value={form.no_telepon_wa}
            onChange={(e) => ubah('no_telepon_wa', e.target.value)}
            className={gayaInput}
          />
        </Kolom>

        <Kolom label="Kategori Pengaduan" nama="kategori_pengaduan" galat={galat}>
          <select
            id="kategori_pengaduan"
            required
            value={form.kategori_pengaduan}
            onChange={(e) => ubah('kategori_pengaduan', e.target.value)}
            className={gayaInput}
          >
            <option value="">Pilih kategori…</option>
            {kategori?.map((k) => (
              <option key={k} value={k}>
                {k}
              </option>
            ))}
          </select>
        </Kolom>

        <Kolom
          label="Isi Pengaduan"
          nama="isi_pengaduan"
          galat={galat}
          petunjuk="Uraikan persoalan sejelas mungkin: apa, di mana, dan sejak kapan."
        >
          <textarea
            id="isi_pengaduan"
            required
            rows={6}
            value={form.isi_pengaduan}
            onChange={(e) => ubah('isi_pengaduan', e.target.value)}
            className={gayaInput}
          />
        </Kolom>

        <Kolom
          label="Lampiran (opsional)"
          nama="lampiran"
          galat={galat}
          petunjuk="Maksimal 3 berkas, masing-masing 5 MB. Format: JPG, PNG, WebP, atau PDF."
        >
          <input
            id="lampiran"
            ref={inputBerkas}
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp,application/pdf"
            className="mt-1 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy/5 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-navy/10"
          />
          {/* Galat per-berkas dikirim backend dengan kunci lampiran.0, .1, … */}
          {[0, 1, 2].map((i) =>
            galat?.fieldError(`lampiran.${i}`) ? (
              <p key={i} role="alert" className="mt-1 text-sm text-red-600">
                Berkas ke-{i + 1}: {galat.fieldError(`lampiran.${i}`)}
              </p>
            ) : null,
          )}
        </Kolom>

        <button
          type="submit"
          disabled={kirim.isPending}
          className="rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy-light disabled:opacity-60"
        >
          {kirim.isPending ? 'Mengirim…' : 'Kirim Pengaduan'}
        </button>
      </form>
    </div>
  )
}

/** Pelacakan status pengaduan — PRD 6.15. */
export function LacakPengaduanPage() {
  const [params, setParams] = useSearchParams()
  const [tiket, setTiket] = useState(params.get('tiket') ?? '')
  const [hasil, setHasil] = useState<StatusPengaduan | null>(null)
  const [galat, setGalat] = useState<string | null>(null)

  const lacak = useMutation({
    mutationFn: async (nomor: string) => {
      const r = await api.get<ApiSuccess<StatusPengaduan>>(
        `/pengaduan/${encodeURIComponent(nomor)}/status`,
      )
      return r.data.data
    },
    onSuccess: (d) => {
      setGalat(null)
      setHasil(d)
    },
    onError: (e) => {
      setHasil(null)
      setGalat(e instanceof ApiRequestError ? e.message : 'Gagal memeriksa status pengaduan.')
    },
  })

  return (
    <div className="mx-auto max-w-2xl px-6 py-10">
      <h1 className="font-heading text-2xl font-bold text-navy">Lacak Pengaduan</h1>
      <p className="mt-2 text-slate-600">
        Masukkan nomor tiket yang Anda terima saat mengirim pengaduan.
      </p>

      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          setParams({ tiket })
          lacak.mutate(tiket.trim())
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
            value={tiket}
            onChange={(e) => setTiket(e.target.value)}
            placeholder="PGD-20260819-1234"
            className={`${gayaInput} font-mono`}
          />
        </div>
        <button
          type="submit"
          disabled={lacak.isPending}
          className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white hover:bg-navy-light disabled:opacity-60"
        >
          {lacak.isPending ? 'Memeriksa…' : 'Lacak'}
        </button>
      </form>

      {galat && (
        <p role="alert" className="mt-5 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
          {galat}
        </p>
      )}

      {hasil && (
        <div className="mt-8 rounded-2xl border border-black/5 bg-white shadow-sm p-5">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <p className="font-mono text-sm text-slate-600">{hasil.nomor_tiket}</p>
            <span className="rounded-full bg-navy/5 px-3 py-1 text-sm font-medium text-slate-800">
              {LABEL_STATUS[hasil.status]}
            </span>
          </div>

          <dl className="mt-5 space-y-3 text-sm">
            <Baris label="Pelapor" nilai={hasil.nama} />
            <Baris label="Kategori" nilai={hasil.kategori_pengaduan} />
            <Baris label="Isi Pengaduan" nilai={hasil.isi_pengaduan} />
            <Baris label="Tanggal Pengaduan" nilai={formatTanggal(hasil.tanggal_pengaduan)} />
            {hasil.tanggal_tanggapan && (
              <Baris label="Tanggal Tanggapan" nilai={formatTanggal(hasil.tanggal_tanggapan)} />
            )}
            {hasil.tanggapan_admin && (
              <Baris label="Tanggapan Petugas" nilai={hasil.tanggapan_admin} />
            )}
            {hasil.alasan_penolakan && (
              <Baris label="Alasan Penolakan" nilai={hasil.alasan_penolakan} />
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

const gayaInput =
  'mt-1 w-full rounded-lg border border-navy/20 px-3 py-2 text-sm outline-none focus:border-navy'

function Kolom({
  label,
  nama,
  galat,
  petunjuk,
  children,
}: {
  label: string
  nama: string
  galat: ApiRequestError | null
  petunjuk?: string
  children: React.ReactNode
}) {
  const pesan = galat?.fieldError(nama)

  return (
    <div>
      <label htmlFor={nama} className="block text-sm font-medium text-slate-700">
        {label}
      </label>
      {petunjuk && <p className="mt-0.5 text-xs text-slate-500">{petunjuk}</p>}
      {children}
      {pesan && (
        <p role="alert" className="mt-1 text-sm text-red-600">
          {pesan}
        </p>
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
