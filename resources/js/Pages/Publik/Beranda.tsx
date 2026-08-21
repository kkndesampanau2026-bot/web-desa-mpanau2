import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import {
  ArrowRight,
  BarChart3,
  FileText,
  Landmark,
  MapPin,
  MessageSquareWarning,
  ShoppingBag,
  Users,
  Wallet,
} from 'lucide-react'
import { formatTanggal } from '@/lib/format'
import { IsiHalaman, Kartu, TombolTautan } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { useHalaman } from '@/types/inertia'
import type { BeritaRingkas, Profil } from '@/types/api'

/**
 * Beranda — PRD Bagian 4 no. 1: hero + ringkasan cepat seluruh modul
 * beserta CTA menuju sub-halaman.
 */
const PINTASAN = [
  {
    ke: '/pemerintah',
    ikon: Landmark,
    judul: 'Pemerintah Desa',
    isi: 'Susunan aparat desa dan Badan Permusyawaratan Desa.',
  },
  {
    ke: '/infografis/penduduk',
    ikon: Users,
    judul: 'Data Penduduk',
    isi: 'Statistik kependudukan desa dalam bentuk infografis.',
  },
  {
    ke: '/infografis/apbdes',
    ikon: Wallet,
    judul: 'APBDes',
    isi: 'Transparansi anggaran pendapatan dan belanja desa.',
  },
  {
    ke: '/listing',
    ikon: MapPin,
    judul: 'Peta Desa',
    isi: 'Titik lokasi fasilitas penting di seluruh desa.',
  },
  {
    ke: '/belanja',
    ikon: ShoppingBag,
    judul: 'Belanja UMKM',
    isi: 'Produk unggulan pelaku usaha warga desa.',
  },
  {
    ke: '/ppid',
    ikon: FileText,
    judul: 'PPID',
    isi: 'Layanan keterbukaan informasi publik desa.',
  },
]

