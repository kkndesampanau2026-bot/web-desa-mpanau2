import { useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { Check, ClipboardCopy, FileText, Search } from 'lucide-react'
import {
  GAYA_INPUT,
  IsiHalaman,
  Kartu,
  KepalaHalaman,
  Kolom,
  Pemberitahuan,
  Tombol,
} from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { PilihanRt, TandaTerimaSurat } from '@/types/api'

interface Props {
  daftar_rt: PilihanRt[]
  daftar_dusun: { id: number; nama: string }[]
  pilihan: {
    agama: string[]
    status_perkawinan: string[]
    warga_negara: string[]
    pekerjaan: string[]
  }
  tiket: TandaTerimaSurat | null
  captcha_site_key: string | null
}

/**
 * Formulir Surat Pengantar RT/Dusun.
 *
 * Mengikuti alur dua modul publik yang sudah ada: satu alamat menyajikan
 * formulir DAN tanda terima, dibedakan oleh keberadaan prop `tiket` yang
 * dititipkan server lewat flash session. Dengan begitu menyegarkan halaman
 * setelah berhasil tidak pernah mengirim ulang pengajuan yang sama.
 */
export default function Ajukan({ daftar_rt: daftarRt, pilihan, tiket }: Props) {
  const { data, setData, post, processing, errors, reset } = useForm({
    nama: '',
    nik: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    pekerjaan: '',
    agama: '',
    status_perkawinan: '',
    warga_negara: 'WNI',
    alamat: '',
    maksud_keperluan: '',
    rt_id: '',
  })

  const [konfirmasi, setKonfirmasi] = useState(false)

  // Dusun ditampilkan sebagai keterangan yang mengikuti RT, bukan sebagai
  // isian tersendiri: dusun disimpulkan server dari RT, dan menyediakan dua
  // pilihan yang bisa saling bertentangan hanya membuka peluang salah isi.
  const dusunTerpilih = useMemo(
    () => daftarRt.find((rt) => String(rt.id) === data.rt_id)?.dusun ?? null,
    [daftarRt, data.rt_id],
  )

  if (tiket) {
    return <TandaTerima tiket={tiket} />
  }

  function kirim(e: FormEvent) {
    e.preventDefault()

    // Konfirmasi sebelum kirim: pengajuan langsung membangunkan Telegram
    // Ketua RT, jadi mengirim dua kali karena ragu bukan hal sepele.
    if (!konfirmasi) {
      setKonfirmasi(true)
      return
    }

    post('/layanan-mandiri/surat-pengantar', {
      preserveScroll: true,
      onSuccess: () => reset(),
      onError: () => setKonfirmasi(false),
    })
  }

  return (
    <>
      <Head title="Surat Pengantar" />

      <KepalaHalaman
        eyebrow="Layanan Mandiri"
        judul="Pengajuan Surat Pengantar"
        deskripsi="Isi data Anda seperti tertera pada KTP. Pengajuan akan diteruskan kepada Ketua RT, lalu Kepala Dusun, untuk disetujui."
      />

      <IsiHalaman lebar="sempit">
        <Pemberitahuan jenis="info">
          NIK dan alamat Anda hanya dipakai untuk mencetak surat, dan tidak pernah
          ditampilkan utuh di halaman mana pun. Anda akan menerima nomor tiket untuk
          memantau prosesnya.
        </Pemberitahuan>

        <Kartu className="mt-6 p-6 sm:p-8">
          <form onSubmit={kirim} className="space-y-5" noValidate>
            <Kolom label="Nama Lengkap" htmlFor="nama" galat={errors.nama} wajib>
              <input
                id="nama"
                required
                autoComplete="name"
                value={data.nama}
                onChange={(e) => setData('nama', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="NIK"
              htmlFor="nik"
              galat={errors.nik}
              petunjuk="16 digit angka sesuai KTP."
              wajib
            >
              <input
                id="nik"
                required
                inputMode="numeric"
                maxLength={16}
                placeholder="16 digit"
                value={data.nik}
                // Karakter selain angka dibuang saat diketik, bukan sekadar
                // ditolak saat submit: warga yang menyalin NIK dari catatan
                // kerap membawa spasi ikut serta.
                onChange={(e) => setData('nik', e.target.value.replace(/\D/g, '').slice(0, 16))}
                className={`${GAYA_INPUT} font-mono tracking-wider`}
              />
              <p className="mt-1 text-xs text-slate-500">{data.nik.length}/16 digit</p>
            </Kolom>

            <div className="grid gap-5 sm:grid-cols-2">
              <Kolom
                label="Tempat Lahir"
                htmlFor="tempat_lahir"
                galat={errors.tempat_lahir}
                wajib
              >
                <input
                  id="tempat_lahir"
                  required
                  value={data.tempat_lahir}
                  onChange={(e) => setData('tempat_lahir', e.target.value)}
                  className={GAYA_INPUT}
                />
              </Kolom>

              <Kolom
                label="Tanggal Lahir"
                htmlFor="tanggal_lahir"
                galat={errors.tanggal_lahir}
                petunjuk="Dipakai juga untuk membuka status surat Anda nanti."
                wajib
              >
                <input
                  id="tanggal_lahir"
                  type="date"
                  required
                  max={new Date().toISOString().slice(0, 10)}
                  value={data.tanggal_lahir}
                  onChange={(e) => setData('tanggal_lahir', e.target.value)}
                  className={GAYA_INPUT}
                />
              </Kolom>
            </div>

            <Kolom
              label="Pekerjaan"
              htmlFor="pekerjaan"
              galat={errors.pekerjaan}
              petunjuk="Ketik bebas, atau pilih dari daftar yang muncul."
              wajib
            >
              <input
                id="pekerjaan"
                required
                list="daftar-pekerjaan"
                value={data.pekerjaan}
                onChange={(e) => setData('pekerjaan', e.target.value)}
                className={GAYA_INPUT}
              />
              {/* Saran dari data penduduk yang ada, tetap boleh diisi bebas. */}
              <datalist id="daftar-pekerjaan">
                {pilihan.pekerjaan.map((p) => (
                  <option key={p} value={p} />
                ))}
              </datalist>
            </Kolom>

            <div className="grid gap-5 sm:grid-cols-2">
              <Kolom label="Agama" htmlFor="agama" galat={errors.agama} wajib>
                <select
                  id="agama"
                  required
                  value={data.agama}
                  onChange={(e) => setData('agama', e.target.value)}
                  className={GAYA_INPUT}
                >
                  <option value="">Pilih agama…</option>
                  {pilihan.agama.map((a) => (
                    <option key={a} value={a}>
                      {a}
                    </option>
                  ))}
                </select>
              </Kolom>

              <Kolom
                label="Status Perkawinan"
                htmlFor="status_perkawinan"
                galat={errors.status_perkawinan}
                wajib
              >
                <select
                  id="status_perkawinan"
                  required
                  value={data.status_perkawinan}
                  onChange={(e) => setData('status_perkawinan', e.target.value)}
                  className={GAYA_INPUT}
                >
                  <option value="">Pilih status…</option>
                  {pilihan.status_perkawinan.map((s) => (
                    <option key={s} value={s}>
                      {s}
                    </option>
                  ))}
                </select>
              </Kolom>
            </div>

            <Kolom
              label="Warga Negara"
              htmlFor="warga_negara"
              galat={errors.warga_negara}
              wajib
            >
              <select
                id="warga_negara"
                required
                value={data.warga_negara}
                onChange={(e) => setData('warga_negara', e.target.value)}
                className={GAYA_INPUT}
              >
                {pilihan.warga_negara.map((w) => (
                  <option key={w} value={w}>
                    {w}
                  </option>
                ))}
              </select>
            </Kolom>

            <Kolom
              label="RT"
              htmlFor="rt_id"
              galat={errors.rt_id}
              petunjuk="Kepala Dusun yang menyetujui ditentukan otomatis dari RT Anda."
              wajib
            >
              <select
                id="rt_id"
                required
                value={data.rt_id}
                onChange={(e) => setData('rt_id', e.target.value)}
                className={GAYA_INPUT}
              >
                <option value="">Pilih RT…</option>
                {daftarRt.map((rt) => (
                  <option key={rt.id} value={rt.id}>
                    RT {rt.nomor}
                    {rt.dusun ? ` — ${rt.dusun}` : ''}
                  </option>
                ))}
              </select>

              {dusunTerpilih && (
                <p className="mt-1.5 text-sm text-slate-600">
                  Dusun: <span className="font-semibold text-navy">{dusunTerpilih}</span>
                </p>
              )}
            </Kolom>

            <Kolom label="Alamat" htmlFor="alamat" galat={errors.alamat} wajib>
              <textarea
                id="alamat"
                required
                rows={2}
                value={data.alamat}
                onChange={(e) => setData('alamat', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Maksud dan Keperluan"
              htmlFor="maksud_keperluan"
              galat={errors.maksud_keperluan}
              petunjuk="Contoh: untuk membuat Surat Keterangan Usaha."
              wajib
            >
              <textarea
                id="maksud_keperluan"
                required
                rows={4}
                value={data.maksud_keperluan}
                onChange={(e) => setData('maksud_keperluan', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            {konfirmasi && (
              <Pemberitahuan jenis="info">
                Periksa kembali data Anda. Setelah dikirim, pengajuan langsung
                diteruskan kepada Ketua RT dan tidak dapat diubah. Tekan
                <strong> Kirim Pengajuan </strong> sekali lagi untuk melanjutkan.
              </Pemberitahuan>
            )}

            <div className="flex flex-wrap items-center gap-3 pt-2">
              <Tombol type="submit" gaya="navy" ukuran="besar" disabled={processing}>
                <FileText className="size-4" aria-hidden="true" />
                {processing
                  ? 'Mengirim…'
                  : konfirmasi
                    ? 'Kirim Pengajuan'
                    : 'Periksa & Kirim'}
              </Tombol>

              {konfirmasi && !processing && (
                <Tombol
                  type="button"
                  gaya="garis"
                  ukuran="besar"
                  onClick={() => setKonfirmasi(false)}
                >
                  Periksa lagi
                </Tombol>
              )}
            </div>
          </form>
        </Kartu>
      </IsiHalaman>
    </>
  )
}

/** Tanda terima setelah pengajuan berhasil. */
function TandaTerima({ tiket }: { tiket: TandaTerimaSurat }) {
  const [tersalin, setTersalin] = useState(false)

  async function salin() {
    try {
      await navigator.clipboard.writeText(tiket.ticket_number)
      setTersalin(true)
      window.setTimeout(() => setTersalin(false), 2500)
    } catch {
      // Clipboard API ditolak (peramban lama, konteks non-HTTPS). Nomornya
      // tetap terbaca di layar dan dapat disalin manual, jadi kegagalan ini
      // tidak perlu ditampilkan sebagai galat.
    }
  }

  return (
    <>
      <Head title="Pengajuan Berhasil" />

      <KepalaHalaman
        eyebrow="Layanan Mandiri"
        judul="Pengajuan Berhasil"
        deskripsi="Pengajuan Surat Pengantar Anda telah diteruskan kepada Ketua RT."
      />

      <IsiHalaman lebar="sempit">
        <Kartu className="p-6 text-center sm:p-8">
          <p className="text-sm font-semibold tracking-wide text-slate-500 uppercase">
            Nomor Tiket Anda
          </p>

          <p className="font-heading mt-3 font-mono text-2xl font-bold tracking-widest text-navy sm:text-3xl">
            {tiket.ticket_number}
          </p>

          <p className="mt-4 text-sm text-slate-600">
            Simpan nomor tiket ini untuk memantau proses pengajuan surat. Anda akan
            diminta nomor tiket <strong>dan tanggal lahir</strong> saat memeriksa
            statusnya.
          </p>

          <p className="mt-2 text-xs text-slate-500">
            Atas nama {tiket.nama} · Diajukan {formatTanggal(tiket.tanggal_pengajuan)}
          </p>

          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Tombol type="button" gaya="utama" onClick={salin}>
              {tersalin ? (
                <>
                  <Check className="size-4" aria-hidden="true" />
                  Tersalin
                </>
              ) : (
                <>
                  <ClipboardCopy className="size-4" aria-hidden="true" />
                  Salin Nomor Tiket
                </>
              )}
            </Tombol>

            <Link
              href={`/layanan-mandiri/surat-pengantar/lacak?tiket=${tiket.ticket_number}`}
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-navy px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-light"
            >
              <Search className="size-4" aria-hidden="true" />
              Cek Status Surat
            </Link>
          </div>

          {/* Kunjungan baru, bukan sekadar mengosongkan state: tanda terima
              hidup di flash session milik server. */}
          <Link
            href="/layanan-mandiri/surat-pengantar"
            className="mt-6 inline-block text-sm font-semibold text-gold-dark hover:underline"
          >
            Ajukan surat lain
          </Link>
        </Kartu>
      </IsiHalaman>
    </>
  )
}

function Bungkus(page: ReactNode) {
  return <LayoutPublik>{page}</LayoutPublik>
}

Ajukan.layout = Bungkus
