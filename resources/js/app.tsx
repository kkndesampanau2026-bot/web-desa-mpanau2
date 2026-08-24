import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

/**
 * Titik masuk tunggal seluruh aplikasi — situs publik dan dashboard CMS.
 *
 * Sebelumnya ada dua bundel React yang berdiri sendiri, masing-masing dengan
 * router sisi klien, klien HTTP, dan cache datanya sendiri. Kini Laravel yang
 * memutuskan halaman mana yang tampil: setiap respons menyebut nama komponen
 * (mis. "Publik/Beranda") beserta datanya, dan berkas ini yang memuat komponen
 * itu. Tidak ada lagi tabel rute yang harus dijaga tetap sinkron di dua tempat.
 */

const NAMA_SITUS = import.meta.env.VITE_APP_NAME ?? 'Website Profil Desa Digital'

/**
 * SEMENTARA — hanya melayani layar CMS.
 *
 * Halaman publik sudah menerima datanya sebagai prop Inertia dan tidak
 * menyentuh React Query sama sekali. Layar CMS masih memanggil `/api/v1/admin/*`
 * lewat XHR, sehingga provider ini tetap dipasang sampai modul terakhir
 * berpindah — lihat Fase 4–5 pada `docs/MIGRASI-MONOLIT.md`.
 */
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      // Data CMS jarang berubah di balik layar saat operator sedang bekerja;
      // refetch tiap kali tab difokuskan hanya menambah beban tanpa manfaat.
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
})

void createInertiaApp({
  title: (judul) => (judul ? `${judul} — ${NAMA_SITUS}` : NAMA_SITUS),

  /*
   * Halaman dimuat malas (tanpa `eager`), sehingga pengunjung yang hanya
   * membaca berita tidak ikut mengunduh Recharts atau Leaflet milik halaman
   * infografis dan peta — target waktu muat PRD 12.1 pada koneksi 4G.
   */
  resolve: (nama) =>
    resolvePageComponent(
      `./Pages/${nama}.tsx`,
      import.meta.glob('./Pages/**/*.tsx'),
    ),

  setup({ el, App, props }) {
    createRoot(el).render(
      <StrictMode>
        <QueryClientProvider client={queryClient}>
          <App {...props} />
        </QueryClientProvider>
      </StrictMode>,
    )
  },

  // Emas, mengikuti warna aksen situs.
  progress: { color: '#c9a227' },
})
