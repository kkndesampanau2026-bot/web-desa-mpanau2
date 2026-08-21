import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { StatusMuat } from '@/components/StatusMuat'
import { useWisata, useWisataDetail } from '@/lib/queries'
import { labelHari } from '@/lib/format'

const DESKRIPSI = 'Destinasi wisata desa beserta lokasi, jam operasional, dan fasilitasnya.'

/** Daftar destinasi wisata — PRD 6.11. */
export function WisataPage() {
  const { data, isPending, error } = useWisata()

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data?.length}
      judul="Wisata Desa"
      deskripsi={DESKRIPSI}
    >
      <div className="mx-auto max-w-5xl px-6 py-10">
        <h1 className="font-heading text-2xl font-bold text-navy">Wisata Desa</h1>
        <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

        <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {data?.map((w) => (
            <li
              key={w.id}
              className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-sm"
            >
              <Link to={`/wisata/${w.slug}`} className="block">
                {w.foto_utama ? (
                  <img
                    src={w.foto_utama}
                    alt=""
                    loading="lazy"
                    className="h-44 w-full object-cover"
                  />
                ) : (
                  <div aria-hidden="true" className="h-44 w-full bg-navy/5" />
                )}

                <div className="p-4">
                  <h2 className="font-semibold text-navy">{w.nama}</h2>
                  {w.deskripsi && (
                    <p className="mt-1 line-clamp-2 text-sm text-slate-600">{w.deskripsi}</p>
                  )}
                  {w.harga_tiket && (
                    <p className="mt-2 text-sm font-medium text-slate-700">
                      Tiket: {w.harga_tiket}
                    </p>
                  )}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </StatusMuat>
  )
}

/** Detail destinasi wisata. */
export function WisataDetailPage() {
  const { slug } = useParams<{ slug: string }>()
  const { data: wisata, isPending, error } = useWisataDetail(slug)
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)

  const foto = wisata?.foto ?? []

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      judul="Destinasi Tidak Ditemukan"
      deskripsi="Destinasi wisata yang Anda cari tidak tersedia."
    >
      {wisata && (
        <div className="mx-auto max-w-3xl px-6 py-10">
          <Link to="/wisata" className="text-sm text-slate-500 hover:text-navy">
            ← Kembali ke daftar wisata
          </Link>

          <h1 className="mt-6 font-heading text-2xl font-bold text-navy">{wisata.nama}</h1>
          {wisata.alamat && <p className="mt-1 text-sm text-slate-500">{wisata.alamat}</p>}

          {foto.length > 0 && (
            <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
              {foto.map((f, i) => (
                <li key={i}>
                  <button
                    onClick={() => setFotoAktif(i)}
                    className="block w-full overflow-hidden rounded-lg"
                  >
                    <img
                      src={f.url}
                      alt={f.alt_text}
                      loading="lazy"
                      className="aspect-square w-full object-cover transition hover:scale-105"
                    />
                  </button>
                </li>
              ))}
            </ul>
          )}

          {wisata.deskripsi && <p className="mt-6 text-slate-700">{wisata.deskripsi}</p>}

          <dl className="mt-8 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <Fakta label="Harga Tiket" nilai={wisata.harga_tiket} />
            <Fakta label="Kontak Pengelola" nilai={wisata.kontak_pengelola} />
          </dl>

          {wisata.fasilitas.length > 0 && (
            <section className="mt-8">
              <h2 className="font-heading text-lg font-bold text-navy">Fasilitas</h2>
              <ul className="mt-3 flex flex-wrap gap-2">
                {wisata.fasilitas.map((f) => (
                  <li
                    key={f}
                    className="rounded-full bg-navy/5 px-3 py-1 text-sm text-slate-700"
                  >
                    {f}
                  </li>
                ))}
              </ul>
            </section>
          )}

          {Object.keys(wisata.jam_operasional).length > 0 && (
            <section className="mt-8">
              <h2 className="font-heading text-lg font-bold text-navy">Jam Operasional</h2>
              <dl className="mt-3 max-w-sm space-y-1 text-sm">
                {Object.entries(wisata.jam_operasional).map(([hari, jam]) => (
                  <div key={hari} className="flex justify-between gap-4">
                    <dt className="text-slate-600">{labelHari(hari)}</dt>
                    <dd className="text-slate-800">
                      {jam.libur ? 'Tutup' : `${jam.buka ?? '—'} – ${jam.tutup ?? '—'}`}
                    </dd>
                  </div>
                ))}
              </dl>
            </section>
          )}

          {wisata.koordinat && (
            <p className="mt-8 text-sm text-slate-500">
              Koordinat: {wisata.koordinat.latitude}, {wisata.koordinat.longitude}
            </p>
          )}

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

function Fakta({ label, nilai }: { label: string; nilai: string | null }) {
  return (
    <div>
      <dt className="text-sm text-slate-500">{label}</dt>
      <dd className="text-slate-800">{nilai ?? <span className="text-slate-400">—</span>}</dd>
    </div>
  )
}
