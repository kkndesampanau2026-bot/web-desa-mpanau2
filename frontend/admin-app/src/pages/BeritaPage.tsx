import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, urlBerkas, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/components/Form'
import { InputBerkas } from '@/components/Berkas'

interface Berita {
  id: number
  judul: string
  slug: string
  ringkasan: string | null
  konten: string
  status: 'draft' | 'terjadwal' | 'published' | 'diarsipkan'
  tanggal_publish: string | null
  gambar_utama: string | null
  og_image: string | null
  jumlah_dilihat: number
}

const STATUS: Berita['status'][] = ['draft', 'terjadwal', 'published', 'diarsipkan']

const LABEL_STATUS: Record<Berita['status'], string> = {
  draft: 'Draft',
  terjadwal: 'Terjadwal',
  published: 'Terbit',
  diarsipkan: 'Diarsipkan',
}

/** CMS Berita — PRD 5.11. */
export function BeritaPage() {
  const queryClient = useQueryClient()
  const [sedangSunting, setSedangSunting] = useState<Berita | null>(null)
  const [formTerbuka, setFormTerbuka] = useState(false)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'berita'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Berita[]>>('/admin/berita')
      return r.data.data
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/berita/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'berita'] }),
  })

  function tutupForm() {
    setFormTerbuka(false)
    setSedangSunting(null)
  }

  if (formTerbuka) {
    return <FormBerita berita={sedangSunting} onSelesai={tutupForm} />
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Berita</h1>
          <p className="mt-1 text-sm text-slate-600">
            Kelola artikel berita yang tampil di situs publik.
          </p>
        </div>
        <Tombol onClick={() => setFormTerbuka(true)}>+ Tulis Berita</Tombol>
      </div>

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">
              Belum ada berita. Klik “Tulis Berita” untuk membuat yang pertama.
            </p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Judul</th>
                    <th scope="col" className="pb-2 font-medium">Status</th>
                    <th scope="col" className="pb-2 font-medium">Dilihat</th>
                    <th scope="col" className="pb-2 font-medium"><span className="sr-only">Aksi</span></th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((b) => (
                    <tr key={b.id} className="border-b border-slate-100">
                      <td className="py-2.5 pr-4">
                        <div className="flex items-center gap-3">
                          {b.gambar_utama ? (
                            <img
                              src={urlBerkas(b.gambar_utama) ?? undefined}
                              alt=""
                              className="h-10 w-14 shrink-0 rounded border border-slate-200 object-cover"
                            />
                          ) : (
                            <span
                              aria-hidden="true"
                              className="h-10 w-14 shrink-0 rounded border border-dashed border-slate-300"
                            />
                          )}
                          <div className="min-w-0">
                            <p className="font-medium text-slate-900">{b.judul}</p>
                            <p className="text-xs text-slate-500">/{b.slug}</p>
                          </div>
                        </div>
                      </td>
                      <td className="py-2.5 pr-4">
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                          {LABEL_STATUS[b.status]}
                        </span>
                      </td>
                      <td className="py-2.5 pr-4 tabular-nums text-slate-600">
                        {b.jumlah_dilihat.toLocaleString('id-ID')}
                      </td>
                      <td className="py-2.5 text-right whitespace-nowrap">
                        <button
                          onClick={() => {
                            setSedangSunting(b)
                            setFormTerbuka(true)
                          }}
                          className="mr-3 text-slate-700 hover:underline"
                        >
                          Sunting
                        </button>
                        <button
                          onClick={() => {
                            if (confirm(`Hapus berita “${b.judul}”?`)) hapus.mutate(b.id)
                          }}
                          className="text-red-600 hover:underline"
                        >
                          Hapus
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        }
      />
    </div>
  )
}

