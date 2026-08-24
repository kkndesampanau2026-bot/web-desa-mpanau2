import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Baby, Gauge, HandCoins, Target, Users, Wallet, type LucideIcon } from 'lucide-react'
import { LayoutPublik } from './LayoutPublik'
import { useHalaman } from '@/types/inertia'

/**
 * Kerangka modul Infografis — PRD 3.2.
 *
 * Mengikuti desain Figma: kepala halaman terpusat "Infografis {Desa}" diikuti
 * enam "pil" pemilih dimensi data. Pil menggantikan sub-navigasi lama; masing-
 * masing sub-halaman cukup merender kartu angka dan grafiknya sendiri.
 */
const TAB: { ke: string; label: string; ikon: LucideIcon }[] = [
  { ke: '/infografis/penduduk', label: 'Penduduk', ikon: Users },
  { ke: '/infografis/apbdes', label: 'APB Desa', ikon: Wallet },
  { ke: '/infografis/stunting', label: 'Stunting', ikon: Baby },
  { ke: '/infografis/bansos', label: 'Bansos', ikon: HandCoins },
  { ke: '/infografis/idm', label: 'IDM', ikon: Gauge },
  { ke: '/infografis/sdgs', label: 'SDGs', ikon: Target },
]

export function LayoutInfografis({ children }: { children: ReactNode }) {
  const { props, url } = useHalaman()
  const jalur = url.split('?')[0]
  const namaDesa = props.pengaturan?.nama_desa ?? 'Desa Mpanau'

  return (
    <div>
      <div className="mx-auto max-w-6xl px-6 pt-14">
        <header className="text-center">
          <p className="text-sm font-semibold tracking-[0.14em] text-gold-dark uppercase">
            Data Terbuka
          </p>
          <h1 className="font-heading mt-2 text-3xl font-bold text-navy sm:text-4xl">
            Infografis {namaDesa}
          </h1>
        </header>

        <nav aria-label="Kategori infografis" className="mt-8 flex flex-wrap justify-center gap-3">
          {TAB.map((tab) => {
            const aktif = jalur === tab.ke

            return (
              <Link
                key={tab.ke}
                href={tab.ke}
                aria-current={aktif ? 'page' : undefined}
                className={`inline-flex items-center gap-2 rounded-full border-2 px-5 py-2.5 text-sm font-semibold transition ${
                  aktif
                    ? 'border-navy bg-navy text-white'
                    : 'border-navy/20 bg-white text-navy hover:border-navy/40'
                }`}
              >
                <tab.ikon className="size-4 shrink-0" aria-hidden="true" />
                {tab.label}
              </Link>
            )
          })}
        </nav>
      </div>

      {children}
    </div>
  )
}

/**
 * Dipakai setiap sub-halaman infografis sebagai `Halaman.layout`.
 *
 * Menyusun dua kerangka sekaligus (situs → hero+pil) dalam satu pemanggilan,
 * supaya keenam berkas halaman tidak perlu mengulang penumpukan yang sama.
 */
export function bungkusInfografis(page: ReactNode) {
  return (
    <LayoutPublik>
      <LayoutInfografis>{page}</LayoutInfografis>
    </LayoutPublik>
  )
}
