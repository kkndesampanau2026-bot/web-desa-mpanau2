import { useState, type FormEvent, type ReactNode } from 'react'
import { Head, router } from '@inertiajs/react'
import { Check, Circle, Download, Search, TicketCheck } from 'lucide-react'
import {
  GAYA_INPUT,
  IsiHalaman,
  Kartu,
  KepalaHalaman,
  Kolom,
  Lencana,
  Pemberitahuan,
  Tombol,
} from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { StatusPengajuanSurat } from '@/types/api'
import {
  GAYA_STATUS_SURAT,
  LABEL_STATUS_SURAT,
  PENJELASAN_STATUS_SURAT,
  TAHAP_SURAT,
} from './status'

interface Props {
  tiket: string | null
  tanggal_lahir: string | null
  permohonan: StatusPengajuanSurat | null
  tidak_ditemukan: boolean
}

/**
 * Cek Status Surat.
 *
 * Mengikuti pola halaman pelacakan PPID & Pengaduan — kata kunci pencarian
 * hidup di query string, bukan state komponen, sehingga hasilnya dapat
 * ditandai maupun dibagikan lewat tautan.
 *
 * Bedanya: halaman ini menuntut DUA hal, nomor tiket dan tanggal lahir. Isi
 * yang dibukanya (NIK tersamar, keperluan, berkas surat) jauh lebih sensitif
 * daripada status pengaduan, sementara nomor tiket adalah benda yang beredar
 * — tersalin ke WhatsApp, terlihat di layar. Tanggal lahir tidak ikut beredar
 * bersamanya.
 */
