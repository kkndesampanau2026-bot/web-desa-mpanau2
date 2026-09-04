import { useState, type FormEvent, type ReactNode } from 'react'
import { Head, router } from '@inertiajs/react'
import { ClipboardCheck, Download, Search } from 'lucide-react'
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
import type { StatusPermohonanPpid } from '@/types/api'
import { GAYA_STATUS_PPID, LABEL_STATUS_PPID } from './status'

/**
 * Halaman pelacakan status permohonan — PRD 6.14.
 *
 * Nomor registrasi hidup di query string, bukan state komponen, sehingga
 * hasil pelacakan dapat ditandai atau dibagikan lewat tautan — dan tautan
 * "Lacak Permohonan" pada tanda terima langsung menampilkan hasilnya.
 */
export default function Lacak({
  nomor,
  permohonan,
  tidak_ditemukan: tidakDitemukan,
}: {
  nomor: string | null
  permohonan: StatusPermohonanPpid | null
  tidak_ditemukan: boolean
}) {
  const [masukan, setMasukan] = useState(nomor ?? '')

  function kirim(e: FormEvent) {
    e.preventDefault()
    router.get(
      '/ppid/permintaan/lacak',
      { nomor: masukan.trim() },
      { preserveState: true, preserveScroll: true },
    )
  }

  return (
    <>
      <Head title="Lacak Permohonan Informasi" />

      <KepalaHalaman
        lebar="sempit"
        eyebrow="PPID"
        judul="Lacak Permohonan Informasi"
        deskripsi="Masukkan nomor registrasi yang Anda terima saat mengajukan permohonan."
      />


      <IsiHalaman lebar="sempit">
        <Kartu className="p-6 sm:p-8">
          <form onSubmit={kirim} className="flex flex-wrap items-end gap-3">
            <div className="min-w-56 flex-1">
              <Kolom label="Nomor Registrasi" htmlFor="nomor">
                <input
                  id="nomor"
                  required
                  value={masukan}
                  onChange={(e) => setMasukan(e.target.value)}
                  placeholder="PPID-20260819-1234"
                  className={`${GAYA_INPUT} font-mono`}
                />
              </Kolom>
            </div>

            <Tombol type="submit" gaya="navy" ukuran="besar">
              <Search className="size-4" aria-hidden="true" />
              Lacak
            </Tombol>
          </form>
        </Kartu>

        {tidakDitemukan && (
          <div className="mt-5">
            <Pemberitahuan jenis="galat">Nomor registrasi tidak ditemukan.</Pemberitahuan>
          </div>
        )}

        {permohonan && (
          <Kartu className="mt-6 overflow-hidden">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-navy/5 bg-navy/3 px-6 py-4">
              <p className="flex items-center gap-2 font-mono text-sm text-navy">
                <ClipboardCheck className="size-4 text-gold-dark" aria-hidden="true" />
                {permohonan.nomor_registrasi}
              </p>
              <Lencana gaya={GAYA_STATUS_PPID[permohonan.status]}>
                {LABEL_STATUS_PPID[permohonan.status]}
              </Lencana>
            </div>

            <dl className="space-y-4 p-6">
              <Baris label="Pemohon" nilai={permohonan.nama_pemohon} />
              <Baris label="Informasi Diminta" nilai={permohonan.informasi_diminta} />
              <Baris
                label="Tanggal Pengajuan"
                nilai={formatTanggal(permohonan.tanggal_pengajuan)}
              />
              {permohonan.tanggal_tanggapan && (
                <Baris
                  label="Tanggal Tanggapan"
                  nilai={formatTanggal(permohonan.tanggal_tanggapan)}
                />
              )}
              {permohonan.tanggapan_admin && (
                <Baris label="Tanggapan" nilai={permohonan.tanggapan_admin} />
              )}
              {/* Alasan penolakan wajib disampaikan menurut UU KIP — pemohon
                  berhak mengetahuinya untuk dapat mengajukan keberatan. */}
              {permohonan.alasan_penolakan && (
                <Baris label="Alasan Penolakan" nilai={permohonan.alasan_penolakan} sorot />
              )}
            </dl>

            {permohonan.dokumen_balasan && (
              <div className="border-t border-navy/5 px-6 py-5">
                <a
                  href={permohonan.dokumen_balasan}
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
      <dd className={`mt-1 whitespace-pre-line ${sorot ? 'text-rose-900' : 'text-slate-800'}`}>
        {nilai}
      </dd>
    </div>
  )
}

Lacak.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
