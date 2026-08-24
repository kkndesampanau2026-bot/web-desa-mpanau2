import { useEffect, useRef, useState } from 'react'
import { Accessibility, Contrast, Minus, Plus, RotateCcw, X } from 'lucide-react'

/**
 * Menu aksesibilitas mengambang — PRD 12.4.
 *
 * Tombol bulat di kiri bawah membuka panel pengaturan sederhana yang membantu
 * warga dengan keterbatasan penglihatan: memperbesar ukuran teks dan menyalakan
 * mode kontras tinggi. Preferensi disimpan di `localStorage` sehingga bertahan
 * antar kunjungan.
 *
 * Ukuran teks diterapkan pada `font-size` elemen root: seluruh ukuran Tailwind
 * berbasis `rem`, jadi mengubah satu nilai ini menskalakan seisi halaman secara
 * proporsional tanpa merusak tata letak.
 */
const KUNCI_UKURAN = 'aksesibilitas:ukuran-teks'
const KUNCI_KONTRAS = 'aksesibilitas:kontras'
const SKALA = ['100%', '112.5%', '125%']

export function MenuAksesibilitas() {
  const [buka, setBuka] = useState(false)
  const [ukuran, setUkuran] = useState(0)
  const [kontras, setKontras] = useState(false)
  const pembungkus = useRef<HTMLDivElement>(null)

  // Muat preferensi tersimpan sekali saat pertama dipasang.
  useEffect(() => {
    const u = Number(localStorage.getItem(KUNCI_UKURAN) ?? '0')
    setUkuran(Number.isFinite(u) ? Math.min(SKALA.length - 1, Math.max(0, u)) : 0)
    setKontras(localStorage.getItem(KUNCI_KONTRAS) === '1')
  }, [])

  useEffect(() => {
    document.documentElement.style.fontSize = SKALA[ukuran]
    localStorage.setItem(KUNCI_UKURAN, String(ukuran))
  }, [ukuran])

  useEffect(() => {
    document.documentElement.classList.toggle('kontras-tinggi', kontras)
    localStorage.setItem(KUNCI_KONTRAS, kontras ? '1' : '0')
  }, [kontras])

  // Escape menutup panel, dan klik di luar panel juga menutupnya.
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

  return (
    <div ref={pembungkus} className="fixed bottom-5 left-5 z-40">
      {buka && (
        <div
          role="dialog"
          aria-label="Pengaturan aksesibilitas"
          className="absolute bottom-16 left-0 w-64 rounded-2xl border border-black/5 bg-white p-4 shadow-xl"
        >
          <div className="flex items-center justify-between">
            <h2 className="font-heading text-sm font-bold text-navy">Aksesibilitas</h2>
            <button
              onClick={() => setBuka(false)}
              aria-label="Tutup"
              className="rounded-full p-1 text-slate-500 hover:bg-navy/5 hover:text-navy"
            >
              <X className="size-4" />
            </button>
          </div>

          <div className="mt-3">
            <p className="text-xs font-semibold text-slate-600">Ukuran Teks</p>
            <div className="mt-1.5 flex items-center gap-2">
              <button
                onClick={() => setUkuran((u) => Math.max(0, u - 1))}
                disabled={ukuran === 0}
                aria-label="Perkecil teks"
                className="grid size-9 place-items-center rounded-lg border border-navy/15 text-navy transition hover:bg-navy/5 disabled:opacity-40"
              >
                <Minus className="size-4" />
              </button>
              <span className="flex-1 text-center text-sm font-semibold text-navy tabular-nums">
                {SKALA[ukuran]}
              </span>
              <button
                onClick={() => setUkuran((u) => Math.min(SKALA.length - 1, u + 1))}
                disabled={ukuran === SKALA.length - 1}
                aria-label="Perbesar teks"
                className="grid size-9 place-items-center rounded-lg border border-navy/15 text-navy transition hover:bg-navy/5 disabled:opacity-40"
              >
                <Plus className="size-4" />
              </button>
            </div>
          </div>

          <button
            onClick={() => setKontras((k) => !k)}
            aria-pressed={kontras}
            className={`mt-3 flex w-full items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold transition ${
              kontras
                ? 'border-navy bg-navy text-white'
                : 'border-navy/15 text-navy hover:bg-navy/5'
            }`}
          >
            <Contrast className="size-4" />
            Kontras Tinggi
            <span className="ml-auto text-xs font-normal opacity-80">{kontras ? 'Aktif' : 'Nonaktif'}</span>
          </button>

          {(ukuran !== 0 || kontras) && (
            <button
              onClick={() => {
                setUkuran(0)
                setKontras(false)
              }}
              className="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-slate-500 hover:bg-navy/5 hover:text-navy"
            >
              <RotateCcw className="size-3.5" />
              Kembalikan ke semula
            </button>
          )}
        </div>
      )}

      <button
        onClick={() => setBuka((b) => !b)}
        aria-expanded={buka}
        aria-label="Menu aksesibilitas"
        className="grid size-14 place-items-center rounded-full border-2 border-white bg-navy text-white shadow-xl transition hover:bg-navy-light"
      >
        <Accessibility className="size-6" />
      </button>
    </div>
  )
}