export default function Lacak({
  tiket,
  tanggal_lahir: tanggalLahir,
  permohonan,
  tidak_ditemukan: tidakDitemukan,
}: Props) {
  const [masukanTiket, setMasukanTiket] = useState(tiket ?? '')
  const [masukanTanggal, setMasukanTanggal] = useState(tanggalLahir ?? '')

  function kirim(e: FormEvent) {
    e.preventDefault()
    router.get(
      '/layanan-mandiri/surat-pengantar/lacak',
      { tiket: masukanTiket.trim(), tanggal_lahir: masukanTanggal },
      { preserveState: true, preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Cek Status Surat" />

      <KepalaHalaman
        eyebrow="Layanan Mandiri"
        judul="Cek Status Surat"
        deskripsi="Masukkan nomor tiket yang Anda terima saat mengajukan, beserta tanggal lahir pemohon."
      />

      <IsiHalaman lebar="sempit">
        <Kartu className="p-6 sm:p-8">
          <form onSubmit={kirim} className="grid gap-4 sm:grid-cols-2">
            <Kolom label="Nomor Tiket" htmlFor="tiket" wajib>
              <input
                id="tiket"
                required
                value={masukanTiket}
                onChange={(e) => setMasukanTiket(e.target.value)}
                placeholder="SP-MPN-8F4K29QZ"
                className={`${GAYA_INPUT} font-mono uppercase`}
              />
            </Kolom>

            <Kolom
              label="Tanggal Lahir Pemohon"
              htmlFor="tanggal_lahir"
              petunjuk="Pengaman agar data Anda tidak terbuka oleh orang lain."
              wajib
            >
              <input
                id="tanggal_lahir"
                type="date"
                required
                value={masukanTanggal}
                onChange={(e) => setMasukanTanggal(e.target.value)}
                className={GAYA_INPUT}
              />
            </Kolom>

            <div className="sm:col-span-2">
              <Tombol type="submit" gaya="navy" ukuran="besar">
                <Search className="size-4" aria-hidden="true" />
                Cek Status
              </Tombol>
            </div>
          </form>
        </Kartu>

        {tidakDitemukan && (
          <div className="mt-5">
            {/* Satu pesan untuk kedua sebab. Membedakan "tiket salah" dari
                "tanggal lahir salah" akan mengubah halaman ini menjadi alat
                untuk memastikan sebuah nomor tiket valid, lalu menebak
                tanggal lahirnya secara terpisah. */}
            <Pemberitahuan jenis="galat">
              Data tidak ditemukan. Periksa kembali nomor tiket dan tanggal lahir Anda.
            </Pemberitahuan>
          </div>
        )}

        {permohonan && <HasilPengajuan p={permohonan} tanggalLahir={masukanTanggal} />}
      </IsiHalaman>
    </>
  )
}

function HasilPengajuan({
  p,
  tanggalLahir,
}: {
  p: StatusPengajuanSurat
  tanggalLahir: string
}) {
  const ditolak = p.status === 'DITOLAK'

  return (
    <>
      <Kartu className="mt-6 overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-navy/5 bg-navy/3 px-6 py-4">
          <p className="flex items-center gap-2 font-mono text-sm text-navy">
            <TicketCheck className="size-4 text-gold-dark" aria-hidden="true" />
            {p.ticket_number}
          </p>
          <Lencana gaya={GAYA_STATUS_SURAT[p.status]}>{LABEL_STATUS_SURAT[p.status]}</Lencana>
        </div>

        <div className="px-6 pt-5">
          <p className="text-sm text-slate-600">{PENJELASAN_STATUS_SURAT[p.status]}</p>
        </div>

        <dl className="grid gap-4 p-6 sm:grid-cols-2">
          <Baris label="Nama" nilai={p.nama} />
          <Baris label="NIK" nilai={p.nik_tersamar} mono />
          <Baris label="RT" nilai={p.rt ? `RT ${p.rt}` : '—'} />
          <Baris label="Dusun" nilai={p.dusun ?? '—'} />
          <Baris label="Jenis Surat" nilai={p.jenis_surat} />
          <Baris label="Tanggal Pengajuan" nilai={formatTanggal(p.tanggal_pengajuan)} />
          {p.nomor_surat && <Baris label="Nomor Surat" nilai={p.nomor_surat} mono />}
          <div className="sm:col-span-2">
            <Baris label="Maksud dan Keperluan" nilai={p.maksud_keperluan} />
          </div>
        </dl>

        {ditolak && p.alasan_penolakan && (
          <div className="border-t border-rose-100 bg-rose-50 px-6 py-5">
            <p className="text-xs font-semibold tracking-wide text-rose-700 uppercase">
              Alasan Penolakan
              {p.ditolak_oleh && ` — oleh ${p.ditolak_oleh === 'RT' ? 'Ketua RT' : 'Kepala Dusun'}`}
            </p>
            <p className="mt-1.5 whitespace-pre-line text-rose-900">{p.alasan_penolakan}</p>
            <p className="mt-3 text-sm text-rose-700">
              Anda dapat mengajukan kembali setelah memperbaiki hal di atas.
            </p>
          </div>
        )}

        {p.pdf_tersedia && !ditolak && (
          <div className="flex flex-wrap gap-3 border-t border-navy/5 px-6 py-5">
            {/*
              Tautan biasa, bukan <Link> Inertia: yang dituju adalah unduhan
              berkas, dan Inertia akan mencoba menafsirkan responsnya sebagai
              halaman. Tanggal lahir ikut dikirim karena endpoint unduh
              memverifikasinya ulang — halaman ini tidak memegang wewenang
              apa pun yang dapat diwariskan ke sana.
            */}
            <a
              href={`/layanan-mandiri/surat-pengantar/${p.ticket_number}/unduh?tanggal_lahir=${encodeURIComponent(tanggalLahir)}`}
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-navy px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-navy-light"
            >
              <Download className="size-4" aria-hidden="true" />
              {p.status === 'DISETUJUI' ? 'Unduh Surat (PDF)' : 'Unduh Draft (PDF)'}
            </a>

            {p.status !== 'DISETUJUI' && (
              <p className="self-center text-xs text-slate-500">
                Berkas ini masih bercap DRAFT dan belum berlaku sebagai surat resmi.
              </p>
            )}
          </div>
        )}
      </Kartu>

      <LiniMasa p={p} />
    </>
  )
}

/**
 * Lini masa empat tahap.
 *
 * Tahap yang belum terjadi ikut ditampilkan — itulah yang menjawab
 * "masih berapa lama lagi", dan itu pula yang membedakannya dari sekadar
 * daftar riwayat.
 */
function LiniMasa({ p }: { p: StatusPengajuanSurat }) {
  const waktu = new Map(p.riwayat.map((r) => [r.action, r.waktu]))
  const ditolak = p.status === 'DITOLAK'

  // Tahap berjalan = tahap pertama yang belum punya catatan waktu.
  const indeksBerjalan = TAHAP_SURAT.findIndex(
    (t) => !t.ditandai.some((a) => waktu.has(a)),
  )

  return (
    <Kartu className="mt-6 p-6 sm:p-8">
      <h2 className="font-heading border-l-4 border-gold pl-3 text-lg font-bold text-navy">
        Riwayat Proses
      </h2>

      <ol className="mt-5 space-y-1">
        {TAHAP_SURAT.map((tahap, i) => {
          const stempel = tahap.ditandai.map((a) => waktu.get(a)).find(Boolean) ?? null
          const selesai = stempel != null
          const berjalan = !selesai && i === indeksBerjalan && !ditolak
          const terakhir = i === TAHAP_SURAT.length - 1

          return (
            <li key={tahap.kunci} className="flex gap-3">
              <div className="flex flex-col items-center">
                <span
                  aria-hidden="true"
                  className={`grid size-6 shrink-0 place-items-center rounded-full ${
                    selesai
                      ? 'bg-emerald-100 text-emerald-700'
                      : berjalan
                        ? 'bg-amber-100 text-amber-700'
                        : 'bg-slate-100 text-slate-400'
                  }`}
                >
                  {selesai ? (
                    <Check className="size-3.5" />
                  ) : (
                    <Circle className={`size-2.5 ${berjalan ? 'fill-current' : ''}`} />
                  )}
                </span>
                {!terakhir && (
                  <span
                    aria-hidden="true"
                    className={`w-0.5 flex-1 ${selesai ? 'bg-emerald-200' : 'bg-slate-200'}`}
                  />
                )}
              </div>

              <div className={terakhir ? 'pb-0' : 'pb-5'}>
                <p
                  className={`text-sm font-semibold ${
                    selesai ? 'text-navy' : berjalan ? 'text-amber-700' : 'text-slate-400'
                  }`}
                >
                  {tahap.label}
                </p>
                <p className="text-xs text-slate-500">
                  {selesai
                    ? formatTanggal(stempel)
                    : berjalan
                      ? 'Sedang diproses'
                      : ditolak
                        ? 'Tidak dilanjutkan'
                        : 'Belum diproses'}
                </p>
              </div>
            </li>
          )
        })}
      </ol>

      {ditolak && (
        <p className="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">
          Proses dihentikan pada {formatTanggal(p.ditolak_pada)} karena pengajuan ditolak.
        </p>
      )}
    </Kartu>
  )
}

function Baris({
  label,
  nilai,
  mono = false,
}: {
  label: string
  nilai: string
  mono?: boolean
}) {
  return (
    <div>
      <dt className="text-xs font-semibold tracking-wide text-slate-500 uppercase">{label}</dt>
      <dd
        className={`mt-1 whitespace-pre-line text-slate-800 ${mono ? 'font-mono tracking-wide' : ''}`}
      >
        {nilai}
      </dd>
    </div>
  )
}

function Bungkus(page: ReactNode) {
  return <LayoutPublik>{page}</LayoutPublik>
}

Lacak.layout = Bungkus
