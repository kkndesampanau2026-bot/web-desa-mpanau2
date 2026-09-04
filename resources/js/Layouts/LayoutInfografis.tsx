import type { ReactNode } from 'react'
import { Baby, Gauge, HandCoins, Target, Users, Wallet } from 'lucide-react'
import { LayoutPublik } from './LayoutPublik'
import { BilahTab, KepalaHalaman, type ItemTab } from '@/Components/ui'
import { useHalaman } from '@/types/inertia'

/**
 * Kerangka modul Infografis — PRD 3.2.
 *
 * Kepala halaman navy yang sama dengan seluruh halaman publik, diikuti bilah
 * tab enam dimensi data. Sebelumnya modul ini memakai judul terpusat di atas
 * latar krem berikut pil bulat — satu-satunya halaman yang begitu, sehingga
 * berpindah ke sini terasa seperti masuk ke situs lain. Judulnya kini jatuh
 * pada garis kiri yang sama dengan logo dan tab pertama.
 */
const TAB: ItemTab[] = [
  { ke: '/infografis/penduduk', label: 'Penduduk', ikon: Users },
  { ke: '/infografis/apbdes', label: 'APB Desa', ikon: Wallet },
  { ke: '/infografis/stunting', label: 'Stunting', ikon: Baby },
  { ke: '/infografis/bansos', label: 'Bansos', ikon: HandCoins },
  { ke: '/infografis/idm', label: 'IDM', ikon: Gauge },
  { ke: '/infografis/sdgs', label: 'SDGs', ikon: Target },
]

export function LayoutInfografis({ children }: { children: ReactNode }) {
  const namaDesa = useHalaman().props.pengaturan?.nama_desa ?? 'Desa Mpanau'

  return (
    <div>
      <KepalaHalaman
        eyebrow="Data Terbuka"
        judul={`Infografis ${namaDesa}`}
        deskripsi="Ringkasan data desa dalam bentuk angka dan grafik, diperbarui mengikuti pemutakhiran data oleh perangkat desa."
      />

      <BilahTab items={TAB} label="Kategori infografis" />

      {children}
    </div>
  )
}

/**
 * Dipakai setiap sub-halaman infografis sebagai `Halaman.layout`.
 *
 * Menyusun dua kerangka sekaligus (situs → kepala+tab) dalam satu pemanggilan,
 * supaya keenam berkas halaman tidak perlu mengulang penumpukan yang sama.
 */
export function bungkusInfografis(page: ReactNode) {
  return (
    <LayoutPublik>
      <LayoutInfografis>{page}</LayoutInfografis>
    </LayoutPublik>
  )
}
