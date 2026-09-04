import type { FormEvent } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { CheckCircle2, Search, Send, ShieldCheck } from 'lucide-react'
import {
  GAYA_INPUT,
  IsiHalaman,
  Kartu,
  KepalaHalaman,
  Kolom,
  Pilihan,
  Tombol,
} from '@/Components/ui'
import { bungkusPpid } from '@/Layouts/LayoutPpid'
import { formatTanggal } from '@/lib/format'
import type { PermohonanPpid } from '@/types/api'
import { LABEL_STATUS_PPID } from './status'

/** Formulir permohonan informasi publik — PRD 6.14 & 10.4. */
export default function Permohonan({ bukti }: { bukti: PermohonanPpid | null }) {
  const { data, setData, post, processing, errors } = useForm({
    nama_pemohon: '',
    no_identitas: '',
    kontak: '',
    alamat: '',
    informasi_diminta: '',
    tujuan_penggunaan: '',
    cara_memperoleh: 'email',
  })

  // Setelah berhasil, formulir diganti tanda terima. Nomor registrasi adalah
  // satu-satunya cara pemohon melacak permohonannya, jadi ditonjolkan.
  if (bukti) {
    return (
      <>
        <Head title="Permohonan Terkirim" />

        <KepalaHalaman eyebrow="PPID" judul="Permohonan Terkirim" />

        <IsiHalaman lebar="sempit">
          <Kartu className="p-8 text-center sm:p-10">
            <span
              aria-hidden="true"
              className="mx-auto grid size-14 place-items-center rounded-full bg-emerald-50 text-emerald-600"
            >
              <CheckCircle2 className="size-7" />
            </span>

            <h2 className="font-heading mt-5 text-xl font-bold text-navy">
              Permohonan Anda telah diterima
            </h2>
            <p className="mt-2 text-sm text-slate-600">
              Simpan nomor registrasi berikut untuk memantau tindak lanjutnya.
            </p>

            <p className="mt-6 rounded-xl border-2 border-dashed border-gold bg-gold/5 px-6 py-5 font-mono text-2xl font-bold tracking-wide text-navy">
              {bukti.nomor_registrasi}
            </p>

            <p className="mt-3 text-sm text-slate-500">
              Diajukan {formatTanggal(bukti.tanggal_pengajuan)} · Status{' '}
              {LABEL_STATUS_PPID[bukti.status]}
            </p>

            <div className="mt-7 flex flex-wrap justify-center gap-3">
              <Link
                href={`/ppid/permintaan/lacak?nomor=${bukti.nomor_registrasi}`}
                className="font-heading inline-flex items-center gap-2 rounded-full bg-gold px-6 py-2.5 text-sm font-bold text-navy transition hover:bg-gold-light"
              >
                <Search className="size-4" aria-hidden="true" />
                Lacak Permohonan
              </Link>
              {/*
                Mengajukan lagi berarti memuat ulang halaman formulir, bukan
                sekadar mengosongkan state: tanda terima hidup di flash session
                milik server, jadi hanya kunjungan baru yang membersihkannya.
              */}
              <Link
                href="/ppid/permintaan"
                className="inline-flex items-center justify-center gap-2 rounded-full border-2 border-navy/20 px-6 py-2.5 text-sm font-semibold text-navy transition hover:border-navy/40"
              >
                Ajukan Lagi
              </Link>
            </div>
          </Kartu>
        </IsiHalaman>
      </>
    )
  }

  return (
    <>
      <Head title="Permohonan Informasi Publik" />

      <KepalaHalaman
        eyebrow="PPID"
        judul="Permohonan Informasi Publik"
        deskripsi="Ajukan permohonan informasi kepada PPID Desa. Anda akan menerima nomor registrasi untuk melacak statusnya tanpa perlu membuat akun."
      />

      <IsiHalaman lebar="sempit">
        <form
          onSubmit={(e: FormEvent) => {
            e.preventDefault()
            post('/ppid/permintaan')
          }}
          noValidate
        >
          <Kartu className="space-y-6 p-6 sm:p-8">
            <Kolom
              label="Nama Lengkap"
              htmlFor="nama_pemohon"
              wajib
              galat={errors.nama_pemohon}
            >
              <input
                id="nama_pemohon"
                required
                value={data.nama_pemohon}
                onChange={(e) => setData('nama_pemohon', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Nomor Identitas (KTP)"
              htmlFor="no_identitas"
              petunjuk="Opsional. Disimpan terenkripsi dan tidak pernah ditampilkan kembali."
              galat={errors.no_identitas}
            >
              <input
                id="no_identitas"
                inputMode="numeric"
                value={data.no_identitas}
                onChange={(e) => setData('no_identitas', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Kontak"
              htmlFor="kontak"
              wajib
              petunjuk="Nomor WhatsApp atau email yang dapat dihubungi."
              galat={errors.kontak}
            >
              <input
                id="kontak"
                required
                value={data.kontak}
                onChange={(e) => setData('kontak', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom label="Alamat" htmlFor="alamat" galat={errors.alamat}>
              <textarea
                id="alamat"
                rows={2}
                value={data.alamat}
                onChange={(e) => setData('alamat', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Informasi yang Diminta"
              htmlFor="informasi_diminta"
              wajib
              petunjuk="Uraikan sejelas mungkin agar permohonan dapat diproses lebih cepat."
              galat={errors.informasi_diminta}
            >
              <textarea
                id="informasi_diminta"
                required
                rows={4}
                value={data.informasi_diminta}
                onChange={(e) => setData('informasi_diminta', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Tujuan Penggunaan"
              htmlFor="tujuan_penggunaan"
              galat={errors.tujuan_penggunaan}
            >
              <textarea
                id="tujuan_penggunaan"
                rows={2}
                value={data.tujuan_penggunaan}
                onChange={(e) => setData('tujuan_penggunaan', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Cara Memperoleh Informasi"
              htmlFor="cara_memperoleh"
              galat={errors.cara_memperoleh}
            >
              <Pilihan
                id="cara_memperoleh"
                value={data.cara_memperoleh}
                onChange={(v) => setData('cara_memperoleh', v)}
                options={[
                  { value: 'langsung', label: 'Mengambil langsung di kantor desa' },
                  { value: 'email', label: 'Dikirim melalui email' },
                  { value: 'pos', label: 'Dikirim melalui pos' },
                ]}
              />
            </Kolom>

            <div className="flex flex-wrap items-center justify-between gap-4 border-t border-navy/5 pt-6">
              <p className="flex items-start gap-2 text-xs text-slate-500">
                <ShieldCheck
                  className="mt-0.5 size-4 shrink-0 text-emerald-600"
                  aria-hidden="true"
                />
                Data Anda hanya dibaca petugas PPID desa.
              </p>

              <Tombol type="submit" gaya="utama" ukuran="besar" disabled={processing}>
                <Send className="size-4" aria-hidden="true" />
                {processing ? 'Mengirim…' : 'Kirim Permohonan'}
              </Tombol>
            </div>
          </Kartu>
        </form>
      </IsiHalaman>
    </>
  )
}

Permohonan.layout = bungkusPpid
