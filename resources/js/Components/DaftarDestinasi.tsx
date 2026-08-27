import { Link } from '@inertiajs/react'
import { useJelajahPotensi } from '@/lib/tautan'
import type { WisataRingkas } from '@/types/api'

/**
 * Kisi destinasi wisata desa — PRD 6.11.
 *
 * Tampil sebagai kategori "Pariwisata" di /potensi, dan detail tiap destinasi
 * ikut tinggal di bawah alamat yang sama supaya tombol "kembali" di sana
 * memulangkan pengunjung ke kategori ini, bukan ke pangkal daftar potensi.
 */
export function DaftarDestinasi({ wisata }: { wisata: WisataRingkas[] }) {
  const jelajah = useJelajahPotensi()

  return (
    <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {wisata.map((w) => (
        <li
          key={w.id}
          className="group overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-lg"
        >
          <Link href={jelajah.detail(w.slug)} className="flex h-full flex-col">
            {w.foto_utama ? (
              <img
                src={w.foto_utama}
                alt=""
                loading="lazy"
                className="h-44 w-full object-cover transition group-hover:scale-105"
              />
            ) : (
              <div aria-hidden="true" className="h-44 w-full bg-navy/5" />
            )}

            <div className="flex flex-1 flex-col p-5">
              <h3 className="font-heading font-bold text-navy">{w.nama}</h3>
              {w.deskripsi && (
                <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-slate-600">
                  {w.deskripsi}
                </p>
              )}
              {w.harga_tiket && (
                <p className="mt-auto pt-4 text-sm font-semibold text-navy">
                  Tiket: {w.harga_tiket}
                </p>
              )}
            </div>
          </Link>
        </li>
      ))}
    </ul>
  )
}
