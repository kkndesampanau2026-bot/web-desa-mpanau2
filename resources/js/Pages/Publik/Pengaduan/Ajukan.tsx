import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { ShieldCheck, TicketCheck } from 'lucide-react'
import { FormAduan } from '@/Components/FormAduan'
import { SeoMeta } from '@/Components/SeoMeta'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'

const DESKRIPSI =
  'Sampaikan keluhan, laporan kerusakan, atau masukan kepada Pemerintah Desa Mpanau. Setiap aduan menerima nomor tiket untuk memantau tindak lanjutnya.'

/**
 * Halaman Aduan Warga — PRD 6.15.
 *
 * Alamatnya sendiri (`/layanan-mandiri/aduan`) supaya dapat dibagikan: ditempel
 * di papan pengumuman, dikirim lewat WhatsApp grup RT, atau ditulis pada surat
 * edaran desa. Popup mengambang tidak bisa melakukan itu — ia tidak punya alamat.
 *
 * Formulirnya komponen yang sama dengan popup (`FormAduan`), bukan salinan.
 */
export default function AjukanPengaduan() {
  return (
    <>
      <SeoMeta
        title="Aduan Warga"
        description={DESKRIPSI}
        schema={{
          '@context': 'https://schema.org',
          '@type': 'GovernmentService',
          name: 'Aduan Warga Desa Mpanau',
          serviceType: 'Pengaduan Masyarakat',
          provider: {
            '@type': 'GovernmentOrganization',
            name: 'Pemerintah Desa Mpanau',
          },
        }}
      />

      <KepalaHalaman
        lebar="sempit"
        eyebrow="Layanan Warga"
        judul="Aduan Warga"
        deskripsi={DESKRIPSI}
      />

      <IsiHalaman lebar="sempit">
        <Kartu className="p-5 sm:p-8">
          <FormAduan />
        </Kartu>

        {/*
          Dua hal yang paling sering ditanyakan warga sebelum mengadu:
          "siapa yang bisa membaca ini" dan "lalu saya harus bagaimana".
          Keduanya dijawab di sini, bukan dibiarkan menjadi keraguan yang
          membuat aduan tidak jadi dikirim.
        */}
        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          <p className="flex gap-3 rounded-xl border border-navy/10 bg-navy/3 px-4 py-3 text-sm text-slate-600">
            <ShieldCheck className="mt-0.5 size-4 shrink-0 text-gold-dark" aria-hidden="true" />
            <span>
              Nomor telepon Anda hanya dibaca petugas desa dan tidak pernah ditampilkan di
              situs ini. Isi aduan tidak diumumkan kepada publik.
            </span>
          </p>

          <p className="flex gap-3 rounded-xl border border-navy/10 bg-navy/3 px-4 py-3 text-sm text-slate-600">
            <TicketCheck className="mt-0.5 size-4 shrink-0 text-gold-dark" aria-hidden="true" />
            <span>
              Simpan nomor tiket yang muncul setelah mengirim. Pakai nomor itu di halaman{' '}
              <Link
                href="/layanan-mandiri/lacak"
                className="font-semibold text-navy underline-offset-2 hover:underline"
              >
                Lacak Aduan
              </Link>{' '}
              untuk melihat tanggapan petugas.
            </span>
          </p>
        </div>
      </IsiHalaman>
    </>
  )
}

AjukanPengaduan.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