function FormBerita({
  berita,
  onSelesai,
}: {
  berita: Berita | null
  onSelesai: () => void
}) {
  const queryClient = useQueryClient()
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [gambarUtama, setGambarUtama] = useState<File | null>(null)
  const [ogImage, setOgImage] = useState<File | null>(null)
  const [form, setForm] = useState({
    judul: berita?.judul ?? '',
    ringkasan: berita?.ringkasan ?? '',
    konten: berita?.konten ?? '',
    status: berita?.status ?? ('draft' as Berita['status']),
    // datetime-local menuntut format YYYY-MM-DDTHH:mm.
    tanggal_publish: berita?.tanggal_publish?.slice(0, 16) ?? '',
  })

  const simpan = useMutation({
    mutationFn: (nilai: typeof form) => {
      const isi = {
        ...nilai,
        tanggal_publish: nilai.tanggal_publish || null,
        gambar_utama: gambarUtama,
        og_image: ogImage,
      }

      // Pembaruan dikirim sebagai POST dengan `_method=PUT`: PHP tidak
      // mengurai body multipart pada request PUT, sehingga formulir akan tiba
      // dalam keadaan kosong bila dikirim apa adanya.
      return berita
        ? api.post(`/admin/berita/${berita.id}`, keFormData(isi, { method: 'PUT' }))
        : api.post('/admin/berita', keFormData(isi))
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['admin', 'berita'] })
      onSelesai()
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate(form)
  }

  return (
    <form onSubmit={kirim} className="max-w-3xl space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h1 className="text-xl font-semibold text-slate-900">
          {berita ? 'Sunting Berita' : 'Tulis Berita'}
        </h1>
        <Tombol type="button" variasi="sekunder" onClick={onSelesai}>
          Batal
        </Tombol>
      </div>

      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <Kartu
        anak={
          <div className="space-y-4">
            <Kolom label="Judul" htmlFor="judul" galat={galat?.fieldError('judul')}>
              <Input
                id="judul"
                required
                value={form.judul}
                onChange={(e) => setForm({ ...form, judul: e.target.value })}
                galat={galat?.fieldError('judul')}
              />
            </Kolom>

            <Kolom
              label="Ringkasan"
              htmlFor="ringkasan"
              petunjuk="Tampil pada kartu berita dan hasil pencarian."
              galat={galat?.fieldError('ringkasan')}
            >
              <TextArea
                id="ringkasan"
                rows={2}
                value={form.ringkasan}
                onChange={(e) => setForm({ ...form, ringkasan: e.target.value })}
                galat={galat?.fieldError('ringkasan')}
              />
            </Kolom>

            <Kolom
              label="Isi Berita"
              htmlFor="konten"
              petunjuk="Mendukung HTML sederhana. Isi dibersihkan otomatis dari skrip berbahaya."
              galat={galat?.fieldError('konten')}
            >
              <TextArea
                id="konten"
                rows={12}
                required
                value={form.konten}
                onChange={(e) => setForm({ ...form, konten: e.target.value })}
                galat={galat?.fieldError('konten')}
              />
            </Kolom>

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Status" htmlFor="status">
                <select
                  id="status"
                  value={form.status}
                  onChange={(e) =>
                    setForm({ ...form, status: e.target.value as Berita['status'] })
                  }
                  className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
                >
                  {STATUS.map((s) => (
                    <option key={s} value={s}>
                      {LABEL_STATUS[s]}
                    </option>
                  ))}
                </select>
              </Kolom>

              <Kolom
                label="Tanggal Terbit"
                htmlFor="tanggal_publish"
                petunjuk={
                  form.status === 'terjadwal'
                    ? 'Wajib diisi. Berita tayang otomatis pada waktu ini.'
                    : 'Kosongkan untuk memakai waktu saat disimpan.'
                }
                galat={galat?.fieldError('tanggal_publish')}
              >
                <Input
                  id="tanggal_publish"
                  type="datetime-local"
                  value={form.tanggal_publish}
                  onChange={(e) => setForm({ ...form, tanggal_publish: e.target.value })}
                  galat={galat?.fieldError('tanggal_publish')}
                />
              </Kolom>
            </div>

            <InputBerkas
              label="Gambar Utama"
              jenis="gambar"
              berkas={gambarUtama}
              onPilih={setGambarUtama}
              pathTersimpan={berita?.gambar_utama}
              petunjuk="Tampil sebagai sampul artikel."
              galat={galat?.fieldError('gambar_utama')}
            />

            <InputBerkas
              label="Gambar Bagikan (OG Image)"
              jenis="gambar"
              berkas={ogImage}
              onPilih={setOgImage}
              pathTersimpan={berita?.og_image}
              petunjuk="Tampil saat tautan dibagikan ke WhatsApp atau Facebook. Kosongkan untuk memakai gambar utama."
              galat={galat?.fieldError('og_image')}
            />
          </div>
        }
      />

      <Tombol type="submit" disabled={simpan.isPending}>
        {simpan.isPending ? 'Menyimpan…' : 'Simpan Berita'}
      </Tombol>
    </form>
  )
}
