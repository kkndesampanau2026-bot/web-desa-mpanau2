import { useHalaman } from '@/types/inertia'

/** Perubahan penyaring: nilai kosong berarti parameternya dibuang. */
export type Jelajah = Record<string, string | number | null | undefined>

/** Query string yang sedang aktif pada alamat halaman. */
function queryAktif(url: string): URLSearchParams {
  const tanya = url.indexOf('?')

  return new URLSearchParams(tanya === -1 ? '' : url.slice(tanya + 1))
}

function bentuk(params: URLSearchParams, ubah: Jelajah = {}, slug?: string): string {
  for (const [kunci, nilai] of Object.entries(ubah)) {
    if (nilai === undefined || nilai === null || nilai === '') {
      params.delete(kunci)
    } else {
      params.set(kunci, String(nilai))
    }
  }

  const dasar = slug ? `/potensi/${slug}` : '/potensi'
  const query = params.toString()

  return query ? `${dasar}?${query}` : dasar
}

/**
 * Penyusun seluruh alamat di bawah /potensi.
 *
 * Keadaan penjelajahan dibaca langsung dari alamat halaman yang sedang
 * terbuka, bukan disusun ulang dari prop lalu dioper turun-temurun ke tiap
 * komponen anak. Susunan yang dioper itu sempat dipakai, dan begitu satu
 * komponen tidak menerimanya — cukup satu pemasangan modul yang tertinggal
 * saat pengembangan — kategori senyap hilang dari tautan: kartu wisata
 * menunjuk /potensi/<slug> tanpa kategori dan berakhir 404, sementara katalog
 * memulangkan pengunjung ke kategori "Semua". Dengan dibaca dari alamat,
 * tidak ada lagi yang bisa tertinggal.
 */
export function useJelajahPotensi() {
  const { url } = useHalaman()

  return {
    /** Alamat daftar dengan sebagian penyaringnya diubah. */
    daftar: (ubah: Jelajah = {}) => bentuk(queryAktif(url), ubah),

    /**
     * Alamat detail satu isi, membawa serta seluruh keadaan daftar saat ini.
     *
     * Query string keduanya wajib sama persis: tombol "kembali" di halaman
     * detail disusun server dengan memasang ulang query string yang ia terima
     * di depan /potensi.
     */
    detail: (slug: string) => bentuk(queryAktif(url), {}, slug),

    /**
     * Alamat kategori lain, mulai dari nol. Penyaring dan nomor halaman
     * sengaja tidak ikut: keduanya milik kategori yang baru ditinggalkan.
     */
    pindahKategori: (pilihan?: string) => bentuk(new URLSearchParams(), { kategori: pilihan }),
  }
}
