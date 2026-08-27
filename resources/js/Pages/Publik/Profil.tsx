import { useState, type ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { MapContainer, Marker, TileLayer } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { BookOpen, Eye, MapPin, Maximize2, Quote, Target, Users } from 'lucide-react'
import { KontenKaya } from '@/Components/KontenKaya'
import { EmptyState } from '@/Components/EmptyState'
import { Lightbox, type FotoLightbox } from '@/Components/Lightbox'
import { IsiHalaman, Kartu } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatAngka } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import type { Profil as ProfilDesa } from '@/types/api'

const DESKRIPSI =
  'Sambutan Kepala Desa, sejarah, visi-misi, struktur organisasi Pemerintah Desa dan BPD, data geografis, serta peta lokasi kantor desa.'

/**
 * Profil Desa — PRD 6.1.
 *
 * Tata letak mengikuti desain Figma (node 33:250): kepala halaman terpusat di
 * atas latar krem, kartu Visi & Misi bergaris emas, kedua bagan struktur
 * organisasi berdampingan, kartu Sejarah, lalu peta lokasi berdampingan dengan
 * ringkasan data geografis & batas wilayah.
 *
 * Bagan Pemerintah Desa dan BPD ditampilkan sebagai gambar utuh yang diunggah
 * admin (CMS Profil Desa), bukan disusun dari data SOTK: bagan resmi desa kerap
 * memuat garis komando, dusun, dan lembaga yang tidak terwakili oleh daftar
 * jabatan. Susunan aparat beserta fotonya tetap tersedia di halaman Pemerintah
 * Desa.
 *
 * Data tiba bersama halaman, jadi satu-satunya keadaan yang tersisa adalah
 * kemungkinan admin desa belum mengisinya — ditangani lewat empty-state.
 */
