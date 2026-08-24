import { useEffect, useState, type ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { MapContainer, Marker, TileLayer } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { BookOpen, Eye, MapPin, Quote, Target, Users, X } from 'lucide-react'
import { KontenKaya } from '@/Components/KontenKaya'
import { EmptyState } from '@/Components/EmptyState'
import { IsiHalaman, Kartu } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatAngka } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import type { BpdMember, Official, Profil as ProfilDesa } from '@/types/api'

const DESKRIPSI =
  'Sambutan Kepala Desa, sejarah, visi-misi, struktur organisasi Pemerintah Desa dan BPD, data geografis, serta peta lokasi kantor desa.'

type OrangPerangkat = Official | BpdMember

/**
 * Profil Desa — PRD 6.1.
 *
 * Tata letak mengikuti desain Figma (node 33:250): kepala halaman terpusat di
 * atas latar krem, kartu Visi & Misi bergaris emas, bagan struktur organisasi
 * berupa kotak yang dapat diklik, kartu Sejarah, lalu peta lokasi berdampingan
 * dengan ringkasan data geografis & batas wilayah.
 *
 * Data tiba bersama halaman, jadi satu-satunya keadaan yang tersisa adalah
 * kemungkinan admin desa belum mengisinya — ditangani lewat empty-state.
 */
export default function Profil({
  profil,
  aparat,
  bpd,
}: {
  profil: ProfilDesa | null
  aparat: Official[]
  bpd: BpdMember[]
}) {
  const { props } = useHalaman()
  const namaDesa = props.pengaturan?.nama_desa ?? 'Desa Mpanau'
  const [detail, setDetail] = useState<OrangPerangkat | null>(null)

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

          {/* Bagan Struktur Organisasi Desa — kotak yang dapat diklik. */}
          {aparat.length > 0 && (
            <section>
              <div className="text-center">
                <h2 className="font-heading text-2xl font-bold text-navy">
                  Bagan Struktur Organisasi Desa
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                  Klik salah satu kotak untuk melihat foto dan detail perangkat desa
                </p>
              </div>
              {/* Tiap tingkat menjadi satu baris; baris menumpuk membentuk
                  pohon dari Kepala Desa di atas ke perangkat di bawahnya. */}
              <div className="mt-6 flex flex-col items-center gap-4">
                {kelompokTingkat(aparat).map((baris, i) => (
                  <div key={i} className="flex flex-wrap justify-center gap-4">
                    {baris.map((o) => (
                      <KotakPerangkat key={o.id} orang={o} onClick={() => setDetail(o)} />
                    ))}
                  </div>
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

          {/* BPD — lembaga terpisah dari aparat desa (PRD 3.2), memakai bahasa
              visual yang sama meski tidak digambarkan pada Figma. */}
          {bpd.length > 0 && (
            <section>
              <div className="text-center">
                <h2 className="font-heading text-2xl font-bold text-navy">
                  Badan Permusyawaratan Desa
                </h2>
                <p className="mt-1 text-sm text-slate-500">
                  Lembaga permusyawaratan desa, mitra kerja Kepala Desa.
                </p>
              </div>
              <div className="mt-6 flex flex-wrap justify-center gap-4">
                {bpd.map((o) => (
                  <KotakPerangkat key={o.id} orang={o} onClick={() => setDetail(o)} />
                ))}
              </div>
            </section>
          )}
        </div>
      </IsiHalaman>

      <DetailPerangkat orang={detail} onTutup={() => setDetail(null)} />
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
 * Mengelompokkan aparat menjadi baris-baris menurut `tingkat`, terurut naik.
 *
 * Data sudah datang terurut dari server (tingkat lalu urutan_tampil), jadi di
 * sini cukup dikumpulkan per tingkat sambil mempertahankan urutannya.
 */
function kelompokTingkat(aparat: Official[]): Official[][] {
  const peta = new Map<number, Official[]>()

  for (const orang of aparat) {
    const baris = peta.get(orang.tingkat) ?? []
    baris.push(orang)
    peta.set(orang.tingkat, baris)
  }

  return [...peta.entries()].sort(([a], [b]) => a - b).map(([, baris]) => baris)
}

/** Kotak nama perangkat pada bagan struktur — jabatan di atas, nama di bawah. */
function KotakPerangkat({ orang, onClick }: { orang: OrangPerangkat; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="w-40 rounded-xl border-2 border-navy/20 bg-white px-[18px] py-3.5 text-center shadow-sm transition hover:border-gold hover:shadow-md"
    >
      <span className="font-heading block text-sm font-bold text-navy">{orang.jabatan}</span>
      <span className="mt-0.5 block text-xs text-slate-500">{orang.nama}</span>
    </button>
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
 * Dialog detail perangkat desa — foto dan keterangan, dibuka dari bagan.
 *
 * Mengikuti pola Lightbox: Escape menutup, klik latar menutup, dan konten
 * menghentikan propagasi klik agar tidak ikut menutup.
 */
function DetailPerangkat({ orang, onTutup }: { orang: OrangPerangkat | null; onTutup: () => void }) {
  useEffect(() => {
    if (!orang) return

    function tanganiTombol(e: KeyboardEvent) {
      if (e.key === 'Escape') onTutup()
    }

    window.addEventListener('keydown', tanganiTombol)

    return () => window.removeEventListener('keydown', tanganiTombol)
  }, [orang, onTutup])

  if (!orang) return null

  const rentang = rentangPeriode(orang.periode_mulai, orang.periode_selesai)

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Detail ${orang.nama}`}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      onClick={onTutup}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="relative w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-xl"
      >
        <button
          onClick={onTutup}
          aria-label="Tutup"
          className="absolute top-3 right-3 rounded-full p-1.5 text-slate-500 transition hover:bg-navy/5 hover:text-navy"
        >
          <X className="size-5" />
        </button>

        {orang.foto ? (
          <img
            src={orang.foto}
            alt={`Foto ${orang.nama}`}
            className="mx-auto size-32 rounded-full border-4 border-gold object-cover"
          />
        ) : (
          <span
            aria-hidden="true"
            className="font-heading mx-auto grid size-32 place-items-center rounded-full border-4 border-gold/40 bg-navy/5 text-3xl font-bold text-navy/40"
          >
            {orang.nama.charAt(0)}
          </span>
        )}

        <p className="font-heading mt-4 text-xl font-bold text-navy">{orang.nama}</p>
        <p className="mt-1 text-slate-600">{orang.jabatan}</p>
        {rentang && <p className="mt-2 text-sm text-slate-500">Periode {rentang}</p>}
      </div>
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

/** "2019–2025" / "2019–sekarang" dari tanggal atau tahun mentah. */
function rentangPeriode(mulai: string | null, selesai: string | null): string | null {
  if (!mulai) return null

  return `${tahun(mulai)}–${selesai ? tahun(selesai) : 'sekarang'}`
}

function tahun(nilai: string): string {
  const tanggal = new Date(nilai)
  return Number.isNaN(tanggal.getTime()) ? nilai : String(tanggal.getFullYear())
}

Profil.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
