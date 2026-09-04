import { useState, type ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { Lightbox } from '@/Components/Lightbox'
import { IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { AlbumDetail } from '@/types/api'

export default function AlbumDetailHalaman({ album }: { album: AlbumDetail }) {
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)
  const foto = album.foto

  return (
    <>
      <Head title={album.nama_album} />

      <KepalaHalaman
        kembali={{ ke: '/galeri', label: 'Kembali ke galeri' }}
        eyebrow="Dokumentasi"
        judul={album.nama_album}
        deskripsi={album.deskripsi ?? undefined}
        meta={<span>{formatTanggal(album.tanggal_kegiatan)}</span>}
      />

      <IsiHalaman lebar="lebar">
        {foto.length === 0 ? (
          <p className="rounded-xl border border-dashed border-navy/20 bg-white px-6 py-12 text-center text-slate-600">
            Album ini belum memiliki foto.
          </p>
        ) : (
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
            {foto.map((f, i) => (
              <li key={f.id}>
                <button
                  onClick={() => setFotoAktif(i)}
                  className="block w-full overflow-hidden rounded-lg"
                >
                  <img
                    src={f.url}
                    // Alt text wajib pada gambar konten (PRD 12.4); backend
                    // sudah menyediakan cadangan bila admin belum mengisinya.
                    alt={f.alt_text}
                    loading="lazy"
                    className="aspect-square w-full object-cover transition hover:scale-105"
                  />
                </button>
              </li>
            ))}
          </ul>
        )}

        <Lightbox foto={foto} indeks={fotoAktif} onTutup={() => setFotoAktif(null)} />
      </IsiHalaman>
    </>
  )
}

AlbumDetailHalaman.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
