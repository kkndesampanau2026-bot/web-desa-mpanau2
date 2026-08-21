import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { StatusMuat } from '@/components/StatusMuat'
import { useAlbum } from '@/lib/queries'
import { formatTanggal } from '@/lib/format'

export function AlbumDetailPage() {
  const { slug } = useParams<{ slug: string }>()
  const { data: album, isPending, error } = useAlbum(slug)
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)

  const foto = album?.foto ?? []

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      judul="Album Tidak Ditemukan"
      deskripsi="Album yang Anda cari tidak tersedia."
    >
      {album && (
        <div className="mx-auto max-w-5xl px-6 py-12">
          <Link to="/galeri" className="text-sm text-slate-500 hover:text-navy">
            ← Kembali ke galeri
          </Link>

          <h1 className="mt-6 font-heading text-2xl font-bold text-navy">{album.nama_album}</h1>
          <p className="mt-1 text-sm text-slate-500">
            {formatTanggal(album.tanggal_kegiatan)}
          </p>
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

          {/* Lightbox — PRD 6.13. */}
          {fotoAktif !== null && foto[fotoAktif] && (
            <div
              role="dialog"
              aria-modal="true"
              aria-label={foto[fotoAktif].alt_text}
              className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
              onClick={() => setFotoAktif(null)}
            >
              <button
                onClick={() => setFotoAktif(null)}
                aria-label="Tutup"
                className="absolute top-4 right-4 rounded-full bg-white/10 px-3 py-1 text-white"
              >
                ✕
              </button>

              <figure onClick={(e) => e.stopPropagation()} className="max-h-full">
                <img
                  src={foto[fotoAktif].url}
                  alt={foto[fotoAktif].alt_text}
                  className="max-h-[80vh] rounded-lg object-contain"
                />
                {foto[fotoAktif].caption && (
                  <figcaption className="mt-3 text-center text-sm text-white/80">
                    {foto[fotoAktif].caption}
                  </figcaption>
                )}
              </figure>
            </div>
          )}
        </div>
      )}
    </StatusMuat>
  )
}
