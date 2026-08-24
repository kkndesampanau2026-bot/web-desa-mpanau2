import { useState, type ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { Lightbox } from '@/Components/Lightbox'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { labelHari } from '@/lib/format'
import type { WisataDetail as Wisata } from '@/types/api'

/** Detail destinasi wisata — PRD 6.11. */
export default function WisataDetail({ wisata }: { wisata: Wisata }) {
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)

  return (
    <div className="mx-auto max-w-3xl px-6 py-10">
      <Head title={wisata.nama} />

      <Link href="/wisata" className="text-sm text-slate-500 hover:text-navy">
        ← Kembali ke daftar wisata
      </Link>

      <h1 className="font-heading mt-6 text-2xl font-bold text-navy">{wisata.nama}</h1>
      {wisata.alamat && <p className="mt-1 text-sm text-slate-500">{wisata.alamat}</p>}

      {wisata.foto.length > 0 && (
        <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
          {wisata.foto.map((f, i) => (
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
              <li key={f} className="rounded-full bg-navy/5 px-3 py-1 text-sm text-slate-700">
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

      <Lightbox foto={wisata.foto} indeks={fotoAktif} onTutup={() => setFotoAktif(null)} />
    </div>
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

WisataDetail.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
