import { useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { MapContainer, Marker, Popup, TileLayer } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { StatusMuat } from '@/components/StatusMuat'
import { usePeta } from '@/lib/queries'
import type { TitikLokasi } from '@/types/api'

const DESKRIPSI =
  'Peta interaktif desa dengan titik lokasi penting: kantor pemerintahan, fasilitas kesehatan, pendidikan, tempat ibadah, wisata, dan UMKM.'

/**
 * Ikon marker dibangun sendiri sebagai SVG inline.
 *
 * Ikon bawaan Leaflet memuat URL gambar relatif terhadap berkas CSS-nya, yang
 * rusak begitu aset di-bundle dan diberi hash oleh Vite — gejalanya marker
 * hilang tanpa pesan galat. Menggambarnya sendiri menghindari persoalan itu
 * sekaligus memungkinkan warna per kategori.
 */
const WARNA_KATEGORI: Record<string, string> = {
  'Kantor Pemerintahan': '#2a78d6',
  'Fasilitas Kesehatan': '#e34948',
  'Fasilitas Pendidikan': '#eda100',
  'Tempat Ibadah': '#4a3aa7',
  Wisata: '#1baf7a',
  'UMKM/Usaha': '#eb6834',
}

const WARNA_LAINNYA = '#52514e'

function ikonUntuk(kategori: string): L.DivIcon {
  const warna = WARNA_KATEGORI[kategori] ?? WARNA_LAINNYA

  return L.divIcon({
    className: '',
    html: `
      <svg width="26" height="34" viewBox="0 0 26 34" xmlns="http://www.w3.org/2000/svg">
        <path d="M13 0C5.8 0 0 5.8 0 13c0 9.7 13 21 13 21s13-11.3 13-21C26 5.8 20.2 0 13 0z"
              fill="${warna}" stroke="#fff" stroke-width="2"/>
        <circle cx="13" cy="13" r="4.5" fill="#fff"/>
      </svg>`,
    iconSize: [26, 34],
    iconAnchor: [13, 34],
    popupAnchor: [0, -32],
  })
}

/** Peta Desa & Titik Lokasi — PRD 6.9. */
export function ListingPage() {
  const [params, setParams] = useSearchParams()
  const kategori = params.get('kategori') ?? undefined

  const { data, isPending, error } = usePeta(kategori)
  const [dipilih, setDipilih] = useState<number | null>(null)

  const pusat = useMemo<[number, number]>(
    () => [data?.pusat_peta.latitude ?? -0.9553, data?.pusat_peta.longitude ?? 119.9089],
    [data],
  )

  function gantiKategori(pilihan?: string) {
    const baru = new URLSearchParams(params)
    if (pilihan) baru.set('kategori', pilihan)
    else baru.delete('kategori')
    setParams(baru)
  }

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data && !kategori}
      judul="Peta Desa & Titik Lokasi"
      deskripsi={DESKRIPSI}
    >
      <div className="mx-auto max-w-6xl px-6 py-10">
        <h1 className="font-heading text-2xl font-bold text-navy">Peta Desa &amp; Titik Lokasi</h1>
        <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

        {data && data.kategori_tersedia.length > 0 && (
          <nav aria-label="Filter kategori" className="mt-6 flex flex-wrap gap-2">
            <Chip aktif={!kategori} onClick={() => gantiKategori()}>
              Semua
            </Chip>
            {data.kategori_tersedia.map((k) => (
              <Chip key={k} aktif={kategori === k} onClick={() => gantiKategori(k)}>
                <span
                  aria-hidden="true"
                  className="mr-1.5 inline-block size-2.5 rounded-full align-middle"
                  style={{ backgroundColor: WARNA_KATEGORI[k] ?? WARNA_LAINNYA }}
                />
                {k}
              </Chip>
            ))}
          </nav>
        )}

        {!data?.titik.length ? (
          <p className="mt-10 rounded-lg border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
            Belum ada titik lokasi pada kategori ini.
          </p>
        ) : (
          <div className="mt-8 grid gap-6 lg:grid-cols-[2fr_1fr]">
            <div className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm">
              <MapContainer
                center={pusat}
                zoom={15}
                scrollWheelZoom={false}
                style={{ height: 480, width: '100%' }}
              >
                {/* OpenStreetMap: gratis dan tanpa kunci API (PRD 6.9).
                    Atribusi WAJIB ditampilkan sesuai syarat penggunaannya. */}
                <TileLayer
                  attribution='&copy; Kontributor <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                  url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />

                {data.titik.map((t) => (
                  <Marker
                    key={t.id}
                    position={[t.latitude, t.longitude]}
                    icon={ikonUntuk(t.kategori)}
                    eventHandlers={{ click: () => setDipilih(t.id) }}
                  >
                    <Popup>
                      <IsiPopup titik={t} />
                    </Popup>
                  </Marker>
                ))}
              </MapContainer>
            </div>

            {/*
              Daftar teks di samping peta bukan sekadar pelengkap: peta berbasis
              kanvas tidak terbaca pembaca layar dan sulit dipakai lewat
              keyboard, sehingga daftar inilah yang membuat informasi lokasi
              tetap terjangkau semua pengunjung (PRD 12.4).
            */}
            <div>
              <h2 className="text-sm font-medium text-slate-700">
                Daftar Lokasi ({data.titik.length})
              </h2>

              <ul className="mt-3 max-h-[440px] space-y-2 overflow-y-auto pr-1">
                {data.titik.map((t) => (
                  <li
                    key={t.id}
                    className={`rounded-lg border p-3 transition ${
                      dipilih === t.id
                        ? 'border-navy bg-navy/3'
                        : 'border-navy/10 hover:border-navy/40'
                    }`}
                  >
                    <div className="flex items-start gap-2">
                      <span
                        aria-hidden="true"
                        className="mt-1.5 inline-block size-2.5 shrink-0 rounded-full"
                        style={{
                          backgroundColor: WARNA_KATEGORI[t.kategori] ?? WARNA_LAINNYA,
                        }}
                      />
                      <div className="min-w-0">
                        <p className="font-semibold text-navy">{t.nama}</p>
                        <p className="text-xs text-slate-500">{t.kategori}</p>
                        {t.alamat && (
                          <p className="mt-1 text-xs text-slate-600">{t.alamat}</p>
                        )}
                        {t.tautan && (
                          <Link
                            to={t.tautan}
                            className="mt-1 inline-block text-xs text-slate-700 underline hover:text-navy"
                          >
                            Lihat halaman
                          </Link>
                        )}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )}
      </div>
    </StatusMuat>
  )
}

function IsiPopup({ titik }: { titik: TitikLokasi }) {
  return (
    <div className="min-w-40">
      <p className="font-semibold text-navy">{titik.nama}</p>
      <p className="text-xs text-slate-500">{titik.kategori}</p>
      {titik.deskripsi && <p className="mt-1 text-xs text-slate-700">{titik.deskripsi}</p>}
      {titik.tautan && (
        <Link to={titik.tautan} className="mt-2 inline-block text-xs underline">
          Lihat halaman
        </Link>
      )}
    </div>
  )
}

function Chip({
  aktif,
  onClick,
  children,
}: {
  aktif: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      onClick={onClick}
      aria-current={aktif ? 'true' : undefined}
      className={`rounded-full px-3 py-1 text-sm transition ${
        aktif ? 'bg-navy text-white' : 'bg-navy/5 text-slate-700 hover:bg-navy/10'
      }`}
    >
      {children}
    </button>
  )
}