export default function Profil({ profil }: { profil: ProfilDesa | null }) {
  const { props } = useHalaman()
  const namaDesa = props.pengaturan?.nama_desa ?? 'Desa Mpanau'
  const [baganTampil, setBaganTampil] = useState<number | null>(null)

  if (!profil) {
    return (
      <>
        <Head title="Profil Desa" />
        <EmptyState eyebrow="Tentang Kami" judul={`Profil ${namaDesa}`} deskripsi={DESKRIPSI} />
      </>
    )
  }

  const luasKm2 = luasDalamKm2(profil.geografis.luas_desa_m2, profil.geografis.luas_desa_hektar)
  const pusatPeta = koordinat(profil.peta.latitude, profil.peta.longitude)
  const bagan = daftarBagan(profil)

  return (
    <>
      <Head title="Profil Desa" />

      <IsiHalaman lebar="lebar">
        <div className="space-y-12">
          {/* Kepala halaman terpusat — bukan bilah navy, sesuai desain Figma. */}
          <header className="text-center">
            <p className="text-sm font-semibold tracking-[0.14em] text-gold-dark uppercase">
              Tentang Kami
            </p>
            <h1 className="font-heading mt-2 text-3xl font-bold text-navy sm:text-4xl">
              Profil {namaDesa}
            </h1>
          </header>

          {/* Sambutan tidak ada pada desain Figma, tetapi tetap dirender bila
              admin mengisinya agar konten CMS tidak hilang (PRD 6.1). */}
          {profil.sambutan && (
            <Kartu className="p-6 shadow-md sm:p-8">
              <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                {profil.foto_kepala_desa ? (
                  <img
                    src={profil.foto_kepala_desa}
                    alt={`Foto ${profil.nama_kepala_desa ?? 'Kepala Desa'}`}
                    className="size-28 shrink-0 rounded-full border-4 border-gold object-cover"
                  />
                ) : (
                  <span
                    aria-hidden="true"
                    className="grid size-28 shrink-0 place-items-center rounded-full border-4 border-gold bg-navy/5 text-navy/30"
                  >
                    <Users className="size-10" />
                  </span>
                )}

                <div className="min-w-0">
                  <Quote className="mb-2 size-6 text-gold/50" aria-hidden="true" />
                  <KontenKaya html={profil.sambutan} />
                  {profil.nama_kepala_desa && (
                    <p className="font-heading mt-4 font-bold text-navy">
                      {profil.nama_kepala_desa}
                      <span className="block text-sm font-normal text-slate-500">Kepala Desa</span>
                    </p>
                  )}
                </div>
              </div>
            </Kartu>
          )}

          {/* Visi & Misi — dua kartu putih bergaris emas di atas. */}
          {(profil.visi || profil.misi.length > 0) && (
            <div className="grid gap-6 lg:grid-cols-2">
              {profil.visi && (
                <Kartu className="border-t-4 border-t-gold p-8 shadow-md">
                  <Eye className="size-8 text-gold" aria-hidden="true" />
                  <h2 className="font-heading mt-3 text-xl font-bold text-navy">Visi</h2>
                  <p className="mt-3 leading-relaxed text-slate-700">{profil.visi}</p>
                </Kartu>
              )}

              {profil.misi.length > 0 && (
                <Kartu className="border-t-4 border-t-gold p-8 shadow-md">
                  <Target className="size-8 text-gold" aria-hidden="true" />
                  <h2 className="font-heading mt-3 text-xl font-bold text-navy">Misi</h2>
                  <ul className="mt-3 space-y-3 pl-1">
                    {profil.misi.map((poin, i) => (
                      <li key={i} className="leading-relaxed text-slate-700">
                        {poin}
                      </li>
                    ))}
                  </ul>
                </Kartu>
              )}
            </div>
          )}

          {/* Bagan Pemerintah Desa & BPD — dua gambar berdampingan. Keduanya
              lembaga terpisah (PRD 3.2), karena itu tetap dua bagan sendiri. */}
          {bagan.length > 0 && (
            <section>
              <div className="text-center">
                <h2 className="font-heading text-2xl font-bold text-navy">
                  Bagan Struktur Organisasi
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                  Klik gambar untuk melihatnya lebih besar
                </p>
              </div>

              <div
                className={`mt-6 grid gap-6 ${bagan.length > 1 ? 'lg:grid-cols-2' : 'mx-auto max-w-3xl'}`}
              >
                {bagan.map((b, i) => (
                  <KartuBagan
                    key={b.url}
                    judul={b.caption}
                    gambar={b.url}
                    onLihat={() => setBaganTampil(i)}
                  />
                ))}
              </div>
            </section>
          )}

          {/* Sejarah Desa. */}
          {profil.sejarah && (
            <Kartu className="p-6 shadow-md sm:p-8">
              <div className="flex items-center gap-3">
                <BookOpen className="size-7 shrink-0 text-navy" aria-hidden="true" />
                <h2 className="font-heading text-2xl font-bold text-navy">Sejarah Desa</h2>
              </div>
              <div className="mt-4">
                <KontenKaya html={profil.sejarah} />
              </div>
            </Kartu>
          )}

          {/* Peta Lokasi & Batas Wilayah. */}
          <section>
            <div className="flex items-center gap-3">
              <MapPin className="size-7 shrink-0 text-navy" aria-hidden="true" />
              <h2 className="font-heading text-2xl font-bold text-navy">
                Peta Lokasi &amp; Batas Wilayah
              </h2>
            </div>

            <div className="mt-4 grid gap-6 lg:grid-cols-3">
              <div className="lg:col-span-2">
                {pusatPeta ? (
                  <div className="overflow-hidden rounded-2xl border-4 border-white shadow-xl">
                    <MapContainer
                      center={pusatPeta}
                      zoom={14}
                      scrollWheelZoom={false}
                      style={{ height: 360, width: '100%' }}
                    >
                      {/* OpenStreetMap: gratis & tanpa kunci API (PRD 6.9);
                          atribusi wajib tampil sesuai syarat penggunaannya. */}
                      <TileLayer
                        attribution='&copy; Kontributor <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                      />
                      <Marker position={pusatPeta} icon={IKON_DESA} />
                    </MapContainer>
                  </div>
                ) : (
                  <div className="grid h-[360px] place-items-center rounded-2xl border border-dashed border-navy/20 bg-navy/3 px-6 text-center text-sm text-slate-500">
                    Titik koordinat kantor desa belum diisi.
                  </div>
                )}
              </div>

              <div className="space-y-3">
                <StatDesa label="Luas Wilayah" nilai={luasKm2 ? `${luasKm2} km²` : null} />
                <StatDesa
                  label="Jumlah Penduduk"
                  nilai={
                    profil.geografis.jumlah_penduduk != null
                      ? `${formatAngka(profil.geografis.jumlah_penduduk)} Jiwa`
                      : null
                  }
                />

                <div className="rounded-xl border border-navy/15 bg-white p-4 shadow-sm">
                  <h3 className="text-xs font-semibold tracking-wide text-gold-dark uppercase">
                    Batas Wilayah
                  </h3>
                  <dl className="mt-2 space-y-1 text-sm text-slate-700">
                    {(
                      [
                        ['Utara', profil.geografis.batas.utara],
                        ['Selatan', profil.geografis.batas.selatan],
                        ['Timur', profil.geografis.batas.timur],
                        ['Barat', profil.geografis.batas.barat],
                      ] as const
                    ).map(([arah, nilai]) => (
                      <div key={arah}>
                        <dt className="inline font-bold">{arah}:</dt>{' '}
                        <dd className="inline">
                          {nilai ?? <span className="text-slate-400">—</span>}
                        </dd>
                      </div>
                    ))}
                  </dl>
                </div>
              </div>
            </div>
          </section>
        </div>
      </IsiHalaman>

      <Lightbox foto={bagan} indeks={baganTampil} onTutup={() => setBaganTampil(null)} />
    </>
  )
}

