import { Link } from 'react-router-dom'
import { StatusMuat } from '@/components/StatusMuat'
import { useGaleri } from '@/lib/queries'
import { formatTanggal } from '@/lib/format'

const DESKRIPSI = 'Dokumentasi foto kegiatan dan pembangunan desa.'

export function GaleriPage() {
  const { data, isPending, error } = useGaleri()

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data?.items.length}
      judul="Galeri"
      deskripsi={DESKRIPSI}
    >
      <div className="mx-auto max-w-5xl px-6 py-12">
        <h1 className="font-heading text-2xl font-bold text-navy">Galeri</h1>
        <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

        <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {data?.items.map((album) => (
            <li
              key={album.id}
              className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-sm"
            >
              <Link to={`/galeri/${album.slug}`} className="block">
                {album.cover_image ? (
                  <img
                    src={album.cover_image}
                    alt=""
                    loading="lazy"
                    className="h-44 w-full object-cover"
                  />
                ) : (
                  <div
                    aria-hidden="true"
                    className="flex h-44 w-full items-center justify-center bg-navy/5 text-sm text-slate-400"
                  >
                    Belum ada foto
                  </div>
                )}

                <div className="p-4">
                  <h2 className="font-semibold text-navy">{album.nama_album}</h2>
                  <p className="mt-1 text-sm text-slate-500">
                    {formatTanggal(album.tanggal_kegiatan)} · {album.jumlah_foto} foto
                  </p>
                </div>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </StatusMuat>
  )
}
