import { useMemo, useRef } from 'react'

/**
 * Kelas animasi masuk untuk `<main>`, dipakai LayoutPublik & LayoutAdmin.
 *
 * Memulangkan `'animasi-halaman'` pada setiap PERPINDAHAN halaman, dan string
 * kosong pada muat pertama.
 *
 * Muat pertama sengaja dilewati. Animasinya bermula dari `opacity: 0`, dan
 * elemen yang belum terlihat tidak dihitung sebagai Largest Contentful Paint —
 * gambar hero Beranda ada di dalam `<main>`, sehingga menganimasikannya saat
 * halaman baru dibuka berarti menambahkan durasi animasi itu ke angka LCP yang
 * dibaca Google. Pada perpindahan berikutnya hal itu tidak berlaku: LCP hanya
 * diukur sekali, pada pemuatan dokumen.
 *
 * Kenapa `useMemo`, bukan sekadar membaca ref saat render: layout ini
 * dirender ulang juga oleh sebab lain — menu ponsel dibuka, dropdown lonceng
 * ditutup. Kalau kelasnya dihitung ulang setiap render, kelas itu akan
 * menempel pada `<main>` yang SUDAH terpasang, dan animasinya berjalan tanpa
 * ada perpindahan halaman: isi halaman berkedip setiap kali menu disentuh.
 * Dengan `useMemo` bergantung pada jalur saja, nilainya hanya berubah ketika
 * alamatnya benar-benar berubah.
 *
 * @param jalur Alamat TANPA query string. Penyaring dan nomor halaman
 *   memperbarui daftar di tempat, bukan berpindah halaman — menganimasikannya
 *   terasa seperti memuat ulang yang tidak perlu.
 */
export function useKelasAnimasiHalaman(jalur: string): string {
  const jalurSebelumnya = useRef<string | null>(null)

  return useMemo(() => {
    const muatPertama = jalurSebelumnya.current === null
    jalurSebelumnya.current = jalur

    return muatPertama ? '' : 'animasi-halaman'
  }, [jalur])
}
