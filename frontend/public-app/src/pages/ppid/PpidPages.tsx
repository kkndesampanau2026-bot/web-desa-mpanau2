import { NavLink, Outlet } from 'react-router-dom'
import {
  ArrowRight,
  CalendarClock,
  Download,
  FileText,
  Gavel,
  Scale,
  Send,
  Siren,
} from 'lucide-react'
import { StatusMuat } from '@/components/StatusMuat'
import {
  IsiHalaman,
  Kartu,
  KepalaHalaman,
  Lencana,
  TombolTautan,
} from '@/components/ui'
import { useDasarHukumPpid, useInformasiPpid } from '@/lib/queries'
import { formatTanggal } from '@/lib/format'
import type { JenisInformasiPpid } from '@/types/api'

/**
 * Modul PPID — PRD 6.14.
 *
 * Struktur mengikuti kategori baku UU No. 14/2008: dasar hukum, informasi
 * berkala, serta-merta, setiap saat, dan formulir permohonan.
 */

const TAB = [
  { ke: '/ppid', label: 'Beranda', ujung: true },
  { ke: '/ppid/dasar-hukum', label: 'Dasar Hukum' },
  { ke: '/ppid/berkala', label: 'Berkala' },
  { ke: '/ppid/serta-merta', label: 'Serta-Merta' },
  { ke: '/ppid/setiap-saat', label: 'Setiap Saat' },
  { ke: '/ppid/permintaan', label: 'Ajukan Permohonan' },
]

export function PpidLayout() {
  return (
    <div>
      {/*
        Sub-navigasi menempel di bawah header utama saat digulir. PPID punya
        enam sub-halaman yang saling terkait; tanpa navigasi yang selalu
        terlihat, pengunjung harus menggulir balik ke atas tiap kali berpindah.
      */}
      <div className="sticky top-17 z-30 border-b border-navy/10 bg-white/95 backdrop-blur">
        <nav
          aria-label="Sub-navigasi PPID"
          className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-6"
        >
          {TAB.map((tab) => (
            <NavLink
              key={tab.ke}
              to={tab.ke}
              end={tab.ujung}
              className={({ isActive }) =>
                `-mb-px shrink-0 border-b-2 px-4 py-3.5 text-sm font-semibold whitespace-nowrap transition ${
                  isActive
                    ? 'border-gold text-navy'
                    : 'border-transparent text-slate-500 hover:text-navy'
                }`
              }
            >
              {tab.label}
            </NavLink>
          ))}
        </nav>
      </div>

      <Outlet />
    </div>
  )
}

