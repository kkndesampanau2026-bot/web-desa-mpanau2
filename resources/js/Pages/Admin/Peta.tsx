import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, urlBerkas, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import {
  AksiBaris,
  Kartu,
  Kolom,
  Input,
  Pemberitahuan,
  TextArea,
  Tombol,
} from '@/Components/Admin/Form'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface TitikLokasi {
  id: number
  nama: string
  kategori: string
  deskripsi: string | null
  alamat: string | null
  foto: string | null
  latitude: string | number
  longitude: string | number
  status_tampil: boolean
  dusun: { id: number; nama: string } | null
}

interface DataPoi {
  titik: TitikLokasi[]
  kategori_bawaan: string[]
}

/** CMS Titik Lokasi (POI) — PRD 5.16. */
export default function PetaAdminPage() {
  const queryClient = useQueryClient()
  const kosong = {
    nama: '',
    kategori: '',
    deskripsi: '',
    alamat: '',
    latitude: '',
    longitude: '',
  }

  const [form, setForm] = useState(kosong)
  const [foto, setFoto] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sunting, setSunting] = useState<TitikLokasi | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'poi'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<DataPoi>>('/admin/points-of-interest')
      return r.data.data
    },
  })

  const simpan = useMutation({
    mutationFn: () =>
      // POST + `_method=PUT` saat menyunting: foto dikirim multipart, dan PHP
      // tidak mengurai body multipart pada request PUT.
      sunting
        ? api.post(
            `/admin/points-of-interest/${sunting.id}`,
            keFormData({ ...form, foto }, { method: 'PUT' }),
          )
        : api.post('/admin/points-of-interest', keFormData({ ...form, foto })),
    onSuccess: () => {
      // Kategori sengaja dipertahankan saat menambah beruntun: operator lazim
      // memasukkan beberapa titik sekategori sekaligus.
      setForm({ ...kosong, kategori: sunting ? '' : form.kategori })
      setFoto(null)
      setGalat(null)
      setSunting(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'poi'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  function mulaiSunting(t: TitikLokasi) {
    setSunting(t)
    setForm({
      nama: t.nama,
      kategori: t.kategori,
      deskripsi: t.deskripsi ?? '',
      alamat: t.alamat ?? '',
      latitude: String(t.latitude),
      longitude: String(t.longitude),
    })
    setFoto(null)
    setGalat(null)
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function batalSunting() {
    setSunting(null)
    setForm(kosong)
    setFoto(null)
    setGalat(null)
  }

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/points-of-interest/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'poi'] }),
  })

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Titik Lokasi Peta</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelola titik yang tampil pada peta desa. Pastikan koordinat diambil dari
          lokasi fisik yang benar — peta yang menunjuk tempat keliru lebih
          menyesatkan daripada peta yang kosong.
        </p>
      </div>

      <Kartu
        judul={sunting ? `Ubah Titik: ${sunting.nama}` : 'Tambah Titik Lokasi'}
        anak={
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              simpan.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Nama Lokasi" htmlFor="nama-poi" galat={galat?.fieldError('nama')}>
                <Input
                  id="nama-poi"
                  required
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  galat={galat?.fieldError('nama')}
                />
              </Kolom>

              <Kolom
                label="Kategori"
                htmlFor="kategori-poi"
                petunjuk="Boleh memilih dari daftar atau mengetik kategori baru."
                galat={galat?.fieldError('kategori')}
              >
                <Input
                  id="kategori-poi"
                  required
                  list="daftar-kategori-poi"
                  value={form.kategori}
                  onChange={(e) => setForm({ ...form, kategori: e.target.value })}
                  galat={galat?.fieldError('kategori')}
                />
                <datalist id="daftar-kategori-poi">
                  {data?.kategori_bawaan.map((k) => (
                    <option key={k} value={k} />
                  ))}
                </datalist>
              </Kolom>

              <Kolom
                label="Latitude"
                htmlFor="latitude"
                petunjuk="Contoh: -0.9553000"
                galat={galat?.fieldError('latitude')}
              >
                <Input
                  id="latitude"
                  required
                  value={form.latitude}
                  onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                  galat={galat?.fieldError('latitude')}
                />
              </Kolom>

              <Kolom
                label="Longitude"
                htmlFor="longitude"
                petunjuk="Contoh: 119.9089000"
                galat={galat?.fieldError('longitude')}
              >
                <Input
                  id="longitude"
                  required
                  value={form.longitude}
                  onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                  galat={galat?.fieldError('longitude')}
                />
              </Kolom>

              <div className="sm:col-span-2">
                <Kolom label="Alamat" htmlFor="alamat-poi">
                  <Input
                    id="alamat-poi"
                    value={form.alamat}
                    onChange={(e) => setForm({ ...form, alamat: e.target.value })}
                  />
                </Kolom>
              </div>

              <div className="sm:col-span-2">
                <Kolom label="Deskripsi" htmlFor="deskripsi-poi">
                  <TextArea
                    id="deskripsi-poi"
                    rows={2}
                    value={form.deskripsi}
                    onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
                  />
                </Kolom>
              </div>

              <div className="sm:col-span-2">
                <InputBerkas
                  label="Foto Lokasi"
                  jenis="gambar"
                  berkas={foto}
                  onPilih={setFoto}
                  pathTersimpan={sunting?.foto ?? undefined}
                  petunjuk={
                    sunting
                      ? 'Biarkan kosong bila foto tidak diganti.'
                      : 'Tampil pada popup titik di peta.'
                  }
                  galat={galat?.fieldError('foto')}
                />
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah Titik'}
              </Tombol>

              {sunting && (
                <Tombol type="button" variasi="sekunder" onClick={batalSunting}>
                  Batal
                </Tombol>
              )}
            </div>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.titik.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada titik lokasi.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Nama</th>
                    <th scope="col" className="pb-2 font-medium">Kategori</th>
                    <th scope="col" className="pb-2 font-medium">Koordinat</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.titik.map((t) => (
                    <tr key={t.id} className="border-b border-slate-100">
                      <td className="py-2.5 pr-4">
                        <div className="flex items-center gap-3">
                          {t.foto && (
                            <img
                              src={urlBerkas(t.foto) ?? undefined}
                              alt=""
                              className="h-9 w-9 shrink-0 rounded border border-slate-200 object-cover"
                            />
                          )}
                          <div className="min-w-0">
                            <p className="font-medium text-slate-900">{t.nama}</p>
                            {!t.status_tampil && (
                              <p className="text-xs text-slate-500">disembunyikan</p>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="py-2.5 pr-4 text-slate-700">{t.kategori}</td>
                      <td className="py-2.5 pr-4 font-mono text-xs text-slate-600">
                        {Number(t.latitude).toFixed(5)}, {Number(t.longitude).toFixed(5)}
                      </td>
                      <td className="py-2.5">
                        <AksiBaris
                          nama={`titik “${t.nama}”`}
                          onSunting={() => mulaiSunting(t)}
                          onHapus={() => hapus.mutate(t.id)}
                          sedangProses={hapus.isPending}
                        />
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

PetaAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="Titik Lokasi">{page}</LayoutAdmin>
