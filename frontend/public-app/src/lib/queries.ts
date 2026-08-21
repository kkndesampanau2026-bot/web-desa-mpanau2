import { useQuery } from '@tanstack/react-query'
import { api, getData, type ApiSuccess } from './api'
import type {
  AlbumDetail,
  AlbumRingkas,
  BeritaDetail,
  BeritaRingkas,
  BpdMember,
  KategoriBerita,
  MetaPaginasi,
  Official,
  Pengaturan,
  Profil,
  StatistikKunjungan,
  InfografisPenduduk,
  InfografisApbdes,
  InfografisStunting,
  InfografisIdm,
  InfografisSdgs,
  InfografisBansos,
  DasarHukumPpid,
  InformasiPpid,
  JenisInformasiPpid,
  DaftarPotensi,
  WisataRingkas,
  WisataDetail,
  ProdukRingkas,
  ProdukDetail,
  KategoriProduk,
  DataPeta,
  KategoriPengaduan,
} from '@/types/api'

/**
 * Hook data untuk seluruh modul publik.
 *
 * Kunci query disusun hierarkis (mis. ['berita', filter]) supaya invalidasi
 * selektif tetap mungkin dilakukan saat modul bertambah pada fase berikutnya.
 */

export function useProfil() {
  return useQuery({
    queryKey: ['profil'],
    queryFn: () => getData<Profil | null>('/profil'),
  })
}

export function useOfficials() {
  return useQuery({
    queryKey: ['officials'],
    queryFn: () => getData<Official[]>('/officials'),
  })
}

export function useBpdMembers() {
  return useQuery({
    queryKey: ['bpd-members'],
    queryFn: () => getData<BpdMember[]>('/bpd-members'),
  })
}

export interface FilterBerita {
  kategori?: string
  cari?: string
  page?: number
}

/** Daftar berita — mengembalikan data beserta metadata paginasi. */
export function useBerita(filter: FilterBerita = {}) {
  return useQuery({
    queryKey: ['berita', filter],
    queryFn: async () => {
      const response = await api.get<ApiSuccess<BeritaRingkas[]>>('/berita', {
        params: filter,
      })

      return {
        items: response.data.data,
        meta: response.data.meta as MetaPaginasi | undefined,
      }
    },
  })
}

export function useBeritaDetail(slug: string | undefined) {
  return useQuery({
    queryKey: ['berita', 'detail', slug],
    queryFn: () => getData<BeritaDetail>(`/berita/${slug}`),
    enabled: Boolean(slug),
  })
}

export function useKategoriBerita() {
  return useQuery({
    queryKey: ['berita', 'kategori'],
    queryFn: () => getData<KategoriBerita[]>('/berita/kategori'),
  })
}

export function useGaleri() {
  return useQuery({
    queryKey: ['galeri'],
    queryFn: async () => {
      const response = await api.get<ApiSuccess<AlbumRingkas[]>>('/galeri')
      return {
        items: response.data.data,
        meta: response.data.meta as MetaPaginasi | undefined,
      }
    },
  })
}

export function useAlbum(slug: string | undefined) {
  return useQuery({
    queryKey: ['galeri', slug],
    queryFn: () => getData<AlbumDetail>(`/galeri/${slug}`),
    enabled: Boolean(slug),
  })
}

export function usePengaturan() {
  return useQuery({
    queryKey: ['settings'],
    queryFn: () => getData<Pengaturan>('/settings'),
    // Pengaturan tampil di footer setiap halaman dan nyaris tak pernah
    // berubah, jadi ditahan lebih lama daripada default.
    staleTime: 30 * 60_000,
  })
}

export function useStatistikKunjungan() {
  return useQuery({
    queryKey: ['visitor-stats'],
    queryFn: () => getData<StatistikKunjungan>('/visitor-stats'),
    staleTime: 5 * 60_000,
  })
}

// --- Fase 3: Infografis ---

