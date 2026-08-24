import { useState, type ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { Lightbox } from '@/Components/Lightbox'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { AlbumDetail } from '@/types/api'

export default function AlbumDetailHalaman({ album }: { album: AlbumDetail }) {
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)
  const foto = album.foto

  return (
    <>
      <Head title={album.nama_album} />

      <div className="mx-auto max-w-5xl px-6 py-12">
        <Link href="/galeri" className="text-sm text-slate-500 hover:text-navy">
          ← Kembali ke galeri
        </Link>

        <h1 className="font-heading mt-6 text-2xl font-bold text-navy">{album.nama_album}</h1>
        <p className="mt-1 text-sm text-slate-500">{formatTanggal(album.tanggal_kegiatan)}</p>
        {album.deskripsi && <p className="mt-3 text-slate-600">{album.deskripsi}</p>}

        {foto.length === 0 ? (
          <p className="mt-10 rounded-lg border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
            Album ini belum memiliki foto.
          </p>
        ) : (
          <ul className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
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
      </div>
    </>
  )
}

AlbumDetailHalaman.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
