import { useForm } from '@inertiajs/react'
import type { FormEvent, ReactNode } from 'react'
import { GAYA_INPUT, Pilihan } from '@/Components/ui'
import { useHalaman } from '@/types/inertia'

/**
 * Formulir Aduan Warga — SATU-SATUNYA di situs ini.
 *
 * Dipakai dua tempat: popup mengambang yang menemani pengunjung di setiap
 * halaman (`AduanWarga`), dan halaman `/pengaduan` yang punya alamat sendiri
 * sehingga dapat dibagikan lewat tautan.
 *
 * Isinya diangkat ke komponen tersendiri justru supaya keduanya tidak menjadi
 * dua formulir: aturan validasi, batas lampiran, dan tujuan pengirimannya
 * hidup di satu berkas. Dua salinan cepat atau lambat berbeda isi, dan yang
 * paling mungkin tertinggal adalah salinan yang jarang dibuka.
 *
 * Setelah terkirim, server mengarahkan ke halaman lacak beserta nomor tiket —
 * bukan `back()` — sebab formulir ini dapat dikirim dari halaman mana pun.
 *
 * Kategori pengaduan datang sebagai prop bersama Inertia, jadi tidak ada
 * permintaan data tambahan saat formulirnya dibuka.
 */
export function FormAduan({ onSelesai, aksiTambahan }: {
  /** Dipanggil setelah aduan benar-benar terkirim — popup memakainya untuk menutup diri. */
  onSelesai?: () => void
  /** Tombol pendamping di baris aksi, mis. "Batal" milik popup. */
  aksiTambahan?: ReactNode
}) {
  const { kategori_pengaduan } = useHalaman().props
  const kategori = kategori_pengaduan ?? []

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

  function kirim(e: FormEvent) {
    e.preventDefault()
    post('/pengaduan', {
      forceFormData: true,
      onSuccess: () => {
        reset()
        onSelesai?.()
      },
    })
  }

  return (
    <form onSubmit={kirim} className="space-y-4" noValidate>
      <Kolom label="Nama Lengkap" htmlFor="aduan_nama" galat={errors.nama}>
        <input
          id="aduan_nama"
          required
          value={data.nama}
          onChange={(e) => setData('nama', e.target.value)}
          className={GAYA_INPUT}
        />
      </Kolom>

      <Kolom
        label="Nomor Telepon / WhatsApp"
        htmlFor="aduan_telepon"
        galat={errors.no_telepon_wa}
        petunjuk="Dipakai petugas bila perlu keterangan tambahan; tidak ditampilkan publik."
      >
        <input
          id="aduan_telepon"
          required
          inputMode="tel"
          placeholder="08xx atau +62xx"
          value={data.no_telepon_wa}
          onChange={(e) => setData('no_telepon_wa', e.target.value)}
          className={GAYA_INPUT}
        />
      </Kolom>

      <Kolom label="Kategori Pengaduan" htmlFor="aduan_kategori" galat={errors.kategori_pengaduan}>
        <Pilihan
          id="aduan_kategori"
          value={data.kategori_pengaduan}
          onChange={(v) => setData('kategori_pengaduan', v)}
          placeholder="Pilih kategori…"
          options={kategori.map((k) => ({ value: k, label: k }))}
        />
      </Kolom>

      <Kolom
        label="Isi Pengaduan"
        htmlFor="aduan_isi"
        galat={errors.isi_pengaduan}
        petunjuk="Uraikan persoalan sejelas mungkin: apa, di mana, dan sejak kapan."
      >
        <textarea
          id="aduan_isi"
          required
          rows={5}
          value={data.isi_pengaduan}
          onChange={(e) => setData('isi_pengaduan', e.target.value)}
          className={GAYA_INPUT}
        />
      </Kolom>

      <Kolom
        label="Lampiran (opsional)"
        htmlFor="aduan_lampiran"
        galat={(errors as Record<string, string | undefined>)['lampiran.0']}
        petunjuk="Maksimal 3 berkas, masing-masing 5 MB. Format: JPG, PNG, WebP, atau PDF."
      >
        <input
          id="aduan_lampiran"
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,application/pdf"
          onChange={(e) => setData('lampiran', Array.from(e.target.files ?? []))}
          className="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-navy/5 file:px-4 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-navy/10"
        />
      </Kolom>

      <div className="flex justify-end gap-3 pt-2">
        {aksiTambahan}
        <button
          type="submit"
          disabled={processing}
          className="rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-light disabled:opacity-60"
        >
          {processing ? 'Mengirim…' : 'Kirim Pengaduan'}
        </button>
      </div>
    </form>
  )
}

function Kolom({
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
      <label htmlFor={htmlFor} className="block text-sm font-semibold text-navy">
        {label}
      </label>
      {petunjuk && <p className="mt-0.5 text-xs text-slate-500">{petunjuk}</p>}
      <div className="mt-1.5">{children}</div>
      {galat && (
        <p role="alert" className="mt-1.5 text-sm text-rose-700">
          {galat}
        </p>
      )}
    </div>
  )
}
