import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import {
  ArrowRight,
  FileSearch,
  FileSignature,
  FileText,
  Megaphone,
  MessageSquareWarning,
  TicketCheck,
  type LucideIcon,
} from 'lucide-react'
import { SeoMeta } from '@/Components/SeoMeta'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'

const DESKRIPSI =
  'Berbagai layanan warga yang dapat diakses mandiri tanpa perlu datang ke kantor desa: mengajukan surat pengantar, memantau proses persetujuannya, memohon informasi publik kepada PPID Desa, hingga mengirim dan melacak pengaduan.'

interface Layanan {
  ikon: LucideIcon
  judul: string
  deskripsi: string
  ke: string
  /** Teks tautan di kaki kartu. */
  aksi: string
}

/*
 * Daftar layanan.
 *
 * Isinya sengaja hanya alur yang benar-benar dilayani mandiri oleh warga:
 * surat pengantar dan permohonan informasi PPID, masing-masing berpasangan
 * dengan halaman pelacakannya. Kartu yang mengarah ke alur di luar itu tidak
 * ditaruh di sini — halaman ini pintu masuk, jadi tautan yang tidak berujung
 * pada layanan aktif hanya membuat warga berputar.
 */
const LAYANAN: Layanan[] = [
  {
    ikon: FileSignature,
    judul: 'Surat Pengantar RT/Dusun',
    deskripsi:
      'Ajukan Surat Pengantar secara daring. Permohonan diteruskan ke Ketua RT dan Kepala Dusun untuk disetujui, tanpa perlu mengisi blangko manual.',
    ke: '/layanan-mandiri/surat-pengantar',
    aksi: 'Ajukan surat',
  },
  {
    ikon: TicketCheck,
    judul: 'Cek Status Surat',
    deskripsi:
      'Pantau proses persetujuan surat Anda dan unduh berkasnya menggunakan nomor tiket serta tanggal lahir pemohon.',
    ke: '/layanan-mandiri/surat-pengantar/lacak',
    aksi: 'Cek status',
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
  /*
   * Mengadu dan melacaknya berdiri berpasangan di sini, seperti surat
   * pengantar dan permohonan PPID di atas.
   *
   * Kartu "Kirim Aduan" sempat tidak ada — alasannya waktu itu: mengadu sudah
   * punya tombol mengambang yang menemani pengunjung di setiap halaman, jadi
   * kartu kedua dianggap menggandakan pintu masuk. Atas permintaan pemilik
   * produk kartunya dikembalikan (docs/DEVIASI.md §A5): warga yang membuka
   * "Layanan Mandiri" datang untuk mencari daftar layanan, dan layanan yang
   * tidak tercantum di daftar itu praktis tidak ada baginya — betapapun
   * tombolnya melayang di sudut layar.
   *
   * Yang TIDAK digandakan adalah formulirnya: halaman tujuan memuat komponen
   * `FormAduan` yang sama persis dengan popup mengambang.
   */
  {
    ikon: Megaphone,
    judul: 'Kirim Aduan Warga',
    deskripsi:
      'Sampaikan keluhan, laporan kerusakan, atau masukan kepada pemerintah desa. Anda menerima nomor tiket untuk memantau tindak lanjutnya.',
    ke: '/pengaduan',
    aksi: 'Kirim aduan',
  },
  {
    ikon: MessageSquareWarning,
    judul: 'Lacak Aduan Warga',
    deskripsi:
      'Pantau tindak lanjut pengaduan yang telah Anda kirim beserta tanggapan petugas, menggunakan nomor tiket aduan.',
    ke: '/layanan-mandiri/lacak',
    aksi: 'Lacak aduan',
  },
]

/** Layanan Mandiri — pusat layanan warga (hub). */
export default function LayananMandiri() {
  return (
    <>
      <SeoMeta
        title="Layanan Mandiri Warga"
        description={DESKRIPSI}
        schema={{
          '@context': 'https://schema.org',
          '@type': 'GovernmentService',
          name: 'Layanan Mandiri Desa Mpanau',
          serviceType: 'Pelayanan Publik Digital Desa',
          provider: {
            '@type': 'GovernmentOrganization',
            name: 'Pemerintah Desa Mpanau',
          },
        }}
      />

      <KepalaHalaman eyebrow="Pusat Layanan Warga" judul="Layanan Mandiri" deskripsi={DESKRIPSI} />

      {/*
        Tiga kolom mulai lg, dua di bawahnya — dengan enam layanan, keduanya
        menghasilkan baris yang terisi penuh. Sempat empat kolom sewaktu
        layanannya masih lima; kartu keenam membuat baris terakhir menyisakan
        dua kolom menganga.
      */}
      <IsiHalaman lebar="lebar">
        <div className="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
          {LAYANAN.map((layanan) => (
            <KartuLayanan key={layanan.judul} layanan={layanan} />
          ))}
        </div>
      </IsiHalaman>
    </>
  )
}

/** Satu kartu layanan — seluruhnya menuju halaman tersendiri. */
function KartuLayanan({ layanan }: { layanan: Layanan }) {
  return (
    <Link href={layanan.ke} className="group">
      <Kartu interaktif className="flex h-full flex-col p-5 sm:p-6">
        <layanan.ikon className="size-5 shrink-0 text-gold-dark" aria-hidden="true" />

        <h2 className="font-heading mt-3.5 text-base font-bold text-navy transition group-hover:text-gold-dark sm:text-lg">
          {layanan.judul}
        </h2>
        <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">{layanan.deskripsi}</p>

        <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-gold-dark">
          {layanan.aksi}
          <ArrowRight className="size-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
        </span>
      </Kartu>
    </Link>
  )
}

LayananMandiri.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
