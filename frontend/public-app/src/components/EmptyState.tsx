import { FileQuestion } from 'lucide-react'
import { KepalaHalaman, IsiHalaman, Kartu } from './ui'

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
      <KepalaHalaman eyebrow={eyebrow} judul={judul} deskripsi={deskripsi} />

      <IsiHalaman>
        <Kartu className="flex flex-col items-center gap-4 px-6 py-16 text-center">
          <span
            aria-hidden="true"
            className="grid size-14 place-items-center rounded-full bg-navy/5 text-navy/40"
          >
            <FileQuestion className="size-7" />
          </span>

          <div>
            <p className="font-heading text-lg font-bold text-navy">{pesan}</p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-slate-500">
              Data untuk bagian ini belum dipublikasikan oleh admin desa.
            </p>
          </div>
        </Kartu>
      </IsiHalaman>
    </>
  )
}
