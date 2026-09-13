import { useState, type ReactNode } from 'react'
import { MapPin, Mountain, Ticket } from 'lucide-react'
import { DetailDenganSidebar } from '@/Components/DetailDenganSidebar'
import { Lightbox } from '@/Components/Lightbox'
import { SeoMeta } from '@/Components/SeoMeta'
import { IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { labelHari } from '@/lib/format'
import { useJelajahPotensi } from '@/lib/tautan'
import type { WisataDetail as Wisata, WisataRingkas } from '@/types/api'

/** Detail destinasi wisata — PRD 6.11. */
export default function WisataDetail({
  wisata,
  wisata_lainnya,
  kembali = '/potensi?kategori=Pariwisata',
}: {
  wisata: Wisata
  /** Destinasi lain untuk sidebar. */
  wisata_lainnya: WisataRingkas[]
  /** Alamat daftar tempat pengunjung berangkat, disusun oleh controller. */
  kembali?: string
}) {
  const [fotoAktif, setFotoAktif] = useState<number | null>(null)
  const jelajah = useJelajahPotensi()

  return (
    <>
      <SeoMeta
        title={wisata.nama}
        description={wisata.deskripsi ?? `Destinasi Wisata ${wisata.nama} di Desa Mpanau. ${wisata.alamat ?? ''}`}
        ogImage={wisata.foto[0]?.url}
        schema={{
          '@context': 'https://schema.org',
          '@type': 'TouristAttraction',
          name: wisata.nama,
          description: wisata.deskripsi ?? undefined,
          image: wisata.foto.length > 0 ? wisata.foto.map((f) => f.url) : undefined,
          address: wisata.alamat ? {
            '@type': 'PostalAddress',
            streetAddress: wisata.alamat,
          } : undefined,
          geo: wisata.koordinat ? {
            '@type': 'GeoCoordinates',
            latitude: wisata.koordinat.latitude,
            longitude: wisata.koordinat.longitude,
          } : undefined,
        }}
      />
      <KepalaHalaman
        lebar="lebar"
        kembali={{ ke: kembali, label: 'Kembali ke daftar wisata' }}
        eyebrow="Pariwisata"
        judul={wisata.nama}
        meta={
          wisata.alamat ? (
            <span className="flex items-center gap-1.5">
              <MapPin className="size-4" aria-hidden="true" />
              {wisata.alamat}
            </span>
          ) : undefined
        }
      />

      <IsiHalaman lebar="lebar">
        <DetailDenganSidebar
          judulSamping="Wisata Lainnya"
          ikonCadangan={Mountain}
          pesanKosong="Belum ada destinasi wisata lain."
          entri={wisata_lainnya.map((w) => ({
            kunci: w.id,
            ke: jelajah.detail(w.slug),
            judul: w.nama,
            gambar: w.foto_utama,
            keterangan: [
              { ikon: MapPin, teks: w.alamat },
              { ikon: Ticket, teks: w.harga_tiket && `Tiket: ${w.harga_tiket}` },
            ],
          }))}
        >
          <div className="space-y-8">
            {wisata.foto.length > 0 && (
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                {wisata.foto.map((f, i) => (
                  <li key={i}>
                    <button
                      type="button"
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

            {wisata.deskripsi && (
              <p className="leading-relaxed whitespace-pre-line text-slate-700">{wisata.deskripsi}</p>
            )}

            <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
              <Fakta label="Harga Tiket" nilai={wisata.harga_tiket} />
              <Fakta label="Kontak Pengelola" nilai={wisata.kontak_pengelola} />
            </dl>

            {wisata.fasilitas.length > 0 && (
              <section>
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
              <section>
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
              <p className="text-sm text-slate-500">
                Koordinat: {wisata.koordinat.latitude}, {wisata.koordinat.longitude}
              </p>
            )}
          </div>
        </DetailDenganSidebar>

        <Lightbox foto={wisata.foto} indeks={fotoAktif} onTutup={() => setFotoAktif(null)} />
      </IsiHalaman>
    </>
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
