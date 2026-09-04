import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { Berhalaman } from '@/types/inertia'
import type { AlbumRingkas } from '@/types/api'

const DESKRIPSI = 'Dokumentasi foto kegiatan dan pembangunan desa.'

export default function GaleriIndex({ galeri }: { galeri: Berhalaman<AlbumRingkas> }) {
  if (galeri.items.length === 0) {
    return (
      <>
        <Head title="Galeri" />
        <EmptyState judul="Galeri" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <>
      <Head title="Galeri" />

      <KepalaHalaman eyebrow="Dokumentasi" judul="Galeri" deskripsi={DESKRIPSI} />

      <IsiHalaman lebar="lebar">
        <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {galeri.items.map((album) => (
            <li key={album.id}>
              <Link href={`/galeri/${album.slug}`} className="group block h-full">
                <Kartu interaktif className="h-full overflow-hidden">
                  {/*
                    Rasio tetap, bukan tinggi tetap: dengan `h-44`, kartu pada
                    kolom yang menyempit tetap setinggi 176px sehingga gambar
                    terpotong makin dalam. Rasio membuat gambar menyusut utuh
                    mengikuti lebar kolomnya.
                  */}
                  {album.cover_image ? (
                    <img
                      src={album.cover_image}
                      alt=""
                      loading="lazy"
                      className="aspect-[4/3] w-full object-cover"
                    />
                  ) : (
                    <div
                      aria-hidden="true"
                      className="flex aspect-[4/3] w-full items-center justify-center bg-navy/5 text-sm text-slate-400"
                    >
                      Belum ada foto
                    </div>
                  )}

                  <div className="p-4">
                    <h2 className="font-heading font-bold text-navy transition group-hover:text-gold-dark">
                      {album.nama_album}
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                      {formatTanggal(album.tanggal_kegiatan)} · {album.jumlah_foto} foto
                    </p>
                  </div>
                </Kartu>
              </Link>
            </li>
          ))}
        </ul>
      </IsiHalaman>
    </>
  )
}

GaleriIndex.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
