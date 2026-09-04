import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { ArrowRight, CalendarClock, FileText, Scale, Send, Siren } from 'lucide-react'
import { IsiHalaman, JudulSeksi, Kartu, KepalaHalaman, TombolTautan } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'

/** Halaman pengantar PPID — PRD 6.14. */
const KATEGORI = [
  {
    ke: '/ppid/berkala',
    ikon: CalendarClock,
    judul: 'Informasi Berkala',
    isi: 'Dokumen yang wajib diumumkan secara rutin, seperti laporan realisasi APBDes dan laporan penyelenggaraan pemerintahan desa.',
  },
  {
    ke: '/ppid/serta-merta',
    ikon: Siren,
    judul: 'Informasi Serta-Merta',
    isi: 'Informasi yang wajib diumumkan segera tanpa perlu diminta, terutama yang menyangkut keselamatan atau kepentingan mendesak warga.',
  },
  {
    ke: '/ppid/setiap-saat',
    ikon: FileText,
    judul: 'Informasi Setiap Saat',
    isi: 'Dokumen yang tersedia kapan saja atas permintaan publik, seperti Peraturan Desa, RPJMDes, dan daftar aset desa.',
  },
]

export default function PpidBeranda() {
  return (
    <>
      <Head title="PPID" />

      <KepalaHalaman
        eyebrow="Keterbukaan Informasi Publik"
        judul="Pejabat Pengelola Informasi dan Dokumentasi"
        deskripsi="PPID Desa bertugas menyediakan dan melayani permintaan informasi publik sesuai Undang-Undang Nomor 14 Tahun 2008 tentang Keterbukaan Informasi Publik."
        aksi={
          <TombolTautan ke="/ppid/permintaan" gaya="utama" ukuran="besar">
            Ajukan Permohonan
            <ArrowRight className="size-4" aria-hidden="true" />
          </TombolTautan>
        }
      />


      <IsiHalaman lebar="lebar">
        <section aria-labelledby="kategori-informasi">
          <JudulSeksi id="kategori-informasi">Kategori Informasi Publik</JudulSeksi>

          <ul className="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
            {KATEGORI.map((k) => (
              <li key={k.ke}>
                <Link href={k.ke} className="group block h-full">
                  <Kartu interaktif className="flex h-full flex-col p-5 sm:p-6">
                    <k.ikon className="size-5 shrink-0 text-gold-dark" aria-hidden="true" />

                    <h3 className="font-heading mt-3.5 text-base font-bold text-navy transition group-hover:text-gold-dark sm:text-lg">
                      {k.judul}
                    </h3>
                    <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">{k.isi}</p>

                    <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-navy transition group-hover:text-gold-dark">
                      Lihat dokumen
                      <ArrowRight
                        className="size-4 transition group-hover:translate-x-0.5"
                        aria-hidden="true"
                      />
                    </span>
                  </Kartu>
                </Link>
              </li>
            ))}
          </ul>
        </section>

        <section className="mt-12 grid gap-5 lg:grid-cols-3 lg:items-stretch">
          <Link href="/ppid/dasar-hukum" className="group lg:col-span-1">
            <Kartu interaktif className="flex h-full items-start gap-4 p-5 sm:p-6">
              <Scale className="mt-0.5 size-5 shrink-0 text-gold-dark" aria-hidden="true" />
              <div>
                <h3 className="font-heading text-base font-bold text-navy">Dasar Hukum</h3>
                <p className="mt-1 text-sm text-slate-600">
                  Regulasi yang menjadi landasan keterbukaan informasi di desa.
                </p>
              </div>
            </Kartu>
          </Link>

          {/* Ajakan bertindak: kartu navy dengan aksen emas, kontras terkuat
              di halaman ini agar jalur "ajukan permohonan" mudah ditemukan.
              `bg-navy!` (bukan `bg-navy` biasa): urutan `.bg-white` bawaan
              `Kartu` pada CSS hasil build kebetulan jatuh SETELAH `.bg-navy`,
              sehingga tanpa `!` warna putih itu yang menang. */}
          <Kartu className="border-none bg-navy! p-6 sm:p-8 lg:col-span-2">
            <div className="flex flex-wrap items-center justify-between gap-6">
              <div className="max-w-md">
                <h2 className="font-heading text-xl font-bold text-white">
                  Belum menemukan yang Anda cari?
                </h2>
                <p className="mt-2 text-sm leading-relaxed text-white/75">
                  Ajukan permohonan informasi secara daring. Anda akan menerima nomor
                  registrasi untuk memantau tindak lanjutnya — tanpa perlu membuat akun.
                </p>
              </div>

              <TombolTautan ke="/ppid/permintaan" gaya="utama">
                <Send className="size-4" aria-hidden="true" />
                Ajukan Permohonan
              </TombolTautan>
            </div>
          </Kartu>
        </section>
      </IsiHalaman>
    </>
  )
}

PpidBeranda.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
