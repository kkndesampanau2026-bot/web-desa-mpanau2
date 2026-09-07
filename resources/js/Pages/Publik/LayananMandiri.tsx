import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import {
  ArrowRight,
  FileSearch,
  FileSignature,
  FileText,
  MessageSquareWarning,
  TicketCheck,
  type LucideIcon,
} from 'lucide-react'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'

const DESKRIPSI =
  'Berbagai layanan warga yang dapat diakses mandiri tanpa perlu datang ke kantor desa: mengajukan surat pengantar, memantau proses persetujuannya, memohon informasi publik kepada PPID Desa, hingga melacak pengaduan yang sudah dikirim.'

interface Layanan {
  ikon: LucideIcon
  judul: string
  deskripsi: string
  ke: string
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
   * Hanya PELACAKANNYA yang berdiri di sini, bukan formulir pengaduannya.
   *
   * Mengirim aduan sudah punya jalannya sendiri lewat tombol mengambang
   * "Aduan Warga" yang menemani pengunjung di SETIAP halaman — menaruh kartu
   * kedua menuju formulir yang sama hanya menggandakan pintu masuk. Yang
   * selama ini tidak punya pintu justru pelacakannya: warga yang sudah
   * memegang nomor tiket harus menebak alamatnya sendiri.
   */
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
      <Head title="Layanan Mandiri" />

      <KepalaHalaman eyebrow="Pusat Layanan Warga" judul="Layanan Mandiri" deskripsi={DESKRIPSI} />

      {/*
        Empat kolom hanya mulai xl. Di bawah itu dua kolom: dengan empat
        layanan, kisi tiga kolom menyisakan satu kolom menganga pada baris
        kedua, sedangkan satu baris berisi empat kartu baru muat tanpa kartunya
        menjadi terlalu sempit ketika kerangka sudah selebar 1280px.
      */}
      <IsiHalaman lebar="lebar">
        <div className="grid gap-4 sm:grid-cols-2 sm:gap-5 xl:grid-cols-4">
          {LAYANAN.map((layanan) => (
            <Link key={layanan.ke} href={layanan.ke} className="group">
              <Kartu interaktif className="flex h-full flex-col p-5 sm:p-6">
                <layanan.ikon className="size-5 shrink-0 text-gold-dark" aria-hidden="true" />

                <h2 className="font-heading mt-3.5 text-base font-bold text-navy transition group-hover:text-gold-dark sm:text-lg">
                  {layanan.judul}
                </h2>
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
