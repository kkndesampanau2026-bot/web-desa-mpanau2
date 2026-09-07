import { useEffect, useState, type FormEvent } from 'react'
import { useForm } from '@inertiajs/react'
import { Megaphone, X } from 'lucide-react'
import { GAYA_INPUT, Pilihan } from '@/Components/ui'
import { useHalaman } from '@/types/inertia'

/**
 * Tombol mengambang "Aduan Warga" beserta popup formulirnya.
 *
 * Inilah SATU-SATUNYA cara warga mengirim aduan: halaman `/pengaduan` yang
 * dulu memuat formulir kembarannya sudah dihapus, karena ia hanya pintu masuk
 * kedua menuju formulir yang sama.
 *
 * Setelah terkirim, server mengarahkan ke halaman lacak beserta nomor
 * tiketnya — bukan `back()` — sebab popup ini dapat dibuka dari halaman mana
 * pun, dan tanda terima belum tentu terlihat pada halaman asalnya.
 *
 * Kategori pengaduan datang sebagai prop bersama Inertia agar popup tidak
 * perlu memuat data sendiri.
 */
export function AduanWarga() {
  const [buka, setBuka] = useState(false)
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

  useEffect(() => {
    if (!buka) return

    function tanganiTombol(e: KeyboardEvent) {
      if (e.key === 'Escape') setBuka(false)
    }

    window.addEventListener('keydown', tanganiTombol)

    return () => window.removeEventListener('keydown', tanganiTombol)
  }, [buka])

  function kirim(e: FormEvent) {
    e.preventDefault()
    post('/pengaduan', {
      forceFormData: true,
      // Sukses memicu redirect server ke /layanan-mandiri/lacak beserta
      // nomor tiketnya; popup ditutup & dikosongkan sebelum navigasi.
      onSuccess: () => {
        reset()
        setBuka(false)
      },
    })
  }

  return (
    <>
      <button
        onClick={() => setBuka(true)}
        className="font-heading fixed right-5 bottom-5 z-40 inline-flex items-center gap-2 rounded-full bg-gold px-5 py-3.5 font-bold text-navy shadow-xl transition hover:bg-gold-light"
      >
        <Megaphone className="size-5" aria-hidden="true" />
        Aduan Warga
      </button>

      {buka && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Formulir Aduan Warga"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
          onClick={() => setBuka(false)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-black/5 p-6">
              <div>
                <h2 className="font-heading text-xl font-bold text-navy">Aduan Warga</h2>
                <p className="mt-1 text-sm text-slate-600">
                  Sampaikan pengaduan Anda. Nomor tiket akan diberikan untuk memantau tindak
                  lanjutnya.
                </p>
              </div>
              <button
                onClick={() => setBuka(false)}
                aria-label="Tutup"
                className="shrink-0 rounded-full p-1.5 text-slate-500 transition hover:bg-navy/5 hover:text-navy"
              >
                <X className="size-5" />
              </button>
            </div>

            <form onSubmit={kirim} className="space-y-4 p-6" noValidate>
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

              <Kolom
                label="Kategori Pengaduan"
                htmlFor="aduan_kategori"
                galat={errors.kategori_pengaduan}
              >
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
                <button
                  type="button"
                  onClick={() => setBuka(false)}
                  className="rounded-lg border border-navy/20 px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-navy/5"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-light disabled:opacity-60"
                >
                  {processing ? 'Mengirim…' : 'Kirim Pengaduan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
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
  children: React.ReactNode
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