/** Halaman pengantar PPID. */
export function PpidBerandaPage() {
  const kategori = [
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

  return (
    <>
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
          <h2
            id="kategori-informasi"
            className="font-heading border-l-4 border-gold pl-3 text-xl font-bold text-navy sm:text-2xl"
          >
            Kategori Informasi Publik
          </h2>

          <ul className="mt-6 grid gap-5 md:grid-cols-3">
            {kategori.map((k) => (
              <li key={k.ke}>
                <NavLink to={k.ke} className="group block h-full">
                  <Kartu interaktif className="flex h-full flex-col p-6">
                    <span
                      aria-hidden="true"
                      className="grid size-11 place-items-center rounded-xl bg-navy/5 text-navy transition group-hover:bg-gold/15 group-hover:text-gold-dark"
                    >
                      <k.ikon className="size-5" />
                    </span>

                    <h3 className="font-heading mt-4 text-lg font-bold text-navy">{k.judul}</h3>
                    <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">{k.isi}</p>

                    <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-navy transition group-hover:text-gold-dark">
                      Lihat dokumen
                      <ArrowRight
                        className="size-4 transition group-hover:translate-x-0.5"
                        aria-hidden="true"
                      />
                    </span>
                  </Kartu>
                </NavLink>
              </li>
            ))}
          </ul>
        </section>

        <section className="mt-12 grid gap-5 lg:grid-cols-3">
          <NavLink to="/ppid/dasar-hukum" className="group lg:col-span-1">
            <Kartu interaktif className="flex h-full items-start gap-4 p-6">
              <span
                aria-hidden="true"
                className="grid size-11 shrink-0 place-items-center rounded-xl bg-navy/5 text-navy"
              >
                <Scale className="size-5" />
              </span>
              <div>
                <h3 className="font-heading text-base font-bold text-navy">Dasar Hukum</h3>
                <p className="mt-1 text-sm text-slate-600">
                  Regulasi yang menjadi landasan keterbukaan informasi di desa.
                </p>
              </div>
            </Kartu>
          </NavLink>

          {/* Ajakan bertindak: kartu navy dengan aksen emas, kontras terkuat
              di halaman ini agar jalur "ajukan permohonan" mudah ditemukan. */}
          <Kartu className="border-none bg-navy p-8 lg:col-span-2">
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

export function DasarHukumPage() {
  const { data, isPending, error } = useDasarHukumPpid()

  const DESKRIPSI =
    'Regulasi yang menjadi dasar penerapan keterbukaan informasi publik di desa.'

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data?.length}
      eyebrow="PPID"
      judul="Dasar Hukum"
      deskripsi={DESKRIPSI}
    >
      <KepalaHalaman eyebrow="PPID" judul="Dasar Hukum" deskripsi={DESKRIPSI} />

      <IsiHalaman>
        <ol className="space-y-4">
          {data?.map((d, i) => (
            <li key={d.judul_regulasi}>
              <Kartu interaktif className="flex items-start gap-5 p-6">
                <span
                  aria-hidden="true"
                  className="font-heading grid size-10 shrink-0 place-items-center rounded-xl bg-gold/15 font-bold text-gold-dark tabular-nums"
                >
                  {i + 1}
                </span>

                <div className="min-w-0 flex-1">
                  <h2 className="font-heading text-base font-bold text-navy sm:text-lg">
                    {d.judul_regulasi}
                  </h2>

                  {(d.nomor_regulasi || d.tahun) && (
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                      <Gavel className="size-4 text-slate-400" aria-hidden="true" />
                      {[d.nomor_regulasi, d.tahun].filter(Boolean).join(' · ')}
                    </p>
                  )}

                  {d.file && (
                    <a
                      href={d.file}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-navy underline-offset-4 hover:text-gold-dark hover:underline"
                    >
                      <Download className="size-4" aria-hidden="true" />
                      Unduh dokumen
                    </a>
                  )}
                </div>
              </Kartu>
            </li>
          ))}
        </ol>
      </IsiHalaman>
    </StatusMuat>
  )
}

const JUDUL_JENIS: Record<JenisInformasiPpid, [string, string]> = {
  berkala: [
    'Informasi Berkala',
    'Dokumen yang wajib dipublikasikan desa secara rutin dan berkala.',
  ],
  'serta-merta': [
    'Informasi Serta-Merta',
    'Informasi yang wajib diumumkan segera tanpa diminta, misalnya keadaan darurat atau bencana.',
  ],
  'setiap-saat': [
    'Informasi Setiap Saat',
    'Dokumen yang tersedia sewaktu-waktu atas permintaan publik.',
  ],
}

const GAYA_URGENSI: Record<string, 'merah' | 'kuning' | 'netral'> = {
  tinggi: 'merah',
  sedang: 'kuning',
  rendah: 'netral',
}

export function InformasiPpidPage({ jenis }: { jenis: JenisInformasiPpid }) {
  const { data, isPending, error } = useInformasiPpid(jenis)
  const [judul, deskripsi] = JUDUL_JENIS[jenis]

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data?.length}
      eyebrow="PPID"
      judul={judul}
      deskripsi={deskripsi}
    >
      <KepalaHalaman eyebrow="PPID" judul={judul} deskripsi={deskripsi} />

      <IsiHalaman>
        <p className="mb-6 text-sm text-slate-500">
          {data?.length} dokumen tersedia
        </p>

        <ul className="space-y-4">
          {data?.map((i) => (
            <li key={i.judul}>
              <Kartu interaktif className="p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <h2 className="font-heading min-w-0 text-base font-bold text-navy sm:text-lg">
                    {i.judul}
                  </h2>

                  {/* Tingkat urgensi disertai teks, bukan warna saja —
                      pembaca yang tidak membedakan warna tetap memahaminya. */}
                  {i.tingkat_urgensi && (
                    <Lencana gaya={GAYA_URGENSI[i.tingkat_urgensi] ?? 'netral'}>
                      <Siren className="size-3" aria-hidden="true" />
                      Urgensi {i.tingkat_urgensi}
                    </Lencana>
                  )}
                </div>

                {i.deskripsi && (
                  <p className="mt-2 text-sm leading-relaxed text-slate-600">{i.deskripsi}</p>
                )}

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-navy/5 pt-4">
                  <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                    {i.kategori && (
                      <span className="rounded-full bg-navy/5 px-2.5 py-1 font-semibold text-navy">
                        {i.kategori}
                      </span>
                    )}
                    {i.periode && <span>{i.periode}</span>}
                    <span className="flex items-center gap-1">
                      <CalendarClock className="size-3.5" aria-hidden="true" />
                      {formatTanggal(i.tanggal_publish)}
                    </span>
                  </p>

                  {i.file && (
                    <a
                      href={i.file}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-1.5 rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white transition hover:bg-navy-light"
                    >
                      <Download className="size-4" aria-hidden="true" />
                      Unduh
                    </a>
                  )}
                </div>
              </Kartu>
            </li>
          ))}
        </ul>
      </IsiHalaman>
    </StatusMuat>
  )
}
