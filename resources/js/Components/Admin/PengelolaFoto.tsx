import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Check, Pencil, X } from 'lucide-react'
import { api, ApiRequestError, urlBerkas } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Pemberitahuan, Tombol } from '@/Components/Admin/Form'
import { InputBanyakGambar } from '@/Components/Admin/Berkas'

export interface Foto {
  id: number
  path: string
  caption?: string | null
  alt_text?: string | null
}

/**
 * Pengelola foto untuk satu entitas induk (album, destinasi wisata, produk).
 *
 * Unggahan dan penghapusan sengaja dipisah dari formulir induknya. Satu album
 * kegiatan desa bisa berisi puluhan foto; menggabungkannya ke dalam satu
 * penyimpanan formulir berarti kegagalan jaringan di foto ke-27 membatalkan
 * seluruh pekerjaan, termasuk data album yang sudah benar.
 */
export function PengelolaFoto({
  urlUnggah,
  urlHapus,
  urlUbah,
  foto,
  onBerubah,
  petunjuk,
}: {
  /** Endpoint POST untuk mengunggah satu batch foto. */
  urlUnggah: string
  /** Membentuk endpoint DELETE untuk satu foto. */
  urlHapus: (idFoto: number) => string
  /**
   * Membentuk endpoint PUT untuk menyunting keterangan satu foto. Bila tidak
   * diberikan, tombol suntingnya tidak muncul — modul yang induknya belum
   * menyediakan endpoint itu (wisata & produk) tidak menampilkan janji palsu.
   */
  urlUbah?: (idFoto: number) => string
  foto: Foto[]
  /** Dipanggil setelah unggah/hapus berhasil, untuk memuat ulang data. */
  onBerubah: () => void
  petunjuk?: string
}) {
  const [pilihan, setPilihan] = useState<File[]>([])
  const [galat, setGalat] = useState<string | null>(null)
  const [suntingId, setSuntingId] = useState<number | null>(null)
  const [caption, setCaption] = useState('')

  const unggah = useMutation({
    mutationFn: () => api.post(urlUnggah, keFormData({ foto: pilihan })),
    onSuccess: () => {
      setPilihan([])
      setGalat(null)
      onBerubah()
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e.message : 'Gagal mengunggah foto.'),
  })

  const ubah = useMutation({
    mutationFn: (idFoto: number) =>
      api.put(urlUbah!(idFoto), { caption: caption || null, alt_text: caption || null }),
    onSuccess: () => {
      setSuntingId(null)
      setGalat(null)
      onBerubah()
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e.message : 'Gagal menyimpan keterangan.'),
  })

  const hapus = useMutation({
    mutationFn: (idFoto: number) => api.delete(urlHapus(idFoto)),
    onSuccess: () => {
      setGalat(null)
      onBerubah()
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e.message : 'Gagal menghapus foto.'),
  })

  return (
    <div className="space-y-4">
      {galat && <Pemberitahuan jenis="galat" pesan={galat} />}

      {foto.length > 0 && (
        <ul className="grid grid-cols-3 gap-2 sm:grid-cols-5">
          {foto.map((f) => (
            <li key={f.id} className="group relative">
              <img
                src={urlBerkas(f.path) ?? undefined}
                alt={f.alt_text ?? f.caption ?? 'Foto'}
                className="aspect-square w-full rounded-lg border border-slate-200 object-cover"
              />
              <div className="absolute top-1 right-1 flex gap-1">
                {urlUbah && (
                  <button
                    type="button"
                    title="Ubah keterangan"
                    aria-label={`Ubah keterangan foto ${f.caption ?? f.id}`}
                    onClick={() => {
                      setSuntingId(f.id)
                      setCaption(f.caption ?? '')
                    }}
                    className="rounded-full bg-white/90 p-1 text-slate-600 shadow-sm transition hover:bg-white hover:text-teal-700"
                  >
                    <Pencil className="size-3.5" aria-hidden="true" />
                  </button>
                )}

                <button
                  type="button"
                  disabled={hapus.isPending}
                  title="Hapus foto"
                  aria-label={`Hapus foto ${f.caption ?? f.id}`}
                  onClick={() => {
                    if (confirm('Hapus foto ini? Berkasnya ikut terhapus dari server.')) {
                      hapus.mutate(f.id)
                    }
                  }}
                  className="rounded-full bg-white/90 p-1 text-red-600 shadow-sm transition hover:bg-white disabled:opacity-50"
                >
                  <X className="size-3.5" aria-hidden="true" />
                </button>
              </div>

              {suntingId === f.id ? (
                <div className="mt-1 flex items-center gap-1">
                  <input
                    value={caption}
                    onChange={(e) => setCaption(e.target.value)}
                    placeholder="Keterangan foto"
                    aria-label="Keterangan foto"
                    className="w-full rounded border border-slate-300 px-1.5 py-0.5 text-xs outline-none focus:border-teal-600"
                  />
                  <button
                    type="button"
                    title="Simpan"
                    aria-label="Simpan keterangan"
                    disabled={ubah.isPending}
                    onClick={() => ubah.mutate(f.id)}
                    className="shrink-0 rounded p-1 text-teal-700 hover:bg-teal-50 disabled:opacity-50"
                  >
                    <Check className="size-3.5" aria-hidden="true" />
                  </button>
                  <button
                    type="button"
                    title="Batal"
                    aria-label="Batal menyunting keterangan"
                    onClick={() => setSuntingId(null)}
                    className="shrink-0 rounded p-1 text-slate-500 hover:bg-slate-100"
                  >
                    <X className="size-3.5" aria-hidden="true" />
                  </button>
                </div>
              ) : (
                f.caption && <p className="mt-1 truncate text-xs text-slate-500">{f.caption}</p>
              )}
            </li>
          ))}
        </ul>
      )}

      <InputBanyakGambar
        label="Tambah Foto"
        berkas={pilihan}
        onUbah={setPilihan}
        petunjuk={petunjuk}
      />

      {pilihan.length > 0 && (
        <div className="flex gap-2">
          <Tombol type="button" disabled={unggah.isPending} onClick={() => unggah.mutate()}>
            {unggah.isPending ? 'Mengunggah…' : `Unggah ${pilihan.length} Foto`}
          </Tombol>
          <Tombol type="button" variasi="sekunder" onClick={() => setPilihan([])}>
            Kosongkan
          </Tombol>
        </div>
      )}
    </div>
  )
}