/**
 * Ikon penanda lokasi desa untuk peta Leaflet.
 *
 * Ikon bawaan Leaflet memuat gambar relatif terhadap berkas CSS-nya yang rusak
 * begitu di-bundle Vite (marker hilang tanpa galat); menggambarnya sendiri
 * menghindari persoalan itu — pola yang sama dipakai halaman Peta Desa.
 */
const IKON_DESA = L.divIcon({
  className: '',
  html: `
    <svg width="30" height="38" viewBox="0 0 26 34" xmlns="http://www.w3.org/2000/svg">
      <path d="M13 0C5.8 0 0 5.8 0 13c0 9.7 13 21 13 21s13-11.3 13-21C26 5.8 20.2 0 13 0z"
            fill="#0b2545" stroke="#c9a227" stroke-width="2"/>
      <circle cx="13" cy="13" r="4.5" fill="#c9a227"/>
    </svg>`,
  iconSize: [30, 38],
  iconAnchor: [15, 38],
})

/**
 * Bagan yang benar-benar sudah diunggah admin, siap dipakai kartu & lightbox.
 *
 * Bagan yang kosong tidak menghasilkan kartu kosong: halaman publik lebih baik
 * menyembunyikan yang belum ada daripada memajang bingkai "belum diisi" kepada
 * warga. Urutannya tetap — indeks kartu sama dengan indeks di lightbox.
 */
function daftarBagan(profil: ProfilDesa): Array<FotoLightbox & { caption: string }> {
  const sumber: Array<[string | null, string]> = [
    [profil.bagan_pemerintahan, 'Struktur Organisasi Pemerintah Desa'],
    [profil.bagan_bpd, 'Badan Permusyawaratan Desa (BPD)'],
  ]

  return sumber
    .filter((pasangan): pasangan is [string, string] => Boolean(pasangan[0]))
    .map(([url, judul]) => ({ url, alt_text: `Bagan ${judul}`, caption: judul }))
}

/** Kartu bagan — gambar utuh yang dapat diklik untuk diperbesar. */
function KartuBagan({
  judul,
  gambar,
  onLihat,
}: {
  judul: string
  gambar: string
  onLihat: () => void
}) {
  return (
    <Kartu className="overflow-hidden border-t-4 border-t-gold shadow-md">
      <h3 className="font-heading border-b border-navy/10 px-6 py-4 text-center text-lg font-bold text-navy">
        {judul}
      </h3>

      <button
        type="button"
        onClick={onLihat}
        aria-label={`Perbesar bagan ${judul}`}
        className="group relative block w-full cursor-zoom-in bg-white p-4"
      >
        {/* object-contain: bagan desa bermacam rasio, dan memotongnya berarti
            memotong kotak jabatan di tepi. */}
        <img
          src={gambar}
          alt={`Bagan ${judul}`}
          loading="lazy"
          className="mx-auto max-h-105 w-full object-contain"
        />

        <span
          aria-hidden="true"
          className="absolute right-6 bottom-6 grid size-9 place-items-center rounded-full bg-navy/80 text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
        >
          <Maximize2 className="size-4" />
        </span>
      </button>
    </Kartu>
  )
}

/** Kartu statistik desa bergaya navy dengan label emas. */
function StatDesa({ label, nilai }: { label: string; nilai: string | null }) {
  return (
    <div className="rounded-xl bg-navy p-4">
      <p className="text-xs font-medium tracking-wide text-gold uppercase">{label}</p>
      <p className="font-heading mt-1 text-xl font-bold text-white">
        {nilai ?? <span className="text-base font-normal text-white/50">Belum diisi</span>}
      </p>
    </div>
  )
}

/**
 * Koordinat peta sebagai pasangan angka, atau null bila belum diisi.
 *
 * Laravel menyerialkan kolom decimal sebagai string, jadi nilai dikonversi
 * dengan hati-hati — string kosong maupun non-angka dianggap "belum diisi".
 */
function koordinat(
  lat: string | number | null,
  lng: string | number | null,
): [number, number] | null {
  if (lat == null || lng == null) return null

  const y = Number(lat)
  const x = Number(lng)

  return Number.isFinite(y) && Number.isFinite(x) ? [y, x] : null
}

/** Luas dalam km² dari m² (atau hektar sebagai cadangan), format Indonesia. */
function luasDalamKm2(m2: number | null, hektar: number | null): string | null {
  const km2 = m2 != null ? m2 / 1_000_000 : hektar != null ? hektar / 100 : null

  return km2 == null ? null : km2.toLocaleString('id-ID', { maximumFractionDigits: 2 })
}

Profil.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
