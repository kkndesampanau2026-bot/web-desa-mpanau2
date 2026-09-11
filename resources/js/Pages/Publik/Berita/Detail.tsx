import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { CalendarDays, Eye, Newspaper, User } from 'lucide-react'
import { KontenKaya } from '@/Components/KontenKaya'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatAngka, formatTanggal } from '@/lib/format'
import type { BeritaDetail, BeritaRingkas } from '@/types/api'

interface Props {
  berita: BeritaDetail
  berita_terbaru: BeritaRingkas[]
}

export default function BeritaDetailHalaman({ berita, berita_terbaru }: Props) {
  return (
    <>
      {/*
        Meta tag per artikel — PRD 6.10 & 12.4.
        Dulu disetel lewat useEffect yang menulis langsung ke document.title
        dan meta[name=description], lengkap dengan fungsi pembersih untuk
        mengembalikan nilai semula saat pengunjung berpindah halaman. Inertia
        mengurus siklus itu sendiri, jadi yang tersisa tinggal menyatakan
        isinya.
      */}
      <Head title={berita.meta.title ?? berita.judul}>
        {berita.meta.description && (
          <meta name="description" content={berita.meta.description} head-key="description" />
        )}
        {berita.meta.og_image && (
          <meta property="og:image" content={berita.meta.og_image} head-key="og:image" />
        )}
      </Head>

      {/*
        Kepala artikel memakai komponen yang sama dengan seluruh halaman lain
        — judul beritalah yang menjadi H1 di sini, menggantikan judul modul.

        Lebarnya `lebar`, bukan `sempit`: atas permintaan pemilik produk,
        halaman detail berita kini selebar kerangka dengan sidebar berita
        terbaru di sisi kanan (lihat docs/DEVIASI.md §A6).
      */}
      <KepalaHalaman
        lebar="lebar"
        kembali={{ ke: '/berita', label: 'Kembali ke daftar berita' }}
        eyebrow={berita.kategori?.nama}
        judul={berita.judul}
        meta={
          <>
            <span className="flex items-center gap-1.5">
              <CalendarDays className="size-4" aria-hidden="true" />
              {formatTanggal(berita.tanggal_publish)}
            </span>
            {berita.penulis && (
              <span className="flex items-center gap-1.5">
                <User className="size-4" aria-hidden="true" />
                {berita.penulis}
              </span>
            )}
            <span className="flex items-center gap-1.5">
              <Eye className="size-4" aria-hidden="true" />
              {formatAngka(berita.jumlah_dilihat)} kali dilihat
            </span>
          </>
        }
      />

      <IsiHalaman lebar="lebar">
        {/*
          Dua kolom mulai `lg`: artikel mengambil sisa lebar, sidebar selebar
          tetap. `minmax(0,1fr)` — bukan `1fr` — supaya tabel atau gambar
          lebar di dalam konten tidak mendorong kolom artikel melampaui
          kisinya. Di bawah `lg`, sidebar turun ke bawah artikel.
        */}
        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_24rem] xl:gap-8">
          <Kartu className="p-5 sm:p-8 lg:p-10">
            <article>
              {berita.gambar_utama && (
                <img
                  src={berita.gambar_utama}
                  alt=""
                  className="mb-8 aspect-video w-full rounded-xl object-cover"
                />
              )}

              <KontenKaya html={berita.konten} />

              {berita.tags && berita.tags.length > 0 && (
                <ul className="mt-10 flex flex-wrap gap-2 border-t border-navy/10 pt-6">
                  {berita.tags.map((tag) => (
                    <li
                      key={tag.slug}
                      className="rounded-full bg-navy/5 px-3 py-1 text-sm text-navy"
                    >
                      #{tag.nama}
                    </li>
                  ))}
                </ul>
              )}
            </article>
          </Kartu>

          <SidebarBeritaTerbaru items={berita_terbaru} />
        </div>
      </IsiHalaman>
    </>
  )
}

/**
 * Sidebar berita lain.
 *
 * Tidak dibuat lengket: header situs satu-satunya elemen sticky (CLAUDE.md
 * prinsip 6), dan sidebar setinggi enam entri akan tertimbun di layar pendek.
 */
function SidebarBeritaTerbaru({ items }: { items: BeritaRingkas[] }) {
  return (
    <Kartu className="p-5 sm:p-6">
      <aside aria-labelledby="judul-berita-terbaru">
        <h2
          id="judul-berita-terbaru"
          className="font-heading border-l-[3px] border-gold pl-3 text-lg font-bold text-navy"
        >
          Berita Terbaru
        </h2>

        {items.length === 0 ? (
          <p className="mt-4 text-sm text-slate-600">Belum ada berita lain.</p>
        ) : (
          <ul className="mt-5 space-y-5">
            {items.map((item) => (
              <li key={item.id}>
                <Link href={`/berita/${item.slug}`} className="group flex gap-4">
                  {item.gambar_utama ? (
                    <img
                      src={item.gambar_utama}
                      alt=""
                      loading="lazy"
                      className="aspect-square w-20 shrink-0 rounded-lg object-cover sm:w-22"
                    />
                  ) : (
                    <div
                      aria-hidden="true"
                      className="grid aspect-square w-20 shrink-0 place-items-center rounded-lg bg-navy/5 text-navy/20 sm:w-22"
                    >
                      <Newspaper className="size-6" />
                    </div>
                  )}

                  <div className="min-w-0">
                    <h3 className="line-clamp-2 text-sm leading-snug font-semibold text-navy transition group-hover:text-gold-dark">
                      {item.judul}
                    </h3>
                    <p className="mt-1.5 flex items-center gap-1.5 text-xs text-slate-500">
                      <CalendarDays className="size-3.5 shrink-0" aria-hidden="true" />
                      {formatTanggal(item.tanggal_publish)}
                    </p>
                    <p className="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                      <Eye className="size-3.5 shrink-0" aria-hidden="true" />
                      Dilihat {formatAngka(item.jumlah_dilihat)} kali
                    </p>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </aside>
    </Kartu>
  )
}

BeritaDetailHalaman.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
