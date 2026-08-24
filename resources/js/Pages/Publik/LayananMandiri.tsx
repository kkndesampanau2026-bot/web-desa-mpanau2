import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import {
  ArrowRight,
  FileSearch,
  FileText,
  HandCoins,
  Megaphone,
  Search,
  type LucideIcon,
} from 'lucide-react'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'

const DESKRIPSI =
  'Berbagai layanan warga yang dapat diakses mandiri tanpa perlu datang ke kantor desa: menyampaikan pengaduan, memohon informasi publik, hingga memeriksa status bantuan sosial.'

interface Layanan {
  ikon: LucideIcon
  judul: string
  deskripsi: string
  ke: string
  aksi: string
}

const LAYANAN: Layanan[] = [
  {
    ikon: Megaphone,
    judul: 'Pengaduan Masyarakat',
    deskripsi:
      'Sampaikan keluhan atau aspirasi kepada Pemerintah Desa dan terima nomor tiket untuk memantau tindak lanjutnya.',
    ke: '/pengaduan',
    aksi: 'Kirim pengaduan',
  },
  {
    ikon: Search,
    judul: 'Lacak Pengaduan',
    deskripsi:
      'Sudah pernah mengadu? Masukkan nomor tiket Anda untuk melihat status dan tanggapan dari petugas desa.',
    ke: '/pengaduan/lacak',
    aksi: 'Lacak status',
  },
  {
    ikon: FileText,
    judul: 'Permohonan Informasi Publik',
    deskripsi:
      'Ajukan permohonan informasi publik kepada PPID Desa sesuai amanat UU No. 14 Tahun 2008.',
    ke: '/ppid/permintaan',
    aksi: 'Ajukan permohonan',
  },
  {
    ikon: FileSearch,
    judul: 'Lacak Permohonan Informasi',
    deskripsi:
      'Pantau perkembangan permohonan informasi publik yang telah Anda ajukan menggunakan nomor registrasi.',
    ke: '/ppid/permintaan/lacak',
    aksi: 'Lacak permohonan',
  },
  {
    ikon: HandCoins,
    judul: 'Cek Penerima Bansos',
    deskripsi:
      'Periksa apakah nama Anda terdaftar sebagai penerima bantuan sosial pada program yang sedang berjalan.',
    ke: '/infografis/bansos',
    aksi: 'Cek penerima',
  },
]

/** Layanan Mandiri — pusat layanan warga (hub). */
export default function LayananMandiri() {
  return (
    <>
      <Head title="Layanan Mandiri" />

      <KepalaHalaman eyebrow="Pusat Layanan Warga" judul="Layanan Mandiri" deskripsi={DESKRIPSI} />

      <IsiHalaman lebar="lebar">
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {LAYANAN.map((layanan) => (
            <Link key={layanan.ke} href={layanan.ke} className="group">
              <Kartu interaktif className="flex h-full flex-col p-6">
                <span
                  aria-hidden="true"
                  className="grid size-12 place-items-center rounded-xl bg-navy/5 text-navy transition group-hover:bg-gold/15 group-hover:text-gold-dark"
                >
                  <layanan.ikon className="size-6" />
                </span>

                <h2 className="font-heading mt-4 text-lg font-bold text-navy">{layanan.judul}</h2>
                <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">
                  {layanan.deskripsi}
                </p>

                <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-gold-dark">
                  {layanan.aksi}
                  <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                </span>
              </Kartu>
            </Link>
          ))}
        </div>
      </IsiHalaman>
    </>
  )
}

LayananMandiri.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
