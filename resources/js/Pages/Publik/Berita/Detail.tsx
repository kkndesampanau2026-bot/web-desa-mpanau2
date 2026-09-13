import type { ReactNode } from 'react'
import { CalendarDays, Eye, Newspaper, User } from 'lucide-react'
import { DetailDenganSidebar } from '@/Components/DetailDenganSidebar'
import { KontenKaya } from '@/Components/KontenKaya'
import { SeoMeta } from '@/Components/SeoMeta'
import { IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatAngka, formatTanggal } from '@/lib/format'
import type { BeritaDetail, BeritaRingkas } from '@/types/api'

interface Props {
  berita: BeritaDetail
  berita_terbaru: BeritaRingkas[]
}

export default function BeritaDetailHalaman({ berita, berita_terbaru }: Props) {
  const metaJudul = berita.meta.title ?? berita.judul
  const metaDeskripsi = berita.meta.description ?? berita.ringkasan ?? undefined

  return (
    <>
      <SeoMeta
        title={metaJudul}
        description={metaDeskripsi}
        ogImage={berita.meta.og_image ?? berita.gambar_utama ?? undefined}
        ogType="article"
        schema={{
          '@context': 'https://schema.org',
          '@type': 'NewsArticle',
          headline: berita.judul,
          description: metaDeskripsi,
          image: berita.gambar_utama ? [berita.gambar_utama] : undefined,
          datePublished: berita.tanggal_publish,
          author: {
            '@type': 'Person',
            name: berita.penulis ?? 'Pemerintah Desa Mpanau',
          },
          publisher: {
            '@type': 'GovernmentOrganization',
            name: 'Pemerintah Desa Mpanau',
            url: window?.location?.origin ?? 'https://mpanau.desa.id',
          },
          articleSection: berita.kategori?.nama,
        }}
      />

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
        <DetailDenganSidebar
          judulSamping="Berita Terbaru"
          ikonCadangan={Newspaper}
          pesanKosong="Belum ada berita lain."
          entri={berita_terbaru.map((item) => ({
            kunci: item.id,
            ke: `/berita/${item.slug}`,
            judul: item.judul,
            gambar: item.gambar_utama,
            keterangan: [
              { ikon: CalendarDays, teks: formatTanggal(item.tanggal_publish) },
              { ikon: Eye, teks: `Dilihat ${formatAngka(item.jumlah_dilihat)} kali` },
            ],
          }))}
        >
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
                  <li key={tag.slug} className="rounded-full bg-navy/5 px-3 py-1 text-sm text-navy">
                    #{tag.nama}
                  </li>
                ))}
              </ul>
            )}
          </article>
        </DetailDenganSidebar>
      </IsiHalaman>
    </>
  )
}

BeritaDetailHalaman.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
