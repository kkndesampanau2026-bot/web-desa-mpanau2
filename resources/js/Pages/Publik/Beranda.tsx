import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import {
  ArrowRight,
  FileSignature,
  FileText,
  Landmark,
  MapPin,
  Newspaper,
  ShoppingBag,
  Users,
  Wallet,
} from 'lucide-react'
import { formatTanggal } from '@/lib/format'
import { GAYA_WADAH, IsiHalaman, JudulSeksi, Kartu, TombolTautan } from '@/Components/ui'
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
    ke: '/potensi?kategori=Ekonomi',
    ikon: ShoppingBag,
    judul: 'Ekonomi Desa',
    isi: 'Katalog produk unggulan pelaku UMKM warga desa.',
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

  // Banner unggahan perangkat desa (Pengaturan Umum). Null berarti belum
  // pernah diunggah; foto bawaan di bawah yang dipakai.
  const bannerAdmin = pengaturan?.banner

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
        Hero: foto desa sebagai latar, di bawah lapisan navy.

        Tingginya satu layar penuh dikurangi header (`--tinggi-header`), jadi
        banner dan navigasi bersama-sama mengisi tepat satu layar tanpa
        menyisakan potongan seksi berikutnya yang mengintip di bawahnya. Pada
        ponsel dipakai 75svh: layar setinggi 844px yang terisi hero seluruhnya
        membuat pengunjung harus menggulir sebelum tahu situs ini berisi apa.

        Fotonya boleh diganti perangkat desa dari Pengaturan Umum. Bila belum
        pernah diunggah, dipakai foto bawaan yang disajikan lewat `<picture>`
        dengan tiga ukuran: berkas aslinya PNG 2,3 MB — memuatnya apa adanya
        berarti pengunjung ber-4G menunggu seberat itu sebelum melihat apa pun,
        padahal hero inilah yang tampil pertama (PRD 12.1). Varian WebP 800px
        hanya 49 KB. Unggahan admin ditempuh jalur yang sama lewat
        `MediaService`: dikecilkan ke 1920px dan dikonversi ke WebP.

        Lapisan navy di atasnya BUKAN hiasan: teks putih dan label emas kecil
        harus tetap terbaca di atas foto apa pun yang diunggah admin — termasuk
        langit cerah. Dasarnya 80% — pada bagian paling terang sekalipun, teks
        putih tetap berkontras ±9:1 dan label emas-muda ±5,8:1 (ambang WCAG
        4,5:1 untuk teks kecil). Gradiennya menambah kepekatan di sisi kiri
        tempat judul berada, dan menipis ke kanan sehingga fotonya paling
        terbaca di sana — di mana hanya ada kutipan visi berhuruf besar.

        Label kecil memakai `gold-light`, bukan `gold`: emas tua berkontras
        hanya ±4,2:1 di atas latar bercampur foto ini, sedangkan di atas navy
        polos ia aman. Perbedaan yang mudah terlewat justru karena keduanya
        terlihat serupa.
      */}
      <section className="relative isolate flex min-h-[75svh] items-center overflow-hidden border-b-4 border-gold bg-navy lg:min-h-[calc(100svh-var(--tinggi-header))]">
        {bannerAdmin ? (
          <img
            src={bannerAdmin}
            alt=""
            fetchPriority="high"
            decoding="async"
            className="absolute inset-0 -z-10 size-full object-cover object-center"
          />
        ) : (
          <picture>
            <source
              type="image/webp"
              sizes="100vw"
              srcSet="/gambar/banner-beranda-800.webp 800w, /gambar/banner-beranda-1200.webp 1200w, /gambar/banner-beranda-1672.webp 1672w"
            />
            <img
              src="/gambar/banner-beranda-1200.jpg"
              sizes="100vw"
              srcSet="/gambar/banner-beranda-800.jpg 800w, /gambar/banner-beranda-1200.jpg 1200w, /gambar/banner-beranda-1672.jpg 1672w"
              alt=""
              fetchPriority="high"
              decoding="async"
              className="absolute inset-0 -z-10 size-full object-cover object-center"
            />
          </picture>
        )}

        <div
          aria-hidden="true"
          className="absolute inset-0 -z-10 bg-navy/80 bg-gradient-to-r from-navy/60 via-navy/25 to-transparent"
        />

        <div className={`${GAYA_WADAH} py-14 sm:py-16`}>
          <div className="grid items-center gap-10 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:gap-16">
            <div>
              <p className="flex items-center gap-3 text-xs font-semibold tracking-[0.14em] text-gold-light uppercase">
                <span aria-hidden="true" className="h-px w-8 bg-gold-light/60" />
                Situs Resmi Pemerintah Desa
              </p>

              <h1 className="font-heading mt-4 text-3xl leading-tight font-bold text-white sm:text-4xl lg:text-5xl">
                {namaDesa}
              </h1>

              {wilayah && <p className="mt-3 text-sm text-white/75 sm:text-base">{wilayah}</p>}

              <div className="mt-8 flex flex-wrap gap-3">
                <TombolTautan ke="/profil" gaya="utama" ukuran="besar">
                  Profil Desa
                  <ArrowRight className="size-4" aria-hidden="true" />
                </TombolTautan>
                <Link
                  href="/layanan-mandiri"
                  className="inline-flex items-center gap-2 rounded-full border border-white/40 px-6 py-3 text-base font-semibold text-white transition hover:border-white hover:bg-white/10"
                >
                  <FileSignature className="size-4" aria-hidden="true" />
                  Layanan Mandiri
                </Link>
              </div>
            </div>

            {profil?.visi && (
              <blockquote className="border-l-2 border-gold-light/70 pl-5 lg:pl-8">
                <p className="font-heading text-lg leading-relaxed text-white sm:text-xl">
                  “{profil.visi}”
                </p>
                <footer className="mt-4 text-xs font-semibold tracking-[0.14em] text-gold-light uppercase">
                  Visi Desa
                </footer>
              </blockquote>
            )}
          </div>
        </div>
      </section>

      <IsiHalaman lebar="lebar">
        <section aria-labelledby="jelajahi">
          <JudulSeksi id="jelajahi">Jelajahi Informasi Desa</JudulSeksi>

          {/*
            Ikon dibiarkan telanjang di samping judul, bukan didudukkan di
            dalam kotak berwarna. Enam kotak seragam berisi ikon adalah pola
            kartu yang paling cepat membuat halaman terasa dirakit dari
            templat; garis tepi kartu sudah cukup memisahkan satu dari lainnya.
          */}
          <ul className="grid gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
            {PINTASAN.map((p) => (
              <li key={p.ke}>
                <Link href={p.ke} className="group block h-full">
                  <Kartu interaktif className="flex h-full items-start gap-4 p-5 sm:p-6">
                    <p.ikon
                      className="mt-0.5 size-5 shrink-0 text-gold-dark"
                      aria-hidden="true"
                    />

                    <div className="min-w-0">
                      <h3 className="font-heading text-base font-bold text-navy transition group-hover:text-gold-dark">
                        {p.judul}
                      </h3>
                      <p className="mt-1.5 text-sm leading-relaxed text-slate-600">{p.isi}</p>
                    </div>
                  </Kartu>
                </Link>
              </li>
            ))}
          </ul>
        </section>

        {beritaTerbaru.length > 0 && (
          <section className="mt-14 sm:mt-16" aria-labelledby="berita-terbaru">
            <JudulSeksi
              id="berita-terbaru"
              aksi={
                <Link
                  href="/berita"
                  className="inline-flex items-center gap-1.5 text-sm font-semibold text-navy transition hover:text-gold-dark"
                >
                  Lihat semua
                  <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
              }
            >
              Berita Terbaru
            </JudulSeksi>

            <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:gap-6">
              {beritaTerbaru.map((item) => (
                <li key={item.id}>
                  <Link href={`/berita/${item.slug}`} className="group block h-full">
                    <Kartu interaktif className="flex h-full flex-col overflow-hidden">
                      {item.gambar_utama ? (
                        <img
                          src={item.gambar_utama}
                          alt=""
                          loading="lazy"
                          className="aspect-[16/10] w-full object-cover"
                        />
                      ) : (
                        <div
                          aria-hidden="true"
                          className="grid aspect-[16/10] w-full place-items-center bg-navy/5 text-navy/20"
                        >
                          <Newspaper className="size-8" />
                        </div>
                      )}

                      <div className="flex flex-1 flex-col p-5">
                        {item.kategori && (
                          <span className="text-xs font-semibold tracking-wide text-gold-dark uppercase">
                            {item.kategori.nama}
                          </span>
                        )}
                        <h3 className="font-heading mt-1.5 font-bold text-navy transition group-hover:text-gold-dark">
                          {item.judul}
                        </h3>
                        <p className="mt-auto pt-4 text-xs text-slate-500">
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
