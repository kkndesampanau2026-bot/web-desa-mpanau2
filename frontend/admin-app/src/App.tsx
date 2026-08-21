import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from '@/features/auth/AuthProvider'
import { RouteTerproteksi } from '@/features/auth/RouteTerproteksi'
import { AdminLayout } from '@/components/AdminLayout'
import { LoginPage } from '@/pages/LoginPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { ProfilPage } from '@/pages/ProfilPage'
import { SotkPage } from '@/pages/SotkPage'
import { BeritaPage } from '@/pages/BeritaPage'
import { GaleriPage } from '@/pages/GaleriPage'
import { PengaturanPage } from '@/pages/PengaturanPage'
import { PendudukPage } from '@/pages/PendudukPage'
import { BansosAdminPage } from '@/pages/BansosAdminPage'
import { PpidAdminPage } from '@/pages/PpidAdminPage'
import { EkonomiAdminPage } from '@/pages/EkonomiAdminPage'
import { PengaduanAdminPage } from '@/pages/PengaduanAdminPage'
import { PetaAdminPage } from '@/pages/PetaAdminPage'

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

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<LoginPage />} />

            <Route element={<RouteTerproteksi />}>
              <Route element={<AdminLayout />}>
                <Route path="/dashboard" element={<DashboardPage />} />

                {/* Setiap modul dijaga permission-nya masing-masing —
                    sejalan dengan pemisahan hak akses pada PRD 12.2. */}
                <Route element={<RouteTerproteksi izinDibutuhkan="manage-village-profile" />}>
                  <Route path="/profil" element={<ProfilPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-officials" />}>
                  <Route path="/sotk-bpd" element={<SotkPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-news" />}>
                  <Route path="/berita" element={<BeritaPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-gallery" />}>
                  <Route path="/galeri" element={<GaleriPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-population-data" />}>
                  <Route path="/penduduk" element={<PendudukPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-bansos" />}>
                  <Route path="/bansos" element={<BansosAdminPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-ppid-content" />}>
                  <Route path="/ppid" element={<PpidAdminPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-potential" />}>
                  <Route path="/ekonomi" element={<EkonomiAdminPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="respond-complaint" />}>
                  <Route path="/pengaduan" element={<PengaduanAdminPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-poi" />}>
                  <Route path="/peta" element={<PetaAdminPage />} />
                </Route>

                <Route element={<RouteTerproteksi izinDibutuhkan="manage-settings" />}>
                  <Route path="/pengaturan" element={<PengaturanPage />} />
                </Route>
              </Route>
            </Route>

            <Route
              path="/tidak-berwenang"
              element={
                <div className="flex min-h-screen items-center justify-center px-4 text-center text-slate-600">
                  Anda tidak memiliki izin untuk mengakses halaman ini.
                </div>
              }
            />

            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
