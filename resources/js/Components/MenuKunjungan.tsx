import { useEffect, useRef, useState } from 'react'
import { BarChart3, X } from 'lucide-react'
import { formatAngka } from '@/lib/format'
import { useHalaman } from '@/types/inertia'
import type { StatistikKunjungan } from '@/types/api'

/**
 * Statistik kunjungan sebagai panel mengambang — PRD 6.16 & 3.2.
 *
 * Sebelumnya hanya angka TOTAL yang tampil, terselip di bilah atas header
 * setiap halaman. Enam kategori lain yang sudah dihitung server tidak pernah
 * terlihat, sementara satu angka telanjang di sebelah nama desa lebih banyak
 * menyita tinggi header daripada memberi tahu apa pun.
 *
 * Kini seluruh tujuh kategori muncul saat panelnya dibuka, dan header kembali
 * berisi identitas desa saja.
 *
 * Perilakunya sengaja dibuat kembar dengan `MenuAksesibilitas` — Escape dan
 * klik di luar sama-sama menutup — karena keduanya berdiri bersebelahan;
 * tombol berdampingan yang menutup dengan cara berbeda terasa rusak.
 *
 * Posisinya TIDAK ditetapkan di sini. `LayoutPublik` yang mendudukkan kedua
 * tombol mengambang dalam satu wadah, sehingga jaraknya tidak perlu ditebak
 * ulang lewat angka ajaib bila ukuran salah satu tombol berubah.
 */
const KATEGORI: [keyof StatistikKunjungan, string][] = [
  ['hari_ini', 'Hari Ini'],
  ['kemarin', 'Kemarin'],
  ['minggu_ini', 'Minggu Ini'],
  ['minggu_lalu', 'Minggu Lalu'],
  ['bulan_ini', 'Bulan Ini'],
  ['bulan_lalu', 'Bulan Lalu'],
]

export function MenuKunjungan() {
  const { statistik_kunjungan: data } = useHalaman().props
  const [buka, setBuka] = useState(false)
  const pembungkus = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!buka) return

    function tanganiTombol(e: KeyboardEvent) {
      if (e.key === 'Escape') setBuka(false)
    }
    function tanganiKlik(e: MouseEvent) {
      if (pembungkus.current && !pembungkus.current.contains(e.target as Node)) setBuka(false)
    }

    window.addEventListener('keydown', tanganiTombol)
    document.addEventListener('mousedown', tanganiKlik)

    return () => {
      window.removeEventListener('keydown', tanganiTombol)
      document.removeEventListener('mousedown', tanganiKlik)
    }
  }, [buka])

  // Pelengkap, bukan isi halaman: bila datanya tidak ada — halaman dashboard,
  // misalnya — tombolnya menghilang diam-diam alih-alih membuka panel kosong.
  if (!data) return null

  return (
    <div ref={pembungkus} className="relative">
      {buka && (
        <div
          role="dialog"
          aria-label="Statistik kunjungan"
          className="absolute bottom-16 left-0 w-64 rounded-2xl border border-black/5 bg-white p-4 shadow-xl"
        >
          <div className="flex items-center justify-between">
            <h2 className="font-heading text-sm font-bold text-navy">Statistik Kunjungan</h2>
            <button
              onClick={() => setBuka(false)}
              aria-label="Tutup"
              className="rounded-full p-1 text-slate-500 hover:bg-navy/5 hover:text-navy"
            >
              <X className="size-4" />
            </button>
          </div>

          {/*
            Total dipisahkan dari keenam kategori lain dan diberi bobot paling
            besar: ia satu-satunya angka yang tidak berganti makna setiap hari,
            dan itulah yang dicari orang lebih dulu.
          */}
          <div className="mt-3 rounded-xl bg-navy px-3 py-2.5 text-center">
            <p className="text-[11px] tracking-wide text-white/60 uppercase">Total Pengunjung</p>
            <p className="font-heading text-xl font-bold text-gold tabular-nums">
              {formatAngka(data.total)}
            </p>
          </div>

          <dl className="mt-3 grid grid-cols-2 gap-2">
            {KATEGORI.map(([kunci, label]) => (
              <div key={kunci} className="rounded-lg bg-cream px-2.5 py-2 text-center">
                <dt className="text-[11px] text-slate-500">{label}</dt>
                <dd className="font-heading font-bold text-navy tabular-nums">
                  {formatAngka(data[kunci])}
                </dd>
              </div>
            ))}
          </dl>
        </div>
      )}

      <button
        onClick={() => setBuka((b) => !b)}
        aria-expanded={buka}
        aria-label={`Statistik kunjungan — total ${formatAngka(data.total)} pengunjung`}
        className="grid size-14 place-items-center rounded-full border-2 border-white bg-navy text-white shadow-xl transition hover:bg-navy-light"
      >
        <BarChart3 className="size-6" aria-hidden="true" />
      </button>
    </div>
  )
}
