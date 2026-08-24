import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
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
  foto,
  onBerubah,
  petunjuk,
}: {
  /** Endpoint POST untuk mengunggah satu batch foto. */
  urlUnggah: string
  /** Membentuk endpoint DELETE untuk satu foto. */
  urlHapus: (idFoto: number) => string
  foto: Foto[]
  /** Dipanggil setelah unggah/hapus berhasil, untuk memuat ulang data. */
  onBerubah: () => void
  petunjuk?: string
}) {
  const [pilihan, setPilihan] = useState<File[]>([])
  const [galat, setGalat] = useState<string | null>(null)

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
              <button
                type="button"
                disabled={hapus.isPending}
                onClick={() => {
                  if (confirm('Hapus foto ini? Berkasnya ikut terhapus dari server.')) {
                    hapus.mutate(f.id)
                  }
                }}
                className="absolute right-1 top-1 rounded-full bg-white/90 px-2 py-0.5 text-xs font-medium text-red-600 shadow-sm transition hover:bg-white disabled:opacity-50"
              >
                Hapus
              </button>
              {f.caption && (
                <p className="mt-1 truncate text-xs text-slate-500">{f.caption}</p>
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
