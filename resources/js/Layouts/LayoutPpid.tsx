import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { LayoutPublik } from './LayoutPublik'
import { useHalaman } from '@/types/inertia'

/**
 * Kerangka modul PPID — PRD 6.14.
 *
 * Sub-navigasi menempel di bawah header utama saat digulir. PPID punya enam
 * sub-halaman yang saling terkait; tanpa navigasi yang selalu terlihat,
 * pengunjung harus menggulir balik ke atas tiap kali berpindah.
 */
const TAB = [
  { ke: '/ppid', label: 'Beranda', ujung: true },
  { ke: '/ppid/dasar-hukum', label: 'Dasar Hukum' },
  // Ketiga jenis informasi kini punya pemilih "pil" sendiri di dalam halaman
  // informasi (sesuai desain), jadi di sub-navigasi cukup satu pintu masuk.
  { ke: '/ppid/berkala', label: 'Informasi Publik' },
  { ke: '/ppid/permintaan', label: 'Ajukan Permohonan' },
]

export function LayoutPpid({ children }: { children: ReactNode }) {
  const jalur = useHalaman().url.split('?')[0]

  return (
    <div>
      <div className="sticky top-17 z-30 border-b border-navy/10 bg-white/95 backdrop-blur">
        <nav
          aria-label="Sub-navigasi PPID"
          className="mx-auto flex max-w-situs gap-1 overflow-x-auto px-6"
        >
          {TAB.map((tab) => {
            // "Beranda" harus cocok persis; tanpa itu ia menyala di seluruh
            // sub-halaman PPID sekaligus.
            const aktif = tab.ujung
              ? jalur === tab.ke
              : jalur === tab.ke || jalur.startsWith(`${tab.ke}/`)

            return (
              <Link
                key={tab.ke}
                href={tab.ke}
                aria-current={aktif ? 'page' : undefined}
                className={`-mb-px shrink-0 border-b-2 px-4 py-3.5 text-sm font-semibold whitespace-nowrap transition ${
                  aktif
                    ? 'border-gold text-navy'
                    : 'border-transparent text-slate-500 hover:text-navy'
                }`}
              >
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

/** Dipakai setiap sub-halaman PPID sebagai `Halaman.layout`. */
export function bungkusPpid(page: ReactNode) {
  return (
    <LayoutPublik>
      <LayoutPpid>{page}</LayoutPpid>
    </LayoutPublik>
  )
}
