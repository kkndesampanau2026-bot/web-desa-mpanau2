import { useId, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import type { LucideIcon } from 'lucide-react'
import { Kartu } from '@/Components/ui'

export interface EntriSamping {
  kunci: string | number
  ke: string
  judul: string
  gambar: string | null
  /** Baris keterangan kecil di bawah judul. Baris bernilai kosong dilewati. */
  keterangan?: { ikon: LucideIcon; teks: ReactNode }[]
}

/**
 * Kerangka halaman detail: isi dalam kartu lebar + sidebar berisi isi lain.
 *
 * Dipakai detail berita, potensi, wisata, dan produk UMKM — atas permintaan
 * pemilik produk keempatnya berbagi satu bentuk (docs/DEVIASI.md §A6).
 * Diletakkan di dalam `IsiHalaman lebar="lebar"`.
 *
 * Dua kolom mulai `lg`. `minmax(0,1fr)` — bukan `1fr` — supaya tabel atau
 * gambar lebar di dalam isi tidak mendorong kolom utama melampaui kisinya.
 * Di bawah `lg`, sidebar turun ke bawah isi.
 *
 * Sidebar tidak dibuat lengket: header situs satu-satunya elemen sticky
 * (CLAUDE.md prinsip 6), dan sidebar setinggi enam entri akan tertimbun di
 * layar pendek.
 */
export function DetailDenganSidebar({
  children,
  judulSamping,
  entri,
  ikonCadangan: IkonCadangan,
  pesanKosong,
}: {
  children: ReactNode
  judulSamping: string
  entri: EntriSamping[]
  /** Ikon pengganti gambar untuk entri yang tidak punya foto. */
  ikonCadangan: LucideIcon
  pesanKosong: string
}) {
  const idJudul = useId()

  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_24rem] xl:gap-8">
      <Kartu className="p-5 sm:p-8 lg:p-10">{children}</Kartu>

      <Kartu className="p-5 sm:p-6">
        <aside aria-labelledby={idJudul}>
          <h2
            id={idJudul}
            className="font-heading border-l-[3px] border-gold pl-3 text-lg font-bold text-navy"
          >
            {judulSamping}
          </h2>

          {entri.length === 0 ? (
            <p className="mt-4 text-sm text-slate-600">{pesanKosong}</p>
          ) : (
            <ul className="mt-5 space-y-5">
              {entri.map((item) => (
                <li key={item.kunci}>
                  <Link href={item.ke} className="group flex gap-4">
                    {item.gambar ? (
                      <img
                        src={item.gambar}
                        alt=""
                        loading="lazy"
                        className="aspect-square w-20 shrink-0 rounded-lg object-cover sm:w-22"
                      />
                    ) : (
                      <div
                        aria-hidden="true"
                        className="grid aspect-square w-20 shrink-0 place-items-center rounded-lg bg-navy/5 text-navy/20 sm:w-22"
                      >
                        <IkonCadangan className="size-6" />
                      </div>
                    )}

                    <div className="min-w-0">
                      <h3 className="line-clamp-2 text-sm leading-snug font-semibold text-navy transition group-hover:text-gold-dark">
                        {item.judul}
                      </h3>

                      {item.keterangan
                        ?.filter((baris) => baris.teks !== null && baris.teks !== undefined && baris.teks !== '')
                        .map(({ ikon: Ikon, teks }, i) => (
                          <p
                            key={i}
                            className="mt-1 flex items-center gap-1.5 text-xs text-slate-500 first-of-type:mt-1.5"
                          >
                            <Ikon className="size-3.5 shrink-0" aria-hidden="true" />
                            <span className="truncate">{teks}</span>
                          </p>
                        ))}
                    </div>
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </aside>
      </Kartu>
    </div>
  )
}
