import type { ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import { MapPin, Sprout, Tag } from 'lucide-react'
import { DetailDenganSidebar } from '@/Components/DetailDenganSidebar'
import { IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { useJelajahPotensi } from '@/lib/tautan'
import type { PotensiDetail as Potensi, PotensiItem } from '@/types/api'

/**
 * Detail satu potensi desa — PRD 6.11.
 *
 * Sebelumnya isi potensi berhenti di kartu ringkas pada daftar: deskripsi
 * panjang terpotong dan koordinat yang sudah diisi admin tidak pernah sampai
 * ke pengunjung. Halaman ini yang membuka seluruhnya.
 */
export default function PotensiDetail({
  potensi,
  potensi_lainnya,
  kembali = '/potensi',
}: {
  potensi: Potensi
  /** Potensi lain untuk sidebar — sekategori didahulukan, tanpa deskripsi. */
  potensi_lainnya: Pick<PotensiItem, 'id' | 'kategori' | 'judul' | 'slug' | 'foto'>[]
  /** Alamat daftar tempat pengunjung berangkat, disusun oleh controller. */
  kembali?: string
}) {
  const jelajah = useJelajahPotensi()

  return (
    <>
      <Head title={potensi.judul} />
      <KepalaHalaman
        lebar="lebar"
        kembali={{ ke: kembali, label: 'Kembali ke daftar potensi desa' }}
        eyebrow={potensi.kategori}
        judul={potensi.judul}
      />

      <IsiHalaman lebar="lebar">
        <DetailDenganSidebar
          judulSamping="Potensi Lainnya"
          ikonCadangan={Sprout}
          pesanKosong="Belum ada potensi lain."
          entri={potensi_lainnya.map((p) => ({
            kunci: p.id,
            ke: jelajah.detail(p.slug),
            judul: p.judul,
            gambar: p.foto,
            keterangan: [{ ikon: Tag, teks: p.kategori }],
          }))}
        >
          <div className="space-y-8">
            {potensi.foto && (
              <img
                src={potensi.foto}
                alt={potensi.judul}
                className="aspect-video w-full rounded-xl object-cover"
              />
            )}

            {potensi.deskripsi ? (
              <div className="space-y-4 leading-relaxed text-slate-700">
                {/* Deskripsi disimpan sebagai teks biasa; paragrafnya dipulihkan
                    di sini agar tulisan panjang tidak menjadi satu blok padat. */}
                {potensi.deskripsi.split(/\n{2,}/).map((paragraf, i) => (
                  <p key={i}>{paragraf}</p>
                ))}
              </div>
            ) : (
              <p className="text-slate-500">Deskripsi untuk potensi ini belum ditambahkan.</p>
            )}

            {potensi.koordinat && (
              <section className="rounded-xl border border-navy/10 bg-navy/3 p-5">
                <h2 className="font-heading flex items-center gap-2 font-bold text-navy">
                  <MapPin aria-hidden="true" className="size-4 text-gold-dark" />
                  Lokasi
                </h2>
                <p className="mt-2 text-sm text-slate-600 tabular-nums">
                  {potensi.koordinat.latitude}, {potensi.koordinat.longitude}
                </p>
                <a
                  href={`https://www.google.com/maps/search/?api=1&query=${potensi.koordinat.latitude},${potensi.koordinat.longitude}`}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-3 inline-block text-sm font-semibold text-navy underline-offset-4 hover:underline"
                >
                  Buka di Google Maps
                </a>
              </section>
            )}
          </div>
        </DetailDenganSidebar>
      </IsiHalaman>
    </>
  )
}

PotensiDetail.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
