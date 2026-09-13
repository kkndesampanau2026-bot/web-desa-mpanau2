import { useEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

/**
 * Dialog konfirmasi untuk tindakan yang tidak dapat dibatalkan.
 *
 * Menggantikan `window.confirm()` yang dipakai seluruh dashboard sebelumnya.
 * Kotak bawaan peramban memang berfungsi, tetapi ia digambar sistem operasi:
 * tidak dapat diberi gaya, judulnya justru menampilkan alamat situs, dan
 * sebagian peramban menawarkan "cegah dialog lain muncul" — sekali dicentang
 * operator, seluruh konfirmasi hapus berikutnya langsung terjawab "batal"
 * tanpa satu pun kotak terlihat, dan tombol Hapus tampak rusak.
 *
 * Yang dijaga di sini dan tidak dimiliki `confirm()`:
 *
 * - Fokus berpindah ke tombol **Batal**, bukan tombol merah. Pada tindakan
 *   merusak, tombol yang paling mudah tertekan haruslah yang paling aman.
 * - Esc menutup, klik latar menutup, dan fokus dikembalikan ke tombol yang
 *   membukanya — sehingga navigasi keyboard tidak tersesat setelah dialog
 *   ditutup.
 * - `role="alertdialog"` beserta judul & keterangan yang tertaut, agar
 *   pembaca layar mengumumkan apa yang akan terjadi (PRD 12.4).
 *
 * Tampilannya sengaja SEDIKIT. Tiga hal yang sempat ada lalu dibuang, karena
 * menambah bentuk tanpa menambah arti:
 *
 * - **Lencana bulat merah berisi ikon segitiga.** Ia mengulang apa yang sudah
 *   dikatakan judul dan tombol merahnya, sambil memakan seperempat lebar
 *   dialog dan mendorong teksnya menjadi kolom sempit.
 * - **Tombol silang di pojok.** Tugasnya persis sama dengan "Batal" yang
 *   berjarak dua sentimeter darinya. Dua jalan keluar yang berbeda rupa untuk
 *   satu maksud membuat operator berhenti sejenak memilih — padahal Esc dan
 *   klik di luar panel pun sudah menutup.
 * - **Kartu besar mengambang.** Lebarnya kini 400px dengan sudut yang lebih
 *   rapat: sebuah pertanyaan ya/tidak tidak menuntut ruang selebar formulir.
 */
export interface OpsiKonfirmasi {
  judul: string
  pesan: string
  /** Teks tombol merah. Dibiarkan kosong berarti "Hapus". */
  labelAksi?: string
  onKonfirmasi: () => void
}

export function DialogKonfirmasi({
  terbuka,
  judul,
  pesan,
  labelAksi = 'Hapus',
  onKonfirmasi,
  onBatal,
}: OpsiKonfirmasi & { terbuka: boolean; onBatal: () => void }) {
  const batalRef = useRef<HTMLButtonElement>(null)
  const pemicuRef = useRef<Element | null>(null)

  /*
   * `onBatal` disimpan di ref supaya efek di bawah hanya bergantung pada
   * `terbuka`. Pemanggilnya membuat fungsi baru pada setiap render — dengan
   * `onBatal` sebagai dependensi, efeknya dibersihkan lalu dipasang ulang
   * setiap kali halaman induk me-render (mis. saat react-query menyegarkan
   * data di belakang layar), sehingga fokus melompat dan kunci gulir halaman
   * dilepas-pasang di tengah dialog yang sedang terbuka.
   */
  const onBatalRef = useRef(onBatal)

  useEffect(() => {
    onBatalRef.current = onBatal
  })

  useEffect(() => {
    if (!terbuka) return

    // Elemen yang tadi difokus diingat SEBELUM fokus dipindahkan, lalu
    // dikembalikan saat dialog tertutup.
    pemicuRef.current = document.activeElement
    batalRef.current?.focus()

    function tanganiKey(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        e.preventDefault()
        onBatalRef.current()
      }
    }

    document.addEventListener('keydown', tanganiKey)

    // Halaman di belakang tidak boleh ikut tergulir saat dialog terbuka —
    // pada ponsel, gulir yang menembus overlay membuat dialog seperti
    // melayang lepas dari isinya.
    const gulirSemula = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', tanganiKey)
      document.body.style.overflow = gulirSemula
      if (pemicuRef.current instanceof HTMLElement) pemicuRef.current.focus()
    }
  }, [terbuka])

  if (!terbuka) return null

  return createPortal(
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-slate-900/45 p-4"
      onMouseDown={(e) => {
        // Hanya klik pada LATAR yang menutup; penekanan yang dimulai di dalam
        // panel lalu berakhir di luar (mis. saat menyeret teks) tidak ikut.
        if (e.target === e.currentTarget) onBatal()
      }}
    >
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="judul-konfirmasi"
        aria-describedby="pesan-konfirmasi"
        className="animasi-dialog w-full max-w-100 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl"
      >
        <div className="px-5 pt-5 pb-4">
          <h2 id="judul-konfirmasi" className="text-base font-semibold text-slate-900">
            {judul}
          </h2>
          <p id="pesan-konfirmasi" className="mt-1.5 text-sm leading-relaxed text-slate-600">
            {pesan}
          </p>
        </div>

        {/*
          Tombol dipisahkan garis di atas latar abu — bentuk yang sama dipakai
          dialog sistem operasi dan sebagian besar aplikasi kerja. Fungsinya
          bukan hiasan: ia memberi batas yang jelas antara "apa yang terjadi"
          dan "apa yang Anda putuskan", sehingga mata berhenti tepat di baris
          keputusan.
        */}
        <div className="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
          <button
            ref={batalRef}
            type="button"
            onClick={onBatal}
            className="rounded-md border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
          >
            Batal
          </button>
          <button
            type="button"
            onClick={onKonfirmasi}
            className="rounded-md bg-red-600 px-3.5 py-1.5 text-sm font-medium text-white transition hover:bg-red-700"
          >
            {labelAksi}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  )
}

/**
 * Pemakaian ringkas `DialogKonfirmasi` dari halaman mana pun.
 *
 * Halaman memanggil `minta({...})` di tempat `confirm()` dulu dipakai, lalu
 * merender `dialog` sekali di mana saja dalam pohonnya — isinya dirender lewat
 * portal, jadi letaknya tidak berpengaruh pada tata letak.
 */
export function useKonfirmasi(): { minta: (opsi: OpsiKonfirmasi) => void; dialog: ReactNode } {
  const [permintaan, setPermintaan] = useState<OpsiKonfirmasi | null>(null)

  return {
    minta: setPermintaan,
    dialog: (
      <DialogKonfirmasi
        terbuka={permintaan !== null}
        judul={permintaan?.judul ?? ''}
        pesan={permintaan?.pesan ?? ''}
        labelAksi={permintaan?.labelAksi}
        onBatal={() => setPermintaan(null)}
        onKonfirmasi={() => {
          permintaan?.onKonfirmasi()
          setPermintaan(null)
        }}
      />
    ),
  }
}
