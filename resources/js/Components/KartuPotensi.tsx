import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { ArrowRight } from 'lucide-react'
import { useJelajahPotensi } from '@/lib/tautan'
import type { PotensiItem } from '@/types/api'

/** Kartu satu potensi desa pada daftar /potensi, menautkan ke detailnya. */
export function KartuPotensi({ potensi }: { potensi: PotensiItem }) {
  const jelajah = useJelajahPotensi()

  return (
    <li className="group overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-lg">
      <Link href={jelajah.detail(potensi.slug)} className="flex h-full flex-col">
        {potensi.foto ? (
          <img
            src={potensi.foto}
            alt=""
            loading="lazy"
            className="h-44 w-full object-cover transition group-hover:scale-105"
          />
        ) : (
          <div aria-hidden="true" className="h-44 w-full bg-navy/5" />
        )}

        <div className="flex flex-1 flex-col p-5">
          <span className="text-xs font-semibold tracking-wide text-gold-dark uppercase">
            {potensi.kategori}
          </span>

          <h3 className="font-heading mt-1 font-bold text-navy">{potensi.judul}</h3>

          {potensi.deskripsi && (
            <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-600">
              {potensi.deskripsi}
            </p>
          )}

          <span className="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-navy">
            Selengkapnya
            <ArrowRight aria-hidden="true" className="size-4 transition group-hover:translate-x-1" />
          </span>
        </div>
      </Link>
    </li>
  )
}

/** Susunan kisi baku untuk sekumpulan KartuPotensi. */
export function KisiPotensi({ children }: { children: ReactNode }) {
  return <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">{children}</ul>
}
