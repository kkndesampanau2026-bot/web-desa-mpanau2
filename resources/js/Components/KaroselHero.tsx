import { useCallback, useEffect, useRef, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'

/**
 * Latar hero beranda yang bergilir antar beberapa foto.
 *
 * Foto diunggah perangkat desa lewat CMS → Banner Beranda. Bila belum ada satu
 * pun, komponen ini tidak dipakai sama sekali — Beranda menyajikan foto
 * bawaannya sendiri lewat `<picture>` tiga ukuran.
 *
 * Beberapa keputusan yang mudah terlihat sepele tetapi menentukan:
 *
 * - Seluruh gambar dirender bertumpuk dan disilang-pudarkan lewat `opacity`,
 *   bukan digeser dengan `transform`. Hero ini adalah LATAR; menggesernya akan
 *   ikut menggeser lapisan navy dan teks di atasnya, atau menuntut lapisan
 *   terpisah yang harus dijaga tetap sepadan.
 *
 * - Hanya gambar pertama yang `fetchPriority="high"` dan dimuat seketika.
 *   Sisanya `loading="lazy"`: memuat delapan foto 1920px sekaligus berarti
 *   pengunjung ber-4G menunggu seluruhnya sebelum melihat apa pun, padahal
 *   hero inilah yang tampil pertama (PRD 12.1).
 *
 * - Putar otomatis BERHENTI saat kursor berada di atasnya, saat salah satu
 *   tombolnya menerima fokus keyboard, dan saat tab peramban tidak terlihat.
 *   Yang terakhir bukan kesempurnaan: tanpa itu, timer tetap berjalan di tab
 *   latar dan pengunjung yang kembali disambut foto yang melompat.
 *
 * - Pengunjung yang menyalakan "kurangi animasi" pada sistemnya tidak pernah
 *   mendapat pergantian otomatis (PRD 12.4). Baginya carousel ini menjadi
 *   galeri biasa yang hanya berpindah bila tombolnya ditekan.
 */
export interface GambarHero {
  url: string
  judul: string | null
}

/** Jeda antar gambar. Cukup lama untuk sempat dilihat, bukan sekadar berkedip. */
const JEDA_MS = 6000

export function KaroselHero({ gambar }: { gambar: GambarHero[] }) {
  const [aktif, setAktif] = useState(0)
  const [berhenti, setBerhenti] = useState(false)
  const wadah = useRef<HTMLDivElement>(null)

  const jumlah = gambar.length
  const banyak = jumlah > 1

  const ke = useCallback(
    (index: number) => setAktif(((index % jumlah) + jumlah) % jumlah),
    [jumlah],
  )

  // Indeks dijaga tetap sah bila operator menghapus banner sementara halaman
  // terbuka dan Inertia memperbarui propnya di tempat.
  useEffect(() => {
    setAktif((i) => (i < jumlah ? i : 0))
  }, [jumlah])

  useEffect(() => {
    if (!banyak || berhenti) return

    const kurangiGerak = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    if (kurangiGerak) return

    const timer = window.setInterval(() => setAktif((i) => (i + 1) % jumlah), JEDA_MS)

    return () => window.clearInterval(timer)
  }, [banyak, berhenti, jumlah])

  // Timer ikut berhenti saat tab disembunyikan.
  useEffect(() => {
    if (!banyak) return

    function tanganiVisibilitas() {
      setBerhenti(document.hidden)
    }

    document.addEventListener('visibilitychange', tanganiVisibilitas)

    return () => document.removeEventListener('visibilitychange', tanganiVisibilitas)
  }, [banyak])

  // Panah kiri/kanan menggerakkan carousel saat fokus keyboard berada di
  // dalamnya — pola yang diharapkan dari sekelompok tombol seperti ini.
  function tanganiTombol(e: React.KeyboardEvent) {
    if (!banyak) return
    if (e.key === 'ArrowLeft') {
      e.preventDefault()
      ke(aktif - 1)
    } else if (e.key === 'ArrowRight') {
      e.preventDefault()
      ke(aktif + 1)
    }
  }

  return (
    <>
      {/*
        DUA saudara sejajar, bukan kendali yang bersarang di dalam lapisan
        gambar.

        Lapisan gambar wajib berada di `-z-10` agar berada di belakang lapisan
        navy dan teks hero. Tetapi `position:absolute` bersama `z-index`
        negatif MEMBUAT STACKING CONTEXT: apa pun di dalamnya — betapa pun
        tinggi z-index-nya — tetap ikut terkubur di lapisan −10 itu. Kendali
        yang dulu bersarang di sana karena itu tertimbun lapisan navy dan tidak
        pernah dapat ditekan, meski tetap samar terlihat menembusnya.

        Sebagai saudara sejajar, keduanya diukur terhadap `<section>` hero yang
        sama, sehingga kendali benar-benar naik ke atas lapisan navy.
      */}
      <div
        onMouseEnter={() => setBerhenti(true)}
        onMouseLeave={() => setBerhenti(false)}
        className="absolute inset-0 -z-10"
      >
        {gambar.map((g, i) => (
          <img
            key={g.url}
            src={g.url}
            alt=""
            aria-hidden="true"
            fetchPriority={i === 0 ? 'high' : 'low'}
            loading={i === 0 ? 'eager' : 'lazy'}
            decoding="async"
            className={`absolute inset-0 size-full object-cover object-center transition-opacity duration-700 ${
              i === aktif ? 'opacity-100' : 'opacity-0'
            }`}
          />
        ))}
      </div>

      {banyak && (
        <div
          ref={wadah}
          onMouseEnter={() => setBerhenti(true)}
          onMouseLeave={() => setBerhenti(false)}
          onFocusCapture={() => setBerhenti(true)}
          onBlurCapture={(e) => {
            if (!wadah.current?.contains(e.relatedTarget as Node)) setBerhenti(false)
          }}
          onKeyDown={tanganiTombol}
          /*
            `pointer-events-none` pada wadahnya, `pointer-events-auto` pada
            tombolnya: lapisan ini menutupi seluruh hero, dan tanpa itu ia akan
            menelan setiap klik maupun sorotan teks pada judul di bawahnya.
          */
          className="pointer-events-none absolute inset-0 z-20"
        >
            <button
              type="button"
              onClick={() => ke(aktif - 1)}
              aria-label="Gambar sebelumnya"
              className="pointer-events-auto absolute top-1/2 left-3 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/25 bg-navy/50 text-white backdrop-blur-sm transition hover:bg-navy/80 focus-visible:ring-2 focus-visible:ring-gold sm:left-5"
            >
              <ChevronLeft className="size-5" aria-hidden="true" />
            </button>

            <button
              type="button"
              onClick={() => ke(aktif + 1)}
              aria-label="Gambar berikutnya"
              className="pointer-events-auto absolute top-1/2 right-3 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/25 bg-navy/50 text-white backdrop-blur-sm transition hover:bg-navy/80 focus-visible:ring-2 focus-visible:ring-gold sm:right-5"
            >
              <ChevronRight className="size-5" aria-hidden="true" />
            </button>

            {/*
              Titik penanda memakai <button>, bukan <span>: ia melompat
              langsung ke satu gambar, dan sasaran ketuknya dibuat 44px lewat
              padding meski titiknya sendiri kecil.
            */}
            <div className="pointer-events-auto absolute bottom-5 left-1/2 flex -translate-x-1/2 items-center gap-1">
              {gambar.map((g, i) => (
                <button
                  key={g.url}
                  type="button"
                  onClick={() => ke(i)}
                  aria-label={`Tampilkan gambar ke-${i + 1}${g.judul ? `: ${g.judul}` : ''}`}
                  aria-current={i === aktif}
                  className="group grid size-11 place-items-center"
                >
                  <span
                    className={`block h-1.5 rounded-full transition-all ${
                      i === aktif
                        ? 'w-7 bg-gold'
                        : 'w-1.5 bg-white/50 group-hover:bg-white/80'
                    }`}
                  />
                </button>
              ))}
            </div>
        </div>
      )}
    </>
  )
}
