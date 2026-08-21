import { lazy, Suspense, type ReactNode } from 'react'
import { Navigate, type RouteObject } from 'react-router-dom'
import { Layout } from '@/components/Layout'
import { EmptyState } from '@/components/EmptyState'
import { BerandaPage } from '@/pages/BerandaPage'
import { ProfilPage } from '@/pages/ProfilPage'
import { PemerintahPage } from '@/pages/PemerintahPage'
import { BeritaPage } from '@/pages/BeritaPage'
import { BeritaDetailPage } from '@/pages/BeritaDetailPage'
import { GaleriPage } from '@/pages/GaleriPage'
import { AlbumDetailPage } from '@/pages/AlbumDetailPage'
import { InfografisLayout } from '@/components/InfografisLayout'
const PendudukPage = lazy(() => import('@/pages/infografis/PendudukPage').then((m) => ({ default: m.PendudukPage })))
const ApbdesPage = lazy(() => import('@/pages/infografis/ApbdesPage').then((m) => ({ default: m.ApbdesPage })))
const StuntingPage = lazy(() => import('@/pages/infografis/StuntingPage').then((m) => ({ default: m.StuntingPage })))
const IdmPage = lazy(() => import('@/pages/infografis/IdmPage').then((m) => ({ default: m.IdmPage })))
const SdgsPage = lazy(() => import('@/pages/infografis/SdgsPage').then((m) => ({ default: m.SdgsPage })))
const BansosPage = lazy(() => import('@/pages/infografis/BansosPage').then((m) => ({ default: m.BansosPage })))
import {
  PpidLayout,
  PpidBerandaPage,
  DasarHukumPage,
  InformasiPpidPage,
} from '@/pages/ppid/PpidPages'
import { PermohonanPage, LacakPermohonanPage } from '@/pages/ppid/PermohonanPage'
import { PotensiPage } from '@/pages/ekonomi/PotensiPage'
import { WisataPage, WisataDetailPage } from '@/pages/ekonomi/WisataPage'
import { BelanjaPage, ProdukDetailPage } from '@/pages/ekonomi/BelanjaPage'
import { PengaduanPage, LacakPengaduanPage } from '@/pages/pengaduan/PengaduanPage'
const ListingPage = lazy(() => import('@/pages/peta/ListingPage').then((m) => ({ default: m.ListingPage })))

/**
 * Sitemap Public App — PRD Bagian 11.
 *
 * Modul yang sudah selesai memakai halaman sungguhan; sisanya masih dirender
 * dengan pola empty-state (PRD 3.2) sampai fasenya tiba. Judul & deskripsi di
 * bawah adalah teks yang benar-benar dibaca publik, jadi ditulis informatif —
 * bukan sekadar penanda "TODO".
 */

/**
 * Membungkus halaman yang dimuat malas.
 *
 * Halaman infografis menarik Recharts (~400 kB) yang tidak dibutuhkan
 * pengunjung yang hanya membaca berita — memuatnya di muka akan mengorbankan
 * target waktu muat PRD 12.1 pada koneksi 4G.
 */
function Malas({ anak }: { anak: ReactNode }) {
  return (
    <Suspense
      fallback={
        <div className="mx-auto max-w-4xl px-6 py-16 text-slate-500">
          Memuat grafik…
        </div>
      }
    >
      {anak}
    </Suspense>
  )
}

export const routes: RouteObject[] = [
  {
    path: '/',
    element: <Layout />,
    children: [
      { index: true, element: <BerandaPage /> },

      // --- Fase 2: Konten Inti ---
      { path: 'profil', element: <ProfilPage /> },
      { path: 'pemerintah', element: <PemerintahPage /> },
      { path: 'berita', element: <BeritaPage /> },
      { path: 'berita/:slug', element: <BeritaDetailPage /> },
      { path: 'galeri', element: <GaleriPage /> },
      { path: 'galeri/:slug', element: <AlbumDetailPage /> },

      // --- Fase 3: Infografis (satu halaman induk, 6 sub-tab — PRD 3.2) ---
      {
        path: 'infografis',
        element: <InfografisLayout />,
        children: [
          // Hub mengarahkan ke sub-tab pertama — PRD Bagian 11.
          { index: true, element: <Navigate to="/infografis/penduduk" replace /> },
          { path: 'penduduk', element: <Malas anak={<PendudukPage />} /> },
          { path: 'apbdes', element: <Malas anak={<ApbdesPage />} /> },
          { path: 'stunting', element: <Malas anak={<StuntingPage />} /> },
          { path: 'idm', element: <Malas anak={<IdmPage />} /> },
          { path: 'sdgs', element: <Malas anak={<SdgsPage />} /> },
          { path: 'bansos', element: <Malas anak={<BansosPage />} /> },
        ],
      },

      // --- Fase 5: Potensi, Wisata & UMKM ---
      { path: 'potensi', element: <PotensiPage /> },
      { path: 'wisata', element: <WisataPage /> },
      { path: 'wisata/:slug', element: <WisataDetailPage /> },
      { path: 'belanja', element: <BelanjaPage /> },
      { path: 'belanja/:slug', element: <ProdukDetailPage /> },

      // --- Fase 4: PPID (satu halaman induk + sub-tab) ---
      {
        path: 'ppid',
        element: <PpidLayout />,
        children: [
          { index: true, element: <PpidBerandaPage /> },
          { path: 'dasar-hukum', element: <DasarHukumPage /> },
          { path: 'berkala', element: <InformasiPpidPage jenis="berkala" /> },
          { path: 'serta-merta', element: <InformasiPpidPage jenis="serta-merta" /> },
          { path: 'setiap-saat', element: <InformasiPpidPage jenis="setiap-saat" /> },
          { path: 'permintaan', element: <PermohonanPage /> },
          // Didaftarkan sebagai anak dari 'permintaan' agar URL pelacakan
          // tetap /ppid/permintaan/lacak sesuai sitemap PRD Bagian 11.
          { path: 'permintaan/lacak', element: <LacakPermohonanPage /> },
        ],
      },

      // --- Fase 6: Peta & Pengaduan ---
      { path: 'listing', element: <Malas anak={<ListingPage />} /> },
      { path: 'pengaduan', element: <PengaduanPage /> },
      { path: 'pengaduan/lacak', element: <LacakPengaduanPage /> },

      {
        path: '*',
        element: (
          <EmptyState
            judul="Halaman Tidak Ditemukan"
            deskripsi="Alamat yang Anda tuju tidak tersedia pada situs ini."
            pesan="404"
          />
        ),
      },
    ],
  },
]
