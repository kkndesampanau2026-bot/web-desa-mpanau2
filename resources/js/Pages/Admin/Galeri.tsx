import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, urlBerkas, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { PengelolaFoto, type Foto } from '@/Components/Admin/PengelolaFoto'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface Album {
  id: number
  nama_album: string
  slug: string
  tanggal_kegiatan: string | null
  deskripsi: string | null
  cover_image: string | null
  status_tampil: boolean
  photos_count: number
  photos?: Foto[]
}

const KUNCI = ['admin', 'galeri']

/**
 * CMS Galeri Kegiatan — PRD 5.15 & 6.13.
 *
 * Album dibuat lebih dulu, fotonya menyusul lewat panel tersendiri. Alurnya
 * mengikuti pemisahan endpoint di backend: satu album kegiatan desa lazim
 * berisi puluhan foto, dan kegagalan di tengah unggahan tidak boleh ikut
 * membatalkan data albumnya.
 */
export default function GaleriPage() {
  const queryClient = useQueryClient()
  const [dibuka, setDibuka] = useState<number | null>(null)

  const { data, isPending } = useQuery({
    queryKey: KUNCI,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Album[]>>('/admin/galeri')
      return r.data.data
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/galeri/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: KUNCI }),
  })

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Galeri Kegiatan</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelompokkan foto kegiatan desa ke dalam album. Album tampil pada halaman
          Galeri situs publik.
        </p>
      </div>

      <FormAlbum />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">
              Belum ada album. Buat album terlebih dahulu, lalu unggah fotonya.
            </p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((a) => (
                <li key={a.id} className="py-3">
                  <div className="flex items-center justify-between gap-4">
                    {a.cover_image ? (
                      <img
                        src={urlBerkas(a.cover_image) ?? undefined}
                        alt=""
                        className="h-12 w-16 shrink-0 rounded-lg border border-slate-200 object-cover"
                      />
                    ) : (
                      <span
                        aria-hidden="true"
                        className="h-12 w-16 shrink-0 rounded-lg border border-dashed border-slate-300"
                      />
                    )}

                    <div className="min-w-0 flex-1">
                      <p className="truncate font-medium text-slate-900">{a.nama_album}</p>
                      <p className="truncate text-sm text-slate-500">
                        {[
                          a.tanggal_kegiatan,
                          `${a.photos_count} foto`,
                          !a.status_tampil && 'disembunyikan',
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-3 text-sm">
                      <button
                        onClick={() => setDibuka(dibuka === a.id ? null : a.id)}
                        aria-expanded={dibuka === a.id}
                        className="text-slate-700 hover:underline"
                      >
                        {dibuka === a.id ? 'Tutup Foto' : 'Kelola Foto'}
                      </button>
                      <button
                        onClick={() => {
                          if (
                            confirm(
                              `Hapus album "${a.nama_album}" beserta ${a.photos_count} fotonya? ` +
                                'Berkas foto ikut terhapus dari server dan tidak dapat dipulihkan.',
                            )
                          ) {
                            hapus.mutate(a.id)
                          }
                        }}
                        className="text-red-600 hover:underline"
                      >
                        Hapus
                      </button>
                    </div>
                  </div>

                  {dibuka === a.id && <PanelFotoAlbum album={a} />}
                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

/**
 * Panel foto satu album.
 *
 * Daftar foto diambil lewat permintaan tersendiri, bukan dari daftar album:
 * daftar album hanya membawa jumlah foto, dan memuat seluruh foto setiap album
 * sekaligus akan membebani halaman tanpa dibutuhkan sampai operator membukanya.
 */
function PanelFotoAlbum({ album }: { album: Album }) {
  const queryClient = useQueryClient()
  const kunciDetail = [...KUNCI, album.id]

  const { data, isPending } = useQuery({
    queryKey: kunciDetail,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Album>>(`/admin/galeri/${album.id}`)
      return r.data.data
    },
  })

  return (
    <div className="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
      {isPending ? (
        <p className="text-sm text-slate-500">Memuat foto…</p>
      ) : (
        <PengelolaFoto
          urlUnggah={`/admin/galeri/${album.id}/foto`}
          urlHapus={(idFoto) => `/admin/galeri/${album.id}/foto/${idFoto}`}
          foto={data?.photos ?? []}
          onBerubah={() => {
            void queryClient.invalidateQueries({ queryKey: kunciDetail })
            // Daftar album ikut disegarkan agar jumlah foto dan sampul
            // otomatisnya tidak tertinggal menampilkan angka lama.
            void queryClient.invalidateQueries({ queryKey: KUNCI })
          }}
          petunjuk="Album tanpa sampul akan memakai foto pertama yang diunggah."
        />
      )}
    </div>
  )
}

function FormAlbum() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ nama_album: '', tanggal_kegiatan: '', deskripsi: '' })
  const [sampul, setSampul] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const tambah = useMutation({
    mutationFn: () =>
      api.post(
        '/admin/galeri',
        keFormData({
          ...form,
          // Tanggal kosong dikirim null; string kosong ditolak aturan `date`.
          tanggal_kegiatan: form.tanggal_kegiatan || null,
          cover_image: sampul,
        }),
      ),
    onSuccess: () => {
      setForm({ nama_album: '', tanggal_kegiatan: '', deskripsi: '' })
      setSampul(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: KUNCI })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  return (
    <Kartu
      judul="Buat Album"
      anak={
        <form
          onSubmit={(e: FormEvent) => {
            e.preventDefault()
            tambah.mutate()
          }}
          className="space-y-4"
        >
          {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

          <div className="grid gap-4 sm:grid-cols-2">
            <Kolom
              label="Nama Album"
              htmlFor="nama_album"
              galat={galat?.fieldError('nama_album')}
            >
              <Input
                id="nama_album"
                required
                placeholder="mis. HUT Kemerdekaan 2026"
                value={form.nama_album}
                onChange={(e) => setForm({ ...form, nama_album: e.target.value })}
                galat={galat?.fieldError('nama_album')}
              />
            </Kolom>

            <Kolom
              label="Tanggal Kegiatan"
              htmlFor="tanggal_kegiatan"
              galat={galat?.fieldError('tanggal_kegiatan')}
            >
              <Input
                id="tanggal_kegiatan"
                type="date"
                value={form.tanggal_kegiatan}
                onChange={(e) => setForm({ ...form, tanggal_kegiatan: e.target.value })}
                galat={galat?.fieldError('tanggal_kegiatan')}
              />
            </Kolom>

            <div className="sm:col-span-2">
              <Kolom label="Deskripsi" htmlFor="deskripsi">
                <TextArea
                  id="deskripsi"
                  rows={2}
                  value={form.deskripsi}
                  onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                />
              </Kolom>
            </div>
          </div>

          <InputBerkas
            label="Sampul Album"
            jenis="gambar"
            berkas={sampul}
            onPilih={setSampul}
            petunjuk="Opsional — bila dikosongkan, foto pertama yang diunggah dipakai sebagai sampul."
            galat={galat?.fieldError('cover_image')}
          />

          <Tombol type="submit" disabled={tambah.isPending}>
            {tambah.isPending ? 'Menyimpan…' : 'Buat Album'}
          </Tombol>
        </form>
      }
    />
  )
}

GaleriPage.layout = (page: ReactNode) => <LayoutAdmin judul="Galeri">{page}</LayoutAdmin>
