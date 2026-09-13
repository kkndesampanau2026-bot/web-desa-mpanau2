import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { CheckCircle2, Info, TriangleAlert, X } from 'lucide-react'

/**
 * Pemberitahuan mengambang hasil sebuah tindakan.
 *
 * Menggantikan kotak hijau yang dulu muncul DI DALAM formulir setelah
 * menyimpan. Masalah kotak itu: ia bertengger di puncak formulir, sehingga
 * operator yang baru menekan "Simpan" di bagian bawah layar panjang tidak
 * melihat apa pun — tidak ada tanda berhasil maupun gagal — dan menekan
 * Simpan sekali lagi.
 *
 * Letaknya TENGAH ATAS, satu tempat untuk seluruh hasil tindakan (tambah,
 * ubah, hapus) di seluruh layar CMS. Sempat di pojok kanan bawah, lalu
 * dipindahkan atas permintaan pemilik produk: tempat yang sama setiap kali
 * membuat operator tahu ke mana harus melihat, dan sudut bawah-kanan justru
 * sisi yang paling jauh dari tombol yang barusan ditekan pada layar lebar.
 *
 * Sengaja TIDAK memakai React context: pemberitahuan dipicu dari dalam
 * callback mutation, bukan saat render, sehingga fungsi biasa sudah cukup dan
 * tidak ada halaman yang perlu dibungkus provider. `WadahToast` cukup dipasang
 * sekali di LayoutAdmin.
 *
 * Galat validasi per kolom TIDAK dikirim ke sini: tempatnya di bawah kolom
 * yang bersangkutan, bukan melayang di atas layar lalu menghilang sendiri.
 */
export type JenisToast = 'sukses' | 'galat' | 'info'

interface Toast {
  id: number
  jenis: JenisToast
  pesan: string
}

/** Toast galat bertahan lebih lama — isinya perlu dibaca, bukan sekadar dilihat. */
const DURASI: Record<JenisToast, number> = {
  sukses: 4000,
  info: 5000,
  galat: 7000,
}

let daftar: Toast[] = []
let nomorTerakhir = 0
const pendengar = new Set<(d: Toast[]) => void>()

function siarkan(): void {
  pendengar.forEach((dengar) => dengar(daftar))
}

function buang(id: number): void {
  daftar = daftar.filter((t) => t.id !== id)
  siarkan()
}

export function tampilkanToast(pesan: string, jenis: JenisToast = 'sukses'): void {
  const id = ++nomorTerakhir

  // Paling banyak tiga sekaligus: tumpukan yang lebih tinggi dari itu menutupi
  // isi halaman, dan pesan terlamalah yang paling tidak relevan.
  daftar = [...daftar, { id, jenis, pesan }].slice(-3)
  siarkan()

  window.setTimeout(() => buang(id), DURASI[jenis])
}

const GAYA: Record<JenisToast, { ikon: typeof CheckCircle2; warna: string }> = {
  sukses: { ikon: CheckCircle2, warna: 'text-emerald-600' },
  galat: { ikon: TriangleAlert, warna: 'text-red-600' },
  info: { ikon: Info, warna: 'text-teal-600' },
}

/**
 * Tempat seluruh toast digambar. Dipasang sekali saja, di LayoutAdmin.
 *
 * Wadahnya selalu ada di DOM sekalipun kosong: daerah `aria-live` yang baru
 * disisipkan bersamaan dengan isinya sering tidak jadi dibacakan pembaca
 * layar.
 */
export function WadahToast() {
  const [isi, setIsi] = useState<Toast[]>(daftar)

  useEffect(() => {
    pendengar.add(setIsi)
    return () => {
      pendengar.delete(setIsi)
    }
  }, [])

  return createPortal(
    <div
      role="status"
      aria-live="polite"
      className="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-center gap-2 sm:top-6"
    >
      {isi.map((toast) => {
        const { ikon: Ikon, warna } = GAYA[toast.jenis]

        return (
          <div
            key={toast.id}
            className="animasi-toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-lg"
          >
            <Ikon className={`mt-0.5 size-5 shrink-0 ${warna}`} aria-hidden="true" />
            <p className="min-w-0 flex-1 text-sm leading-relaxed text-slate-700">{toast.pesan}</p>
            <button
              type="button"
              onClick={() => buang(toast.id)}
              aria-label="Tutup pemberitahuan"
              className="-mt-0.5 -mr-1 shrink-0 rounded-md p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          </div>
        )
      })}
    </div>,
    document.body,
  )
}
