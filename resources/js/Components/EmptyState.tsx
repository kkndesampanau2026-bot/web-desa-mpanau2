import { FileQuestion } from 'lucide-react'
import { KepalaHalaman, IsiHalaman } from './ui'

/**
 * Pola empty-state informatif — PRD 3.2.
 *
 * Ketika admin desa belum mengisi data, halaman tetap menampilkan judul modul
 * dan penjelasan fungsinya, bukan halaman kosong atau error. Pola ini dipakai
 * konsisten di seluruh modul publik supaya situs tetap terasa profesional
 * pada masa awal pengisian konten.
 */
export function EmptyState({
  judul,
  deskripsi,
  pesan = 'Belum Ada Data',
  eyebrow,
}: {
  judul: string
  deskripsi: string
  pesan?: string
  eyebrow?: string
}) {
  return (
    <>
      <KepalaHalaman eyebrow={eyebrow} judul={judul} deskripsi={deskripsi} lebar="sedang" />

      <IsiHalaman>
        {/*
          Kotak bergaris putus-putus, bukan kartu penuh berisi lingkaran ikon
          besar: keadaan kosong sebaiknya terbaca sebagai tempat yang MENUNGGU
          diisi, bukan sebagai kartu yang isinya memang begitu.
        */}
        <div className="rounded-xl border border-dashed border-navy/25 bg-white px-6 py-12 text-center sm:py-16">
          <FileQuestion className="mx-auto size-7 text-navy/30" aria-hidden="true" />
          <p className="font-heading mt-4 text-lg font-bold text-navy">{pesan}</p>
          <p className="mx-auto mt-1.5 max-w-sm text-sm leading-relaxed text-slate-500">
            Data untuk bagian ini belum dipublikasikan oleh admin desa.
          </p>
        </div>
      </IsiHalaman>
    </>
  )
}
