import type { ReactNode } from 'react'
import { CalendarRange, MapPin, User } from 'lucide-react'
import { EmptyState } from '@/Components/EmptyState'
import { SeoMeta } from '@/Components/SeoMeta'
import { IsiHalaman, JudulSeksi, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { BpdMember, Official } from '@/types/api'

const DESKRIPSI =
  'Susunan aparat Pemerintah Desa dan Badan Permusyawaratan Desa (BPD) beserta jabatannya.'

interface Props {
  aparat: Official[]
  bpd: BpdMember[]
}

/**
 * Pemerintah Desa — PRD 6.2.
 *
 * Dua lembaga, dua bagian, satu halaman: Pemerintah Desa menjalankan
 * pemerintahan, BPD mewakili warga (PRD 3.2). Keduanya sengaja TIDAK dicampur
 * dalam satu kisi — deretan kartu tanpa batas membuat keduanya terbaca sebagai
 * satu susunan jabatan, padahal Ketua BPD bukan bawahan Kepala Desa.
 *
 * Urutan kartunya datang dari server (`Official::urut` & `BpdMember::urut`)
 * menurut jenjang jabatan, bukan dari angka yang diisi operator.
 */
export default function Pemerintah({ aparat, bpd }: Props) {
  // Benar-benar kosong berarti admin belum mengisi lembaga mana pun.
  if (aparat.length === 0 && bpd.length === 0) {
    return (
      <>
        <SeoMeta title="Pemerintah Desa" description={DESKRIPSI} />
        <EmptyState eyebrow="Struktur Organisasi" judul="Pemerintah Desa" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <>
      <SeoMeta
        title="Pemerintah Desa"
        description={DESKRIPSI}
        schema={{
          '@context': 'https://schema.org',
          '@type': 'GovernmentOrganization',
          name: 'Pemerintah Desa Mpanau',
          employee: aparat.map((a) => ({
            '@type': 'Person',
            name: a.nama,
            jobTitle: a.jabatan,
            image: a.foto ?? undefined,
          })),
        }}
      />

      <KepalaHalaman eyebrow="Struktur Organisasi" judul="Pemerintah Desa" deskripsi={DESKRIPSI} />

      <IsiHalaman lebar="lebar">
        <div className="space-y-12">
          <Seksi
            judul="Aparat Pemerintah Desa"
            keterangan="Perangkat desa yang menjalankan pemerintahan sehari-hari."
            kosong="Susunan aparat desa belum ditambahkan."
            orang={aparat.map((o) => ({
              kunci: o.id,
              nama: o.nama,
              jabatan: o.jabatan,
              foto: o.foto,
              keterangan: o.periode_mulai ? (
                <>
                  <CalendarRange className="size-3.5 shrink-0" aria-hidden="true" />
                  {formatTanggal(o.periode_mulai)}
                  {o.periode_selesai && <> – {formatTanggal(o.periode_selesai)}</>}
                </>
              ) : null,
            }))}
          />

          <Seksi
            judul="Badan Permusyawaratan Desa (BPD)"
            keterangan="Lembaga yang mewakili warga dalam menyalurkan aspirasi dan mengawasi jalannya pemerintahan desa."
            kosong="Susunan anggota BPD belum ditambahkan."
            orang={bpd.map((a) => ({
              kunci: a.id,
              nama: a.nama,
              jabatan: a.jabatan,
              foto: a.foto,
              keterangan: a.dapil ? (
                <>
                  <MapPin className="size-3.5 shrink-0" aria-hidden="true" />
                  {a.dapil}
                </>
              ) : null,
            }))}
          />
        </div>
      </IsiHalaman>
    </>
  )
}

interface Orang {
  kunci: number
  nama: string
  jabatan: string
  foto: string | null
  /** Baris kecil di bawah jabatan — masa bakti atau daerah pemilihan. */
  keterangan: ReactNode
}

/**
 * Satu lembaga beserta anggotanya.
 *
 * Bagian yang kosong tetap digambar lengkap dengan judul dan penjelasannya
 * (PRD 3.2): pengunjung yang mencari BPD perlu tahu bahwa lembaganya memang
 * ada dan datanya belum diisi — bukan mengira halaman ini tidak memuatnya.
 */
function Seksi({
  judul,
  keterangan,
  kosong,
  orang,
}: {
  judul: string
  keterangan: string
  kosong: string
  orang: Orang[]
}) {
  return (
    <section>
      <JudulSeksi keterangan={keterangan}>{judul}</JudulSeksi>

      {orang.length === 0 ? (
        <p className="rounded-xl border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
          {kosong}
        </p>
      ) : (
        <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:gap-6 2xl:grid-cols-4">
          {orang.map((o) => (
            <li key={o.kunci}>
              <Kartu interaktif className="h-full p-6 text-center">
                {o.foto ? (
                  <img
                    src={o.foto}
                    alt=""
                    loading="lazy"
                    className="mx-auto size-24 rounded-full border-4 border-gold object-cover"
                  />
                ) : (
                  <span
                    aria-hidden="true"
                    className="mx-auto grid size-24 place-items-center rounded-full border-4 border-gold/40 bg-navy/5 text-navy/30"
                  >
                    <User className="size-9" />
                  </span>
                )}

                <h3 className="font-heading mt-4 font-bold text-navy">{o.nama}</h3>

                <p className="mt-1 inline-block rounded-full bg-navy/5 px-3 py-1 text-xs font-semibold text-navy">
                  {o.jabatan}
                </p>

                {o.keterangan && (
                  <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                    {o.keterangan}
                  </p>
                )}
              </Kartu>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

Pemerintah.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
