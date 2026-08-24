import type { FormEvent } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { BatangKategori, KartuAngka } from '@/Components/viz/Grafik'
import { bungkusInfografis } from '@/Layouts/LayoutInfografis'
import { formatRupiah } from '@/lib/format'
import type { HasilCekBansos, InfografisBansos } from '@/types/api'

const DESKRIPSI =
  'Jumlah penerima bantuan sosial per jenis bantuan, dilengkapi fitur pengecekan status penerima secara mandiri.'

/** Infografis Bansos + Cek Penerima — PRD 6.6 & 10.3. */
export default function Bansos({
  data,
  hasil_cek: hasilCek,
}: {
  data: InfografisBansos | null
  hasil_cek: HasilCekBansos | null
}) {
  if (!data) {
    return (
      <>
        <Head title="Bantuan Sosial" />
        <EmptyState judul="Bantuan Sosial" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <div className="mx-auto max-w-6xl px-6 py-10">
      <Head title={`Bantuan Sosial ${data.tahun_anggaran}`} />

      <p className="text-center text-sm text-slate-500">
        Bantuan Sosial · Tahun Anggaran {data.tahun_anggaran}
      </p>

      <div className="mt-8 max-w-xs">
        <KartuAngka
          label="Total Penerima"
          nilai={data.total_penerima}
          satuan="jiwa"
          keterangan={`Tahun anggaran ${data.tahun_anggaran}`}
        />
      </div>

      <div className="mt-10">
        <BatangKategori
          judul="Jumlah Penerima per Jenis Bantuan"
          data={data.per_jenis.map((j) => ({
            label: j.jenis_bantuan,
            jumlah: j.jumlah_penerima,
          }))}
        />
      </div>

      {data.per_jenis.some((j) => j.deskripsi || j.sumber_dana) && (
        <section className="mt-10">
          <h2 className="font-heading text-lg font-bold text-navy">Jenis Bantuan</h2>
          <ul className="mt-3 space-y-3">
            {data.per_jenis.map((j) => (
              <li
                key={j.jenis_bantuan}
                className="rounded-2xl border border-black/5 bg-white p-4 shadow-sm"
              >
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                  <h3 className="font-semibold text-navy">{j.jenis_bantuan}</h3>
                  {j.sumber_dana && (
                    <span className="text-xs text-slate-500">Sumber: {j.sumber_dana}</span>
                  )}
                </div>
                {j.deskripsi && <p className="mt-1 text-sm text-slate-600">{j.deskripsi}</p>}
              </li>
            ))}
          </ul>
        </section>
      )}

      <FormCekPenerima hasil={hasilCek} />
    </div>
  )
}

/**
 * Formulir Cek Penerima Bansos.
 *
 * Hanya meminta nama lengkap dan EMPAT DIGIT TERAKHIR NIK — bukan NIK penuh.
 * Meminta NIK lengkap pada formulir web publik justru membiasakan warga
 * menyerahkan data pribadinya, dan menambah nilai bagi siapa pun yang berhasil
 * menyadap lalu lintasnya.
 *
 * Hasilnya datang dari server sebagai prop halaman, bukan disimpan di state
 * komponen. Konsekuensinya disengaja: menyegarkan halaman membuang hasil
 * pencarian, sehingga status bantuan seseorang tidak tertinggal di layar
 * komputer bersama di kantor desa.
 */
function FormCekPenerima({ hasil }: { hasil: HasilCekBansos | null }) {
  const { data, setData, post, processing, errors } = useForm({
    nama: '',
    empat_digit_nik: '',
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    post('/infografis/bansos/cek', { preserveScroll: true })
  }

  return (
    <section className="mt-12 rounded-2xl border border-black/5 bg-navy/3 p-6">
      <h2 className="font-heading text-lg font-bold text-navy">Cek Penerima Bantuan</h2>
      <p className="mt-1 text-sm text-slate-600">
        Periksa apakah Anda terdaftar sebagai penerima bantuan sosial desa. Untuk menjaga
        kerahasiaan data warga, cukup masukkan nama lengkap dan{' '}
        <strong>empat digit terakhir</strong> NIK Anda — jangan memasukkan NIK secara
        lengkap.
      </p>

      <form onSubmit={kirim} className="mt-5 grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
        <div>
          <label htmlFor="nama-cek" className="block text-sm font-medium text-slate-700">
            Nama Lengkap
          </label>
          <input
            id="nama-cek"
            required
            minLength={3}
            value={data.nama}
            onChange={(e) => setData('nama', e.target.value)}
            placeholder="Sesuai kartu keluarga"
            className="mt-1 w-full rounded-lg border border-navy/20 bg-white px-3 py-2 text-sm outline-none focus:border-navy"
          />
          {errors.nama && <p className="mt-1 text-sm text-red-600">{errors.nama}</p>}
        </div>

        <div>
          <label htmlFor="digit-cek" className="block text-sm font-medium text-slate-700">
            4 Digit Terakhir NIK
          </label>
          <input
            id="digit-cek"
            required
            inputMode="numeric"
            maxLength={4}
            pattern="\d{4}"
            value={data.empat_digit_nik}
            onChange={(e) =>
              setData('empat_digit_nik', e.target.value.replace(/\D/g, '').slice(0, 4))
            }
            placeholder="1234"
            className="mt-1 w-28 rounded-lg border border-navy/20 bg-white px-3 py-2 text-sm tabular-nums outline-none focus:border-navy"
          />
          {errors.empat_digit_nik && (
            <p className="mt-1 text-sm text-red-600">{errors.empat_digit_nik}</p>
          )}
        </div>

        <button
          type="submit"
          disabled={processing}
          className="rounded-lg bg-navy px-5 py-2 text-sm font-semibold text-white hover:bg-navy-light disabled:opacity-60"
        >
          {processing ? 'Mencari…' : 'Cek'}
        </button>
      </form>

      {hasil && (
        <div role="status" className="mt-6">
          {hasil.ditemukan ? (
            <div className="rounded-lg border border-black/5 bg-white p-4">
              <p className="text-sm font-semibold text-navy">
                Ditemukan {hasil.hasil.length} data bantuan:
              </p>
              <ul className="mt-3 space-y-2">
                {hasil.hasil.map((h, i) => (
                  <li key={i} className="rounded-lg bg-navy/3 p-3 text-sm">
                    <p className="font-semibold text-navy">{h.nama}</p>
                    <p className="mt-0.5 text-slate-600">
                      {h.jenis_bantuan} · Tahun {h.tahun_anggaran} · Status {h.status}
                    </p>
                    {h.nominal !== null && (
                      <p className="mt-0.5 text-slate-600">Nominal: {formatRupiah(h.nominal)}</p>
                    )}
                  </li>
                ))}
              </ul>
              <p className="mt-3 text-xs text-slate-500">
                Nama ditampilkan sebagian demi menjaga kerahasiaan data warga.
              </p>
            </div>
          ) : (
            <p className="rounded-lg border border-black/5 bg-white px-4 py-3 text-sm text-slate-700">
              {hasil.pesan}
            </p>
          )}
        </div>
      )}
    </section>
  )
}

Bansos.layout = bungkusInfografis
