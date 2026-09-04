import type { ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { CalendarDays, Eye, User } from 'lucide-react'
import { KontenKaya } from '@/Components/KontenKaya'
import { IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatAngka, formatTanggal } from '@/lib/format'
import type { BeritaDetail } from '@/types/api'

export default function BeritaDetailHalaman({ berita }: { berita: BeritaDetail }) {
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
      */}
      <KepalaHalaman
        lebar="sempit"
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

      <IsiHalaman lebar="sempit">
        <article>
          {berita.gambar_utama && (
            <img
              src={berita.gambar_utama}
              alt=""
              className="mb-8 aspect-[16/9] w-full rounded-xl border border-navy/10 object-cover"
            />
          )}

          <KontenKaya html={berita.konten} />

          {berita.tags && berita.tags.length > 0 && (
            <ul className="mt-10 flex flex-wrap gap-2 border-t border-navy/10 pt-6">
              {berita.tags.map((tag) => (
                <li key={tag.slug} className="rounded-full bg-navy/5 px-3 py-1 text-sm text-navy">
                  #{tag.nama}
                </li>
              ))}
            </ul>
          )}
        </article>
      </IsiHalaman>
    </>
  )
}

BeritaDetailHalaman.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