export function useInfografisPenduduk() {
  return useQuery({
    queryKey: ['infografis', 'penduduk'],
    queryFn: () => getData<InfografisPenduduk | null>('/infografis/penduduk'),
  })
}

export function useInfografisApbdes(tahun?: number) {
  return useQuery({
    queryKey: ['infografis', 'apbdes', tahun],
    queryFn: () => getData<InfografisApbdes | null>('/infografis/apbdes', { tahun }),
  })
}

export function useInfografisStunting() {
  return useQuery({
    queryKey: ['infografis', 'stunting'],
    queryFn: () => getData<InfografisStunting | null>('/infografis/stunting'),
  })
}

export function useInfografisIdm(tahun?: number) {
  return useQuery({
    queryKey: ['infografis', 'idm', tahun],
    queryFn: () => getData<InfografisIdm | null>('/infografis/idm', { tahun }),
  })
}

export function useInfografisSdgs(tahun?: number) {
  return useQuery({
    queryKey: ['infografis', 'sdgs', tahun],
    queryFn: () => getData<InfografisSdgs | null>('/infografis/sdgs', { tahun }),
  })
}

// --- Fase 4: Bansos & PPID ---

export function useInfografisBansos() {
  return useQuery({
    queryKey: ['infografis', 'bansos'],
    queryFn: () => getData<InfografisBansos | null>('/infografis/bansos'),
  })
}

export function useDasarHukumPpid() {
  return useQuery({
    queryKey: ['ppid', 'dasar-hukum'],
    queryFn: () => getData<DasarHukumPpid[]>('/ppid/dasar-hukum'),
  })
}

export function useInformasiPpid(jenis: JenisInformasiPpid) {
  return useQuery({
    queryKey: ['ppid', 'informasi', jenis],
    queryFn: () => getData<InformasiPpid[]>('/ppid/informasi', { jenis }),
  })
}

// --- Fase 5: Potensi, Wisata & UMKM ---

export function usePotensi(kategori?: string) {
  return useQuery({
    queryKey: ['potensi', kategori],
    queryFn: () => getData<DaftarPotensi | null>('/potensi', { kategori }),
  })
}

export function useWisata() {
  return useQuery({
    queryKey: ['wisata'],
    queryFn: () => getData<WisataRingkas[] | null>('/wisata'),
  })
}

export function useWisataDetail(slug: string | undefined) {
  return useQuery({
    queryKey: ['wisata', slug],
    queryFn: () => getData<WisataDetail>(`/wisata/${slug}`),
    enabled: Boolean(slug),
  })
}

export interface FilterProduk {
  kategori?: string
  cari?: string
  page?: number
}

export function useProduk(filter: FilterProduk = {}) {
  return useQuery({
    queryKey: ['produk', filter],
    queryFn: async () => {
      const response = await api.get<ApiSuccess<ProdukRingkas[]>>('/produk', { params: filter })
      return {
        items: response.data.data,
        meta: response.data.meta as MetaPaginasi | undefined,
      }
    },
  })
}

export function useProdukDetail(slug: string | undefined) {
  return useQuery({
    queryKey: ['produk', 'detail', slug],
    queryFn: () => getData<ProdukDetail>(`/produk/${slug}`),
    enabled: Boolean(slug),
  })
}

export function useKategoriProduk() {
  return useQuery({
    queryKey: ['produk', 'kategori'],
    queryFn: () => getData<KategoriProduk[]>('/produk/kategori'),
  })
}

// --- Fase 6: Peta & Pengaduan ---

export function usePeta(kategori?: string) {
  return useQuery({
    queryKey: ['peta', kategori],
    queryFn: () => getData<DataPeta | null>('/points-of-interest', { kategori }),
  })
}

export function useKategoriPengaduan() {
  return useQuery({
    queryKey: ['pengaduan', 'kategori'],
    queryFn: () => getData<KategoriPengaduan[]>('/pengaduan/kategori'),
    staleTime: Infinity,
  })
}
