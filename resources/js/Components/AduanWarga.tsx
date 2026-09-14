import { useEffect, useState } from 'react'
import { Megaphone, X } from 'lucide-react'
import { FormAduan } from '@/Components/FormAduan'

/**
 * Tombol mengambang "Aduan Warga" beserta popup formulirnya.
 *
 * Pintu masuk CEPAT: ia menemani pengunjung di setiap halaman publik, sehingga
 * warga yang menemukan persoalan di tengah membaca berita tidak perlu mencari
 * halaman formulir lebih dulu.
 *
 * Pintu masuk satunya adalah halaman `/pengaduan`, yang punya alamat sendiri
 * dan karena itu dapat dibagikan lewat tautan. Keduanya memuat formulir yang
 * SAMA — `FormAduan` — bukan dua salinan yang harus dijaga tetap serupa.
 */
export function AduanWarga() {
  const [buka, setBuka] = useState(false)

  useEffect(() => {
    if (!buka) return

    function tanganiTombol(e: KeyboardEvent) {
      if (e.key === 'Escape') setBuka(false)
    }

    window.addEventListener('keydown', tanganiTombol)

    return () => window.removeEventListener('keydown', tanganiTombol)
  }, [buka])

  return (
    <>
      <button
        onClick={() => setBuka(true)}
        className="font-heading fixed right-5 bottom-5 z-40 inline-flex items-center gap-2 rounded-full bg-gold px-5 py-3.5 font-bold text-navy shadow-xl transition hover:bg-gold-light"
      >
        <Megaphone className="size-5" aria-hidden="true" />
        Aduan Warga
      </button>

      {buka && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Formulir Aduan Warga"
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
          onClick={() => setBuka(false)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl"
          >
            <div className="flex items-start justify-between gap-4 border-b border-black/5 p-6">
              <div>
                <h2 className="font-heading text-xl font-bold text-navy">Aduan Warga</h2>
                <p className="mt-1 text-sm text-slate-600">
                  Sampaikan pengaduan Anda. Nomor tiket akan diberikan untuk memantau tindak
                  lanjutnya.
                </p>
              </div>
              <button
                onClick={() => setBuka(false)}
                aria-label="Tutup"
                className="shrink-0 rounded-full p-1.5 text-slate-500 transition hover:bg-navy/5 hover:text-navy"
              >
                <X className="size-5" />
              </button>
            </div>

            <div className="p-6">
              <FormAduan
                onSelesai={() => setBuka(false)}
                aksiTambahan={
                  <button
                    type="button"
                    onClick={() => setBuka(false)}
                    className="rounded-lg border border-navy/20 px-4 py-2.5 text-sm font-semibold text-navy transition hover:bg-navy/5"
                  >
                    Batal
                  </button>
                }
              />
            </div>
          </div>
        </div>
      )}
    </>
  )
}
