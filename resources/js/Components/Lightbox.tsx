import { useEffect } from 'react'

export interface FotoLightbox {
  url: string
  alt_text: string
  caption?: string | null
}

/**
 * Penampil foto layar penuh — PRD 6.13.
 *
 * Diangkat menjadi komponen bersama karena dipakai galeri album maupun detail
 * wisata; sebelumnya kode yang sama ditulis dua kali dan hanya salah satunya
 * yang menangani tombol Escape.
 */
export function Lightbox({
  foto,
  indeks,
  onTutup,
}: {
  foto: FotoLightbox[]
  indeks: number | null
  onTutup: () => void
}) {
  // Escape menutup dialog. Tanpa ini, pengguna keyboard terjebak di dalam
  // lapisan gelap dan hanya bisa keluar dengan menyegarkan halaman.
  useEffect(() => {
    if (indeks === null) return

    function tanganiTombol(e: KeyboardEvent) {
      if (e.key === 'Escape') onTutup()
    }

    window.addEventListener('keydown', tanganiTombol)

    return () => window.removeEventListener('keydown', tanganiTombol)
  }, [indeks, onTutup])

  if (indeks === null || !foto[indeks]) return null

  const aktif = foto[indeks]

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={aktif.alt_text}
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
      onClick={onTutup}
    >
      <button
        onClick={onTutup}
        aria-label="Tutup"
        className="absolute top-4 right-4 rounded-full bg-white/10 px-3 py-1 text-white"
      >
        ✕
      </button>

      <figure onClick={(e) => e.stopPropagation()} className="max-h-full">
        <img
          src={aktif.url}
          alt={aktif.alt_text}
          className="max-h-[80vh] rounded-lg object-contain"
        />
        {aktif.caption && (
          <figcaption className="mt-3 text-center text-sm text-white/80">
            {aktif.caption}
          </figcaption>
        )}
      </figure>
    </div>
  )
}
