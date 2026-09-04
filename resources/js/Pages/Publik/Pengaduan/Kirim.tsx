import type { FormEvent, ReactNode } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { Pilihan } from '@/Components/ui'
import { formatTanggal } from '@/lib/format'
import type { TandaTerimaPengaduan } from '@/types/api'
import { GAYA_INPUT_PENGADUAN, LABEL_STATUS_PENGADUAN } from './status'

/** Formulir pengaduan masyarakat — PRD 6.15 & 10.2. */
export default function Kirim({
  kategori,
  tiket,
}: {
  kategori: string[]
  tiket: TandaTerimaPengaduan | null
}) {
  /*
   * `useForm` menangani unggahan berkas sendiri: begitu salah satu nilainya
   * berupa File, Inertia mengirim permintaan sebagai multipart. Tidak perlu
   * lagi menyusun FormData manual seperti pada versi axios.
   */
  const { data, setData, post, processing, errors, reset } = useForm<{
    nama: string
    no_telepon_wa: string
    kategori_pengaduan: string
    isi_pengaduan: string
    lampiran: File[]
  }>({
    nama: '',
    no_telepon_wa: '',
    kategori_pengaduan: '',
    isi_pengaduan: '',
    lampiran: [],
  })

  if (tiket) {
    return (
      <div className="mx-auto max-w-2xl px-6 py-12">
        <Head title="Pengaduan Terkirim" />

        <div className="rounded-2xl border border-black/5 bg-navy/3 p-6 text-center">
          <h1 className="text-xl font-bold text-navy">Pengaduan Terkirim</h1>
          <p className="mt-2 text-sm text-slate-600">
            Simpan nomor tiket berikut untuk memantau tindak lanjut pengaduan Anda.
          </p>

          <p className="mt-6 font-mono text-2xl font-semibold tracking-wide text-navy">
            {tiket.nomor_tiket}
          </p>

          <p className="mt-2 text-sm text-slate-500">
            Dikirim {formatTanggal(tiket.tanggal_pengaduan)} · Status{' '}
            {LABEL_STATUS_PENGADUAN[tiket.status]}
            {tiket.jumlah_lampiran > 0 && ` · ${tiket.jumlah_lampiran} lampiran`}
          </p>

          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Link
              href={`/pengaduan/lacak?tiket=${tiket.nomor_tiket}`}
              className="rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-light"
            >
              Lacak Pengaduan
            </Link>
            {/*
              Kunjungan baru, bukan sekadar mengosongkan state: tanda terima
              hidup di flash session milik server.
            */}
            <Link
              href="/pengaduan"
              className="rounded-lg border border-navy/20 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-white"
            >
              Kirim Pengaduan Lain
            </Link>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-6 py-10">
      <Head title="Pengaduan Masyarakat" />

      <h1 className="font-heading text-2xl font-bold text-navy">Pengaduan Masyarakat</h1>
      <p className="mt-2 text-slate-600">
        Sampaikan pengaduan kepada Pemerintah Desa. Anda akan menerima nomor tiket untuk
        memantau tindak lanjutnya tanpa perlu membuat akun.
      </p>

      <p className="mt-3 rounded-lg bg-navy/3 px-4 py-3 text-sm text-slate-600">
        Nama dan nomor telepon Anda hanya dibaca oleh petugas desa yang menangani
        pengaduan, dan tidak ditampilkan di halaman mana pun.
      </p>

      <form
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          post('/pengaduan', { forceFormData: true, onSuccess: () => reset() })
        }}
        className="mt-8 space-y-5"
        noValidate
      >
        <Kolom label="Nama Lengkap" nama="nama" galat={errors.nama}>
          <input
            id="nama"
            required
            value={data.nama}
            onChange={(e) => setData('nama', e.target.value)}
            className={GAYA_INPUT_PENGADUAN}
          />
        </Kolom>

        <Kolom
          label="Nomor Telepon / WhatsApp"
          nama="no_telepon_wa"
          galat={errors.no_telepon_wa}
          petunjuk="Dipakai petugas untuk menghubungi Anda bila perlu keterangan tambahan."
        >
          <input
            id="no_telepon_wa"
            required
            inputMode="tel"
            placeholder="08xx atau +62xx"
            value={data.no_telepon_wa}
            onChange={(e) => setData('no_telepon_wa', e.target.value)}
            className={GAYA_INPUT_PENGADUAN}
          />
        </Kolom>

        <Kolom
          label="Kategori Pengaduan"
          nama="kategori_pengaduan"
          galat={errors.kategori_pengaduan}
        >
          <Pilihan
            id="kategori_pengaduan"
            value={data.kategori_pengaduan}
            onChange={(v) => setData('kategori_pengaduan', v)}
            placeholder="Pilih kategori…"
            options={kategori.map((k) => ({ value: k, label: k }))}
          />
        </Kolom>

        <Kolom
          label="Isi Pengaduan"
          nama="isi_pengaduan"
          galat={errors.isi_pengaduan}
          petunjuk="Uraikan persoalan sejelas mungkin: apa, di mana, dan sejak kapan."
        >
          <textarea
            id="isi_pengaduan"
            required
            rows={6}
            value={data.isi_pengaduan}
            onChange={(e) => setData('isi_pengaduan', e.target.value)}
            className={GAYA_INPUT_PENGADUAN}
          />
        </Kolom>

        <Kolom
          label="Lampiran (opsional)"
          nama="lampiran"
          galat={errors.lampiran}
          petunjuk="Maksimal 3 berkas, masing-masing 5 MB. Format: JPG, PNG, WebP, atau PDF."
        >
          <input
            id="lampiran"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp,application/pdf"
            onChange={(e) => setData('lampiran', Array.from(e.target.files ?? []))}
            className="mt-1 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy/5 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-navy/10"
          />
          {/* Galat per-berkas dikirim backend dengan kunci lampiran.0, .1, … */}
          {[0, 1, 2].map((i) => {
            const pesan = (errors as Record<string, string | undefined>)[`lampiran.${i}`]

            return pesan ? (
              <p key={i} role="alert" className="mt-1 text-sm text-red-600">
                Berkas ke-{i + 1}: {pesan}
              </p>
            ) : null
          })}
        </Kolom>

        <button
          type="submit"
          disabled={processing}
          className="rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy-light disabled:opacity-60"
        >
          {processing ? 'Mengirim…' : 'Kirim Pengaduan'}
        </button>
      </form>
    </div>
  )
}

function Kolom({
  label,
  nama,
  galat,
  petunjuk,
  children,
}: {
  label: string
  nama: string
  galat?: string
  petunjuk?: string
  children: ReactNode
}) {
  return (
    <div>
      <label htmlFor={nama} className="block text-sm font-medium text-slate-700">
        {label}
      </label>
      {petunjuk && <p className="mt-0.5 text-xs text-slate-500">{petunjuk}</p>}
      {children}
      {galat && (
        <p role="alert" className="mt-1 text-sm text-red-600">
          {galat}
        </p>
      )}
    </div>
  )
}

Kirim.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