export default function Beranda({
  profil,
  berita_terbaru: beritaTerbaru,
}: {
  profil: Profil | null
  berita_terbaru: BeritaRingkas[]
}) {
  // Identitas desa datang dari prop bersama — sudah dipakai header & footer,
  // jadi controller tidak perlu mengirimnya lagi khusus untuk halaman ini.
  const { pengaturan } = useHalaman().props

  const namaDesa = pengaturan?.nama_desa ?? 'Desa Mpanau'
  const wilayah = [
    pengaturan?.wilayah.kecamatan && `Kecamatan ${pengaturan.wilayah.kecamatan}`,
    pengaturan?.wilayah.kabupaten && `Kabupaten ${pengaturan.wilayah.kabupaten}`,
    pengaturan?.wilayah.provinsi,
  ]
    .filter(Boolean)
    .join(', ')

  return (
    <div>
      <Head title={namaDesa} />

      {/*
        Hero navy dengan aksen emas. Tanpa gambar latar: desa belum tentu
        memiliki foto beresolusi tinggi, dan latar warna solid tetap tampak
        rapi sekaligus memuat jauh lebih cepat pada koneksi 4G (PRD 12.1).
      */}
      <section className="relative overflow-hidden border-b-4 border-gold bg-navy">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-gold/10 blur-3xl"
        />

        <div className="relative mx-auto max-w-4xl px-6 py-20 text-center sm:py-24">
          <p className="mb-4 inline-block rounded-full border border-gold/50 px-4 py-1 text-xs font-semibold tracking-widest text-gold uppercase">
            Situs Resmi Pemerintah Desa
          </p>

          <h1 className="font-heading text-4xl leading-tight font-bold text-white sm:text-5xl">
            {namaDesa}
          </h1>

          {wilayah && <p className="mt-4 text-white/70">{wilayah}</p>}

          {profil?.visi && (
            <blockquote className="mx-auto mt-8 max-w-2xl border-t border-white/10 pt-8">
              <p className="font-heading text-lg leading-relaxed text-white/90 italic sm:text-xl">
                “{profil.visi}”
              </p>
              <footer className="mt-3 text-xs tracking-widest text-gold uppercase">
                Visi Desa
              </footer>
            </blockquote>
          )}

          <div className="mt-10 flex flex-wrap justify-center gap-3">
            <TombolTautan ke="/profil" gaya="utama" ukuran="besar">
              Profil Desa
              <ArrowRight className="size-4" aria-hidden="true" />
            </TombolTautan>
            <Link
              href="/pengaduan"
              className="inline-flex items-center gap-2 rounded-full border-2 border-white/25 px-7 py-3 text-base font-semibold text-white transition hover:border-white/60"
            >
              <MessageSquareWarning className="size-4" aria-hidden="true" />
              Kirim Pengaduan
            </Link>
          </div>
        </div>
      </section>

      <IsiHalaman lebar="lebar">
        <section aria-labelledby="jelajahi">
          <h2
            id="jelajahi"
            className="font-heading border-l-4 border-gold pl-3 text-xl font-bold text-navy sm:text-2xl"
          >
            Jelajahi Informasi Desa
          </h2>

          <ul className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {PINTASAN.map((p) => (
              <li key={p.ke}>
                <Link href={p.ke} className="group block h-full">
                  <Kartu interaktif className="flex h-full items-start gap-4 p-6">
                    <span
                      aria-hidden="true"
                      className="grid size-11 shrink-0 place-items-center rounded-xl bg-navy/5 text-navy transition group-hover:bg-gold/15 group-hover:text-gold-dark"
                    >
                      <p.ikon className="size-5" />
                    </span>

                    <div className="min-w-0">
                      <h3 className="font-heading text-base font-bold text-navy">{p.judul}</h3>
                      <p className="mt-1 text-sm leading-relaxed text-slate-600">{p.isi}</p>
                    </div>
                  </Kartu>
                </Link>
              </li>
            ))}
          </ul>
        </section>

        {beritaTerbaru.length > 0 && (
          <section className="mt-14" aria-labelledby="berita-terbaru">
            <div className="flex flex-wrap items-end justify-between gap-3">
              <h2
                id="berita-terbaru"
                className="font-heading border-l-4 border-gold pl-3 text-xl font-bold text-navy sm:text-2xl"
              >
                Berita Terbaru
              </h2>
              <Link
                href="/berita"
                className="inline-flex items-center gap-1.5 text-sm font-semibold text-navy transition hover:text-gold-dark"
              >
                Lihat semua
                <ArrowRight className="size-4" aria-hidden="true" />
              </Link>
            </div>

            <ul className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {beritaTerbaru.map((item) => (
                <li key={item.id}>
                  <Link href={`/berita/${item.slug}`} className="group block h-full">
                    <Kartu interaktif className="flex h-full flex-col overflow-hidden">
                      {item.gambar_utama ? (
                        <img
                          src={item.gambar_utama}
                          alt=""
                          loading="lazy"
                          className="h-40 w-full object-cover"
                        />
                      ) : (
                        <div
                          aria-hidden="true"
                          className="grid h-40 w-full place-items-center bg-navy/5 text-navy/20"
                        >
                          <BarChart3 className="size-8" />
                        </div>
                      )}

                      <div className="flex flex-1 flex-col p-5">
                        {item.kategori && (
                          <span className="text-xs font-semibold tracking-wide text-gold-dark uppercase">
                            {item.kategori.nama}
                          </span>
                        )}
                        <h3 className="font-heading mt-1 font-bold text-navy group-hover:text-gold-dark">
                          {item.judul}
                        </h3>
                        <p className="mt-auto pt-3 text-xs text-slate-500">
                          {formatTanggal(item.tanggal_publish)}
                        </p>
                      </div>
                    </Kartu>
                  </Link>
                </li>
              ))}
            </ul>
          </section>
        )}
      </IsiHalaman>
    </div>
  )
}

Beranda.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
