/**
 * Pemeriksa MySQL sebelum `npm run dev`.
 *
 * MySQL Laragon TIDAK terdaftar sebagai layanan Windows, sehingga ia tidak
 * hidup otomatis saat komputer menyala dan bisa mati tanpa pemberitahuan.
 * Gejalanya membingungkan: server menyala normal, halaman terbuka, tetapi
 * setiap data gagal dimuat.
 *
 * Skrip ini mengubah kebingungan itu menjadi satu pesan yang jelas.
 */
import net from 'node:net'

const HOST = '127.0.0.1'
const PORT = 3306

const socket = new net.Socket()
socket.setTimeout(2000)

socket.on('connect', () => {
  socket.destroy()
  process.exit(0)
})

const gagal = () => {
  socket.destroy()
  console.error('\n  MySQL tidak berjalan di ' + HOST + ':' + PORT + '\n')
  console.error('  Situs akan terbuka, tetapi SEMUA data gagal dimuat.\n')
  console.error('  Cara menyalakan:')
  console.error('    1. Buka Laragon lalu klik "Start All", ATAU')
  console.error('    2. Jalankan di PowerShell:')
  console.error('       Start-Process "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe" ' +
                '-ArgumentList \'--defaults-file=C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini\' -WindowStyle Hidden\n')
  console.error('  Lewati pemeriksaan ini dengan: npm run dev:paksa\n')
  process.exit(1)
}

socket.on('timeout', gagal)
socket.on('error', gagal)
socket.connect(PORT, HOST)
