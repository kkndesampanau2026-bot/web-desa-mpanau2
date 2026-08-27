import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { ArrowLeft, MapPin } from 'lucide-react'
import { IsiHalaman, KepalaHalaman, Kartu } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import type { PotensiDetail as Potensi } from '@/types/api'

/**
 * Detail satu potensi desa — PRD 6.11.
 *
 * Sebelumnya isi potensi berhenti di kartu ringkas pada daftar: deskripsi
 * panjang terpotong dan koordinat yang sudah diisi admin tidak pernah sampai
 * ke pengunjung. Halaman ini yang membuka seluruhnya.
 */
export default function PotensiDetail({
  potensi,
  kembali = '/potensi',
}: {
  potensi: Potensi
  /** Alamat daftar tempat pengunjung berangkat, disusun oleh controller. */
  kembali?: string
}) {
  return (
    <>
      <Head title={potensi.judul} />
      <KepalaHalaman eyebrow={potensi.kategori} judul={potensi.judul} />

      <IsiHalaman lebar="sempit">
        <Link
          href={kembali}
          className="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-navy"
        >
          <ArrowLeft aria-hidden="true" className="size-4" />
          Kembali ke daftar potensi desa
        </Link>

        {potensi.foto && (
          <img
            src={potensi.foto}
            alt={potensi.judul}
            className="mt-6 w-full rounded-2xl object-cover"
          />
        )}

        {potensi.deskripsi ? (
          <div className="mt-8 space-y-4 leading-relaxed text-slate-700">
            {/* Deskripsi disimpan sebagai teks biasa; paragrafnya dipulihkan
                di sini agar tulisan panjang tidak menjadi satu blok padat. */}
            {potensi.deskripsi.split(/\n{2,}/).map((paragraf, i) => (
              <p key={i}>{paragraf}</p>
            ))}
          </div>
        ) : (
          <p className="mt-8 text-slate-500">Deskripsi untuk potensi ini belum ditambahkan.</p>
        )}

        {potensi.koordinat && (
          <Kartu className="mt-8 p-5">
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
          </Kartu>
        )}
      </IsiHalaman>
    </>
  )
}

PotensiDetail.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
