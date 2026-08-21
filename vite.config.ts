import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'

/**
 * Satu build untuk seluruh aplikasi — situs publik dan dashboard CMS.
 *
 * Sejak arsitektur dipadukan menjadi monolit Inertia, tidak ada lagi dua
 * dev server terpisah pada port 5173/5174. Vite di sini hanya melayani aset;
 * yang membuka halaman tetap Laravel pada port 8000. Karena itu tidak ada
 * `server.port` yang wajib dijaga: berkas `hot` yang ditulis
 * laravel-vite-plugin yang memberi tahu Blade ke mana harus menunjuk.
 */
export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: true,
    }),
    react(),
    tailwindcss(),
  ],
  server: {
    /*
     * Tanpa ini Vite mengikat ke `::1` dan laravel-vite-plugin menuliskan
     * alamat IPv6 literal (`http://[::1]:5173`) ke berkas `hot`. Sebagian
     * penyiapan Windows gagal menyelesaikan alamat itu, sehingga halaman
     * terbuka tanpa gaya sama sekali padahal kedua server berjalan normal.
     */
    host: 'localhost',
  },
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './resources/js'),
    },
  },
})
