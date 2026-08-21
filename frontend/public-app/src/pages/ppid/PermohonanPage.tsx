import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'
import {
  CheckCircle2,
  ClipboardCheck,
  Download,
  Search,
  Send,
  ShieldCheck,
} from 'lucide-react'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import {
  GAYA_INPUT,
  IsiHalaman,
  Kartu,
  KepalaHalaman,
  Kolom,
  Lencana,
  Pemberitahuan,
  Tombol,
} from '@/components/ui'
import { formatTanggal } from '@/lib/format'
import type { PermohonanPpid, StatusPermohonanPpid } from '@/types/api'

const LABEL_STATUS: Record<StatusPermohonanPpid['status'], string> = {
  diajukan: 'Diajukan',
  diverifikasi: 'Diverifikasi',
  diproses: 'Sedang Diproses',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
}

const GAYA_STATUS: Record<StatusPermohonanPpid['status'], 'netral' | 'kuning' | 'hijau' | 'merah'> =
  {
    diajukan: 'netral',
    diverifikasi: 'kuning',
    diproses: 'kuning',
    selesai: 'hijau',
    ditolak: 'merah',
  }

/** Formulir permohonan informasi publik — PRD 6.14 & 10.4. */
export function PermohonanPage() {
  const [form, setForm] = useState({
    nama_pemohon: '',
    no_identitas: '',
    kontak: '',
    alamat: '',
    informasi_diminta: '',
    tujuan_penggunaan: '',
    cara_memperoleh: 'email' as 'langsung' | 'email' | 'pos',
  })
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [berhasil, setBerhasil] = useState<PermohonanPpid | null>(null)

  const ajukan = useMutation({
    mutationFn: async () => {
      const r = await api.post<ApiSuccess<PermohonanPpid>>('/ppid/permintaan', form)
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

  // Setelah berhasil, formulir diganti tanda terima. Nomor registrasi adalah
  // satu-satunya cara pemohon melacak permohonannya, jadi ditonjolkan.
  if (berhasil) {
    return (
      <>
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
              {berhasil.nomor_registrasi}
            </p>

            <p className="mt-3 text-sm text-slate-500">
              Diajukan {formatTanggal(berhasil.tanggal_pengajuan)} · Status{' '}
              {LABEL_STATUS[berhasil.status]}
            </p>

            <div className="mt-7 flex flex-wrap justify-center gap-3">
              <Link
                to={`/ppid/permintaan/lacak?nomor=${berhasil.nomor_registrasi}`}
                className="font-heading inline-flex items-center gap-2 rounded-full bg-gold px-6 py-2.5 text-sm font-bold text-navy transition hover:bg-gold-light"
              >
                <Search className="size-4" aria-hidden="true" />
                Lacak Permohonan
              </Link>
              <Tombol gaya="garis" onClick={() => setBerhasil(null)}>
                Ajukan Lagi
              </Tombol>
            </div>
          </Kartu>
        </IsiHalaman>
      </>
    )
  }

  return (
    <>
      <KepalaHalaman
        eyebrow="PPID"
        judul="Permohonan Informasi Publik"
        deskripsi="Ajukan permohonan informasi kepada PPID Desa. Anda akan menerima nomor registrasi untuk melacak statusnya tanpa perlu membuat akun."
      />

      <IsiHalaman lebar="sempit">
        <form
          onSubmit={(e: FormEvent) => {
            e.preventDefault()
            ajukan.mutate()
          }}
          noValidate
        >
          <Kartu className="space-y-6 p-6 sm:p-8">
            {galat && !galat.errors && (
              <Pemberitahuan jenis="galat">{galat.message}</Pemberitahuan>
            )}

            <Kolom
              label="Nama Lengkap"
              htmlFor="nama_pemohon"
              wajib
              galat={galat?.fieldError('nama_pemohon')}
            >
              <input
                id="nama_pemohon"
                required
                value={form.nama_pemohon}
                onChange={(e) => ubah('nama_pemohon', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Nomor Identitas (KTP)"
              htmlFor="no_identitas"
              petunjuk="Opsional. Disimpan terenkripsi dan tidak pernah ditampilkan kembali."
              galat={galat?.fieldError('no_identitas')}
            >
              <input
                id="no_identitas"
                inputMode="numeric"
                value={form.no_identitas}
                onChange={(e) => ubah('no_identitas', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Kontak"
              htmlFor="kontak"
              wajib
              petunjuk="Nomor WhatsApp atau email yang dapat dihubungi."
              galat={galat?.fieldError('kontak')}
            >
              <input
                id="kontak"
                required
                value={form.kontak}
                onChange={(e) => ubah('kontak', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom label="Alamat" htmlFor="alamat" galat={galat?.fieldError('alamat')}>
              <textarea
                id="alamat"
                rows={2}
                value={form.alamat}
                onChange={(e) => ubah('alamat', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Informasi yang Diminta"
              htmlFor="informasi_diminta"
              wajib
              petunjuk="Uraikan sejelas mungkin agar permohonan dapat diproses lebih cepat."
              galat={galat?.fieldError('informasi_diminta')}
            >
              <textarea
                id="informasi_diminta"
                required
                rows={4}
                value={form.informasi_diminta}
                onChange={(e) => ubah('informasi_diminta', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Tujuan Penggunaan"
              htmlFor="tujuan_penggunaan"
              galat={galat?.fieldError('tujuan_penggunaan')}
            >
              <textarea
                id="tujuan_penggunaan"
                rows={2}
                value={form.tujuan_penggunaan}
                onChange={(e) => ubah('tujuan_penggunaan', e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom
              label="Cara Memperoleh Informasi"
              htmlFor="cara_memperoleh"
              galat={galat?.fieldError('cara_memperoleh')}
            >
              <select
                id="cara_memperoleh"
                value={form.cara_memperoleh}
                onChange={(e) => ubah('cara_memperoleh', e.target.value)}
                className={GAYA_INPUT}
              >
                <option value="langsung">Mengambil langsung di kantor desa</option>
                <option value="email">Dikirim melalui email</option>
                <option value="pos">Dikirim melalui pos</option>
              </select>
            </Kolom>

            <div className="flex flex-wrap items-center justify-between gap-4 border-t border-navy/5 pt-6">
              <p className="flex items-start gap-2 text-xs text-slate-500">
                <ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" aria-hidden="true" />
                Data Anda hanya dibaca petugas PPID desa.
              </p>

              <Tombol type="submit" gaya="utama" ukuran="besar" disabled={ajukan.isPending}>
                <Send className="size-4" aria-hidden="true" />
                {ajukan.isPending ? 'Mengirim…' : 'Kirim Permohonan'}
              </Tombol>
            </div>
          </Kartu>
        </form>
      </IsiHalaman>
    </>
  )
}

/** Halaman pelacakan status permohonan. */
export function LacakPermohonanPage() {
  const [params, setParams] = useSearchParams()
  const [nomor, setNomor] = useState(params.get('nomor') ?? '')
  const [hasil, setHasil] = useState<StatusPermohonanPpid | null>(null)
  const [galat, setGalat] = useState<string | null>(null)

  const lacak = useMutation({
    mutationFn: async (no: string) => {
      const r = await api.get<ApiSuccess<StatusPermohonanPpid>>(
        `/ppid/permintaan/${encodeURIComponent(no)}/status`,
      )
      return r.data.data
    },
    onSuccess: (d) => {
      setGalat(null)
      setHasil(d)
    },
    onError: (e) => {
      setHasil(null)
      setGalat(e instanceof ApiRequestError ? e.message : 'Gagal memeriksa status permohonan.')
    },
  })

  return (
    <>
      <KepalaHalaman
        eyebrow="PPID"
        judul="Lacak Permohonan Informasi"
        deskripsi="Masukkan nomor registrasi yang Anda terima saat mengajukan permohonan."
      />

      <IsiHalaman lebar="sempit">
        <Kartu className="p-6 sm:p-8">
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              setParams({ nomor })
              lacak.mutate(nomor.trim())
            }}
            className="flex flex-wrap items-end gap-3"
          >
            <div className="min-w-56 flex-1">
              <Kolom label="Nomor Registrasi" htmlFor="nomor">
                <input
                  id="nomor"
                  required
                  value={nomor}
                  onChange={(e) => setNomor(e.target.value)}
                  placeholder="PPID-20260819-1234"
                  className={`${GAYA_INPUT} font-mono`}
                />
              </Kolom>
            </div>

            <Tombol type="submit" gaya="navy" ukuran="besar" disabled={lacak.isPending}>
              <Search className="size-4" aria-hidden="true" />
              {lacak.isPending ? 'Memeriksa…' : 'Lacak'}
            </Tombol>
          </form>
        </Kartu>

        {galat && (
          <div className="mt-5">
            <Pemberitahuan jenis="galat">{galat}</Pemberitahuan>
          </div>
        )}

        {hasil && (
          <Kartu className="mt-6 overflow-hidden">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-navy/5 bg-navy/3 px-6 py-4">
              <p className="flex items-center gap-2 font-mono text-sm text-navy">
                <ClipboardCheck className="size-4 text-gold-dark" aria-hidden="true" />
                {hasil.nomor_registrasi}
              </p>
              <Lencana gaya={GAYA_STATUS[hasil.status]}>{LABEL_STATUS[hasil.status]}</Lencana>
            </div>

            <dl className="space-y-4 p-6">
              <Baris label="Pemohon" nilai={hasil.nama_pemohon} />
              <Baris label="Informasi Diminta" nilai={hasil.informasi_diminta} />
              <Baris label="Tanggal Pengajuan" nilai={formatTanggal(hasil.tanggal_pengajuan)} />
              {hasil.tanggal_tanggapan && (
                <Baris label="Tanggal Tanggapan" nilai={formatTanggal(hasil.tanggal_tanggapan)} />
              )}
              {hasil.tanggapan_admin && (
                <Baris label="Tanggapan" nilai={hasil.tanggapan_admin} />
              )}
              {/* Alasan penolakan wajib disampaikan menurut UU KIP — pemohon
                  berhak mengetahuinya untuk dapat mengajukan keberatan. */}
              {hasil.alasan_penolakan && (
                <Baris label="Alasan Penolakan" nilai={hasil.alasan_penolakan} sorot />
              )}
            </dl>

            {hasil.dokumen_balasan && (
              <div className="border-t border-navy/5 px-6 py-5">
                <a
                  href={hasil.dokumen_balasan}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-lg bg-navy px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-light"
                >
                  <Download className="size-4" aria-hidden="true" />
                  Unduh Dokumen Balasan
                </a>
              </div>
            )}
          </Kartu>
        )}
      </IsiHalaman>
    </>
  )
}

function Baris({
  label,
  nilai,
  sorot = false,
}: {
  label: string
  nilai: string
  sorot?: boolean
}) {
  return (
    <div className={sorot ? 'rounded-lg bg-rose-50 p-4' : undefined}>
      <dt className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{label}</dt>
      <dd
        className={`mt-1 whitespace-pre-line ${sorot ? 'text-rose-900' : 'text-slate-800'}`}
      >
        {nilai}
      </dd>
    </div>
  )
}
