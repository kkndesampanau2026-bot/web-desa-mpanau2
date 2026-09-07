import { Fragment, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, urlBerkas, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import {
  AksiBaris,
  Input,
  Kartu,
  Kolom,
  Pemberitahuan,
  Pilihan,
  TextArea,
  Tombol,
} from '@/Components/Admin/Form'
import { InputBanyakGambar, InputBerkas } from '@/Components/Admin/Berkas'
import { PengelolaFoto, type Foto } from '@/Components/Admin/PengelolaFoto'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface Potensi {
  id: number
  kategori: string
  judul: string
  slug: string
  deskripsi: string | null
  foto: string | null
  latitude: string | null
  longitude: string | null
  status_tampil: boolean
  urutan_tampil: number
}

interface Wisata {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  alamat: string | null
  harga_tiket: string | null
  status_tampil: boolean
  photos: Foto[]
  photos_count: number
}

interface Produk {
  id: number
  nama_produk: string
  slug: string
  kategori: string | null
  harga: string | number | null
  satuan: string | null
  tersedia: boolean
  nama_penjual: string
  kontak_wa: string | null
  status_tampil: boolean
  photos: Foto[]
  photos_count: number
}

const KATEGORI_POTENSI = [
  'Ekonomi',
  'Pariwisata',
  'Pertanian',
  'Industri Kreatif',
  'Lingkungan/Kelestarian',
]

const POTENSI_KOSONG = {
  kategori: KATEGORI_POTENSI[0],
  judul: '',
  deskripsi: '',
  latitude: '',
  longitude: '',
  urutan_tampil: '',
  status_tampil: true,
}

const FORMAT_RUPIAH = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

/**
 * CMS Potensi Desa, Wisata, dan Produk UMKM — PRD 5.12–5.14.
 *
 * Modul Produk sengaja tidak memiliki pengelolaan pesanan: cakupannya
 * katalog saja (DEVIASI A4).
 */
export default function EkonomiAdminPage() {
  const [tab, setTab] = useState<'potensi' | 'wisata' | 'produk'>('potensi')

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Potensi &amp; Ekonomi Desa</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelola potensi desa, destinasi wisata, dan katalog produk UMKM yang tampil di
          situs publik.
        </p>
        <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
          Ketiga tab ini mengisi satu halaman publik, <code>/potensi</code>:{' '}
          <strong className="font-medium">Potensi Desa</strong> tampil pada kategorinya
          masing-masing, <strong className="font-medium">Wisata</strong> di{' '}
          <code>/potensi?kategori=Pariwisata</code>, dan{' '}
          <strong className="font-medium">Produk UMKM</strong> di{' '}
          <code>/potensi?kategori=Ekonomi</code>. Detail tiap isinya berada di{' '}
          <code>/potensi/&lt;slug&gt;</code> dengan kategori yang sama ikut terbawa.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {(
          [
            ['potensi', 'Potensi Desa'],
            ['wisata', 'Wisata'],
            ['produk', 'Produk UMKM'],
          ] as const
        ).map(([kunci, label]) => (
          <button
            key={kunci}
            role="tab"
            aria-selected={tab === kunci}
            onClick={() => setTab(kunci)}
            className={`-mb-px border-b-2 px-4 py-2 text-sm transition ${
              tab === kunci
                ? 'border-slate-900 font-medium text-slate-900'
                : 'border-transparent text-slate-500 hover:text-slate-800'
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'potensi' && <PanelPotensi />}
      {tab === 'wisata' && <PanelWisata />}
      {tab === 'produk' && <PanelProduk />}
    </div>
  )
}

function PanelPotensi() {
  const queryClient = useQueryClient()
  const [sunting, setSunting] = useState<Potensi | null>(null)
  const [form, setForm] = useState(POTENSI_KOSONG)
  const [foto, setFoto] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'potensi'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Potensi[]>>('/admin/potensi')
      return r.data.data
    },
  })

  function bersihkan() {
    setSunting(null)
    setForm(POTENSI_KOSONG)
    setFoto(null)
    setGalat(null)
  }

  function mulaiSunting(potensi: Potensi) {
    setSunting(potensi)
    setForm({
      kategori: potensi.kategori,
      judul: potensi.judul,
      deskripsi: potensi.deskripsi ?? '',
      latitude: potensi.latitude ?? '',
      longitude: potensi.longitude ?? '',
      urutan_tampil: String(potensi.urutan_tampil ?? ''),
      status_tampil: potensi.status_tampil,
    })
    setFoto(null)
    setGalat(null)
  }

  const simpan = useMutation({
    mutationFn: () => {
      const isi = {
        ...form,
        // Dikirim sebagai string kosong, bukan dilewati: Laravel mengubahnya
        // menjadi null, sehingga koordinat yang terlanjur salah bisa
        // dikosongkan lagi — kunci yang dilewati justru mempertahankan isinya.
        latitude: form.latitude.trim(),
        longitude: form.longitude.trim(),
        // Kolomnya NOT NULL berdefault 0, jadi kosong berarti 0, bukan null.
        urutan_tampil: form.urutan_tampil.trim() || 0,
        foto,
      }

      // Pembaruan dikirim sebagai POST dengan `_method=PUT`: PHP tidak mengurai
      // body multipart pada request PUT, sehingga formulir yang membawa foto
      // akan tiba dalam keadaan kosong bila dikirim apa adanya.
      return sunting
        ? api.post(`/admin/potensi/${sunting.id}`, keFormData(isi, { method: 'PUT' }))
        : api.post('/admin/potensi', keFormData(isi))
    },
    onSuccess: () => {
      bersihkan()
      void queryClient.invalidateQueries({ queryKey: ['admin', 'potensi'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/potensi/${id}`),
    onSuccess: (_, id) => {
      if (sunting?.id === id) bersihkan()
      void queryClient.invalidateQueries({ queryKey: ['admin', 'potensi'] })
    },
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? `Ubah Potensi: ${sunting.judul}` : 'Tambah Potensi Desa'}
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
              <Kolom
                label="Kategori"
                htmlFor="kategori-potensi"
                petunjuk="Menjadi penyaring di halaman publik /potensi"
              >
                <Pilihan
                  id="kategori-potensi"
                  value={form.kategori}
                  onChange={(v) => setForm({ ...form, kategori: v })}
                  options={KATEGORI_POTENSI.map((k) => ({ value: k, label: k }))}
                />
              </Kolom>

              <Kolom label="Judul" htmlFor="judul-potensi" galat={galat?.fieldError('judul')}>
                <Input
                  id="judul-potensi"
                  required
                  value={form.judul}
                  onChange={(e) => setForm({ ...form, judul: e.target.value })}
                  galat={galat?.fieldError('judul')}
                />
              </Kolom>
            </div>

            <Kolom
              label="Deskripsi"
              htmlFor="deskripsi-potensi"
              petunjuk="Tampil utuh di halaman detail potensi. Pisahkan paragraf dengan baris kosong."
            >
              <TextArea
                id="deskripsi-potensi"
                rows={5}
                value={form.deskripsi}
                onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
              />
            </Kolom>

            <div className="grid gap-4 sm:grid-cols-3">
              <Kolom
                label="Latitude"
                htmlFor="latitude-potensi"
                petunjuk="Opsional"
                galat={galat?.fieldError('latitude')}
              >
                <Input
                  id="latitude-potensi"
                  inputMode="decimal"
                  placeholder="mis. -0.8532140"
                  value={form.latitude}
                  onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                  galat={galat?.fieldError('latitude')}
                />
              </Kolom>

              <Kolom
                label="Longitude"
                htmlFor="longitude-potensi"
                petunjuk="Opsional"
                galat={galat?.fieldError('longitude')}
              >
                <Input
                  id="longitude-potensi"
                  inputMode="decimal"
                  placeholder="mis. 119.8707420"
                  value={form.longitude}
                  onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                  galat={galat?.fieldError('longitude')}
                />
              </Kolom>

              <Kolom
                label="Urutan Tampil"
                htmlFor="urutan-potensi"
                petunjuk="Angka kecil tampil lebih dulu"
                galat={galat?.fieldError('urutan_tampil')}
              >
                <Input
                  id="urutan-potensi"
                  type="number"
                  min={0}
                  value={form.urutan_tampil}
                  onChange={(e) => setForm({ ...form, urutan_tampil: e.target.value })}
                  galat={galat?.fieldError('urutan_tampil')}
                />
              </Kolom>
            </div>

            <InputBerkas
              label="Foto Potensi"
              jenis="gambar"
              berkas={foto}
              onPilih={setFoto}
              galat={galat?.fieldError('foto')}
              pathTersimpan={sunting?.foto}
              petunjuk={
                sunting?.foto ? 'Biarkan kosong bila foto lama tetap dipakai.' : undefined
              }
            />

            <label className="flex items-start gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={form.status_tampil}
                onChange={(e) => setForm({ ...form, status_tampil: e.target.checked })}
                className="mt-0.5 size-4 rounded border-slate-300"
              />
              Tampilkan di situs publik
            </label>

            <div className="flex items-center gap-3">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending
                  ? 'Menyimpan…'
                  : sunting
                    ? 'Simpan Perubahan'
                    : 'Tambah Potensi'}
              </Tombol>

              {sunting && (
                <button
                  type="button"
                  onClick={bersihkan}
                  className="text-sm text-slate-600 hover:underline"
                >
                  Batal
                </button>
              )}
            </div>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada potensi desa.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((p) => (
                <li key={p.id} className="flex items-center gap-4 py-3">
                  {p.foto ? (
                    <img
                      src={urlBerkas(p.foto) ?? undefined}
                      alt=""
                      className="h-11 w-11 shrink-0 rounded-lg border border-slate-200 object-cover"
                    />
                  ) : (
                    <span
                      aria-hidden="true"
                      className="h-11 w-11 shrink-0 rounded-lg border border-dashed border-slate-300"
                    />
                  )}

                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-slate-900">{p.judul}</p>
                    <p className="truncate text-sm text-slate-500">
                      {[
                        p.kategori,
                        p.latitude && 'berkoordinat',
                        !p.status_tampil && 'disembunyikan',
                      ]
                        .filter(Boolean)
                        .join(' · ')}
                    </p>
                  </div>

                  <div className="flex shrink-0 items-center gap-3 text-sm">
                    {/* Tautan keluar, bukan Inertia Link: halaman publik berada
                        di luar dashboard dan sengaja dibuka di tab terpisah. */}
                    {p.status_tampil && (
                      <a
                        href={`/potensi/${p.slug}`}
                        target="_blank"
                        rel="noreferrer"
                        className="text-slate-700 hover:underline"
                      >
                        Lihat
                      </a>
                    )}
                    <AksiBaris
                      nama={`potensi “${p.judul}”`}
                      onSunting={() => mulaiSunting(p)}
                      onHapus={() => hapus.mutate(p.id)}
                      sedangProses={hapus.isPending}
                    />
                  </div>
                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

function PanelWisata() {
  const queryClient = useQueryClient()
  const kosong = { nama: '', deskripsi: '', harga_tiket: '', alamat: '' }
  const [form, setForm] = useState(kosong)
  const [sunting, setSunting] = useState<Wisata | null>(null)
  const [fotoBaru, setFotoBaru] = useState<File[]>([])
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'wisata'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Wisata[]>>('/admin/wisata')
      return r.data.data
    },
  })

  /*
   * Foto diambil dari hasil kueri TERBARU, bukan dari `sunting` — objek itu
   * potret sesaat ketika tombol "Ubah" ditekan, dan tidak ikut berubah setelah
   * foto diunggah atau dihapus. Tanpa ini, grid foto membeku pada keadaan
   * lamanya dan operator mengira perubahannya gagal.
   */
  const fotoTersimpan = sunting
    ? (data?.find((w) => w.id === sunting.id)?.photos ?? sunting.photos ?? [])
    : []

  /*
   * Dua langkah: simpan datanya dulu, baru unggah fotonya.
   *
   * Endpoint fotonya beralamat pada destinasi yang sudah ada
   * (`/wisata/{id}/foto`), sehingga destinasi BARU belum punya id saat
   * formulir dikirim. Bila unggahan gagal setelah data tersimpan, destinasinya
   * tetap ada dan fotonya dapat ditambahkan lewat tombol "Ubah" — kegagalan
   * yang terlihat dan dapat diperbaiki, bukan data yang hilang diam-diam.
   */
  const simpan = useMutation({
    mutationFn: async () => {
      const r = sunting
        ? await api.put(`/admin/wisata/${sunting.id}`, form)
        : await api.post<ApiSuccess<Wisata>>('/admin/wisata', form)

      const id = sunting?.id ?? (r.data as ApiSuccess<Wisata>).data.id

      if (fotoBaru.length > 0) {
        await api.post(`/admin/wisata/${id}/foto`, keFormData({ foto: fotoBaru }))
      }
    },
    onSuccess: () => {
      setForm(kosong)
      setSunting(null)
      setFotoBaru([])
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'wisata'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/wisata/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'wisata'] }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? `Ubah Destinasi: ${sunting.nama}` : 'Tambah Destinasi Wisata'}
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
              <Kolom label="Nama Destinasi" htmlFor="nama-wisata" galat={galat?.fieldError('nama')}>
                <Input
                  id="nama-wisata"
                  required
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  galat={galat?.fieldError('nama')}
                />
              </Kolom>

              <Kolom
                label="Harga Tiket"
                htmlFor="harga-tiket"
                petunjuk='Boleh berupa teks, misalnya "Gratis" atau "Sukarela".'
                galat={galat?.fieldError('harga_tiket')}
              >
                <Input
                  id="harga-tiket"
                  value={form.harga_tiket}
                  onChange={(e) => setForm({ ...form, harga_tiket: e.target.value })}
                  galat={galat?.fieldError('harga_tiket')}
                />
              </Kolom>
            </div>

            <Kolom label="Alamat" htmlFor="alamat-wisata">
              <Input
                id="alamat-wisata"
                value={form.alamat}
                onChange={(e) => setForm({ ...form, alamat: e.target.value })}
              />
            </Kolom>

            <Kolom label="Deskripsi" htmlFor="deskripsi-wisata">
              <TextArea
                id="deskripsi-wisata"
                rows={3}
                value={form.deskripsi}
                onChange={(e) => setForm({ ...form, deskripsi: e.target.value })}
              />
            </Kolom>

            {/*
              Saat MENYUNTING, foto yang sudah tersimpan ikut tampil di dalam
              formulir — beserta tombol ganti keterangan, geser urutan, dan
              hapus. Sebelumnya keduanya terpisah: formulir hanya menerima foto
              BARU, sementara yang sudah ada hanya dapat disentuh lewat tombol
              "Kelola Foto" pada baris daftar. Operator yang membuka "Ubah"
              wajar mengira semua yang bisa diubah ada di situ.

              Saat MENAMBAH, destinasinya belum punya id sehingga endpoint
              fotonya belum beralamat ke mana pun; yang dipakai adalah pilihan
              berkas biasa yang diunggah menyusul setelah data tersimpan.
            */}
            {sunting ? (
              <div>
                <p className="block text-sm font-medium text-slate-900">Foto Destinasi</p>
                <div className="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                  <PengelolaFoto
                    urlUnggah={`/admin/wisata/${sunting.id}/foto`}
                    urlUbah={(idFoto) => `/admin/wisata/${sunting.id}/foto/${idFoto}`}
                    urlGeser={(idFoto) => `/admin/wisata/${sunting.id}/foto/${idFoto}/geser`}
                    urlHapus={(idFoto) => `/admin/wisata/${sunting.id}/foto/${idFoto}`}
                    foto={fotoTersimpan}
                    onBerubah={() =>
                      void queryClient.invalidateQueries({ queryKey: ['admin', 'wisata'] })
                    }
                    petunjuk="Foto pertama (bernomor 1) dipakai sebagai gambar utama destinasi. Pakai panah untuk mengubah urutannya, pensil untuk keterangan, dan silang merah untuk menghapus."
                  />
                </div>
              </div>
            ) : (
              <InputBanyakGambar
                label="Foto Destinasi"
                berkas={fotoBaru}
                onUbah={setFotoBaru}
                petunjuk="Foto pertama dipakai sebagai gambar utama destinasi. Setelah destinasi tersimpan, foto dapat ditambah, diurutkan ulang, atau dihapus lewat tombol Ubah."
                galat={galat?.fieldError('foto')}
              />
            )}

            <div className="flex flex-wrap gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah Destinasi'}
              </Tombol>

              {sunting && (
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => {
                    setSunting(null)
                    setForm(kosong)
                    setGalat(null)
                  }}
                >
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
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada destinasi wisata.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((w) => (
                <li key={w.id} className="py-3">
                  <div className="flex items-center justify-between gap-4">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-slate-900">{w.nama}</p>
                      <p className="truncate text-sm text-slate-500">
                        {[
                          w.harga_tiket && `Tiket: ${w.harga_tiket}`,
                          `${w.photos_count} foto`,
                          !w.status_tampil && 'disembunyikan',
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-3 text-sm">
                      <AksiBaris
                        nama={`destinasi “${w.nama}”`}
                        onSunting={() => {
                          setSunting(w)
                          setForm({
                            nama: w.nama,
                            deskripsi: w.deskripsi ?? '',
                            harga_tiket: w.harga_tiket ?? '',
                            alamat: w.alamat ?? '',
                          })
                          setGalat(null)
                          window.scrollTo({ top: 0, behavior: 'smooth' })
                        }}
                        onHapus={() => hapus.mutate(w.id)}
                        sedangProses={hapus.isPending}
                      />
                    </div>
                  </div>

                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

function PanelProduk() {
  const queryClient = useQueryClient()
  const kosong = {
    nama_produk: '',
    kategori: '',
    harga: '',
    satuan: '',
    nama_penjual: '',
    kontak_wa: '',
  }
  const [form, setForm] = useState(kosong)
  const [sunting, setSunting] = useState<Produk | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const [fotoBaru, setFotoBaru] = useState<File[]>([])

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'produk'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Produk[]>>('/admin/produk')
      return r.data.data
    },
  })

  // Dari hasil kueri terbaru, bukan dari potret `sunting` — lihat panel Wisata.
  const fotoTersimpan = sunting
    ? (data?.find((p) => p.id === sunting.id)?.photos ?? sunting.photos ?? [])
    : []

  // Dua langkah, dengan alasan yang sama seperti pada panel Wisata: endpoint
  // fotonya beralamat pada produk yang sudah ada, sehingga produk baru belum
  // punya id saat formulir dikirim.
  const simpan = useMutation({
    mutationFn: async () => {
      const isi = { ...form, harga: form.harga || null }

      const r = sunting
        ? await api.put(`/admin/produk/${sunting.id}`, isi)
        : await api.post<ApiSuccess<Produk>>('/admin/produk', isi)

      const id = sunting?.id ?? (r.data as ApiSuccess<Produk>).data.id

      if (fotoBaru.length > 0) {
        await api.post(`/admin/produk/${id}/foto`, keFormData({ foto: fotoBaru }))
      }
    },
    onSuccess: () => {
      setForm(kosong)
      setSunting(null)
      setFotoBaru([])
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'produk'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/produk/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'produk'] }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? `Ubah Produk: ${sunting.nama_produk}` : 'Tambah Produk UMKM'}
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
              <Kolom
                label="Nama Produk"
                htmlFor="nama-produk"
                galat={galat?.fieldError('nama_produk')}
              >
                <Input
                  id="nama-produk"
                  required
                  value={form.nama_produk}
                  onChange={(e) => setForm({ ...form, nama_produk: e.target.value })}
                  galat={galat?.fieldError('nama_produk')}
                />
              </Kolom>

              <Kolom label="Kategori" htmlFor="kategori-produk">
                <Input
                  id="kategori-produk"
                  placeholder="mis. Makanan Ringan"
                  value={form.kategori}
                  onChange={(e) => setForm({ ...form, kategori: e.target.value })}
                />
              </Kolom>

              <Kolom label="Harga (Rp)" htmlFor="harga-produk" galat={galat?.fieldError('harga')}>
                <Input
                  id="harga-produk"
                  type="number"
                  min={0}
                  value={form.harga}
                  onChange={(e) => setForm({ ...form, harga: e.target.value })}
                  galat={galat?.fieldError('harga')}
                />
              </Kolom>

              <Kolom label="Satuan" htmlFor="satuan-produk">
                <Input
                  id="satuan-produk"
                  placeholder="mis. bungkus, kg, porsi"
                  value={form.satuan}
                  onChange={(e) => setForm({ ...form, satuan: e.target.value })}
                />
              </Kolom>

              <Kolom
                label="Nama Penjual"
                htmlFor="nama-penjual"
                galat={galat?.fieldError('nama_penjual')}
              >
                <Input
                  id="nama-penjual"
                  required
                  value={form.nama_penjual}
                  onChange={(e) => setForm({ ...form, nama_penjual: e.target.value })}
                  galat={galat?.fieldError('nama_penjual')}
                />
              </Kolom>

              <Kolom
                label="WhatsApp Penjual"
                htmlFor="kontak-wa"
                petunjuk="Pastikan pemilik usaha setuju nomornya ditampilkan ke publik."
                galat={galat?.fieldError('kontak_wa')}
              >
                <Input
                  id="kontak-wa"
                  placeholder="08xx atau +62xx"
                  value={form.kontak_wa}
                  onChange={(e) => setForm({ ...form, kontak_wa: e.target.value })}
                  galat={galat?.fieldError('kontak_wa')}
                />
              </Kolom>
            </div>

            {/* Alasannya sama dengan panel Wisata — lihat komentar di sana. */}
            {sunting ? (
              <div>
                <p className="block text-sm font-medium text-slate-900">Foto Produk</p>
                <div className="mt-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                  <PengelolaFoto
                    urlUnggah={`/admin/produk/${sunting.id}/foto`}
                    urlUbah={(idFoto) => `/admin/produk/${sunting.id}/foto/${idFoto}`}
                    urlGeser={(idFoto) => `/admin/produk/${sunting.id}/foto/${idFoto}/geser`}
                    urlHapus={(idFoto) => `/admin/produk/${sunting.id}/foto/${idFoto}`}
                    foto={fotoTersimpan}
                    onBerubah={() =>
                      void queryClient.invalidateQueries({ queryKey: ['admin', 'produk'] })
                    }
                    petunjuk="Foto pertama (bernomor 1) dipakai sebagai gambar utama produk. Pakai panah untuk mengubah urutannya, pensil untuk keterangan, dan silang merah untuk menghapus."
                  />
                </div>
              </div>
            ) : (
              <InputBanyakGambar
                label="Foto Produk"
                berkas={fotoBaru}
                onUbah={setFotoBaru}
                petunjuk="Foto pertama dipakai sebagai gambar utama produk. Setelah produk tersimpan, foto dapat ditambah, diurutkan ulang, atau dihapus lewat tombol Ubah."
                galat={galat?.fieldError('foto')}
              />
            )}

            <div className="flex flex-wrap gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah Produk'}
              </Tombol>

              {sunting && (
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => {
                    setSunting(null)
                    setForm(kosong)
                    setGalat(null)
                  }}
                >
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
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada produk UMKM.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Produk</th>
                    <th scope="col" className="pb-2 font-medium">Harga</th>
                    <th scope="col" className="pb-2 font-medium">Penjual</th>
                    <th scope="col" className="pb-2 font-medium">
                      <span className="sr-only">Aksi</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {data.map((p) => (
                    <Fragment key={p.id}>
                      <tr className="border-b border-slate-100">
                        <td className="py-2.5 pr-4">
                          <div className="flex items-center gap-3">
                            {p.photos?.[0] ? (
                              <img
                                src={urlBerkas(p.photos[0].path) ?? undefined}
                                alt=""
                                className="h-10 w-10 shrink-0 rounded border border-slate-200 object-cover"
                              />
                            ) : (
                              <span
                                aria-hidden="true"
                                className="h-10 w-10 shrink-0 rounded border border-dashed border-slate-300"
                              />
                            )}
                            <div className="min-w-0">
                              <p className="font-medium text-slate-900">{p.nama_produk}</p>
                              <p className="text-xs text-slate-500">
                                {[
                                  p.kategori,
                                  `${p.photos_count} foto`,
                                  !p.tersedia && 'stok habis',
                                  !p.status_tampil && 'disembunyikan',
                                ]
                                  .filter(Boolean)
                                  .join(' · ')}
                              </p>
                            </div>
                          </div>
                        </td>
                        <td className="py-2.5 pr-4 tabular-nums text-slate-700">
                          {p.harga === null ? '—' : FORMAT_RUPIAH.format(Number(p.harga))}
                          {p.satuan && <span className="text-xs text-slate-500"> / {p.satuan}</span>}
                        </td>
                        <td className="py-2.5 pr-4 text-slate-700">
                          {p.nama_penjual}
                          {p.kontak_wa && (
                            <span className="block text-xs text-slate-500">{p.kontak_wa}</span>
                          )}
                        </td>
                        <td className="py-2.5 text-right whitespace-nowrap">
                          <AksiBaris
                            nama={`produk “${p.nama_produk}”`}
                            onSunting={() => {
                              setSunting(p)
                              setForm({
                                nama_produk: p.nama_produk,
                                kategori: p.kategori ?? '',
                                harga: p.harga == null ? '' : String(p.harga),
                                satuan: p.satuan ?? '',
                                nama_penjual: p.nama_penjual,
                                kontak_wa: p.kontak_wa ?? '',
                              })
                              setGalat(null)
                              window.scrollTo({ top: 0, behavior: 'smooth' })
                            }}
                            onHapus={() => hapus.mutate(p.id)}
                            sedangProses={hapus.isPending}
                          />
                        </td>
                      </tr>

                    </Fragment>
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

EkonomiAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="Potensi & Ekonomi">{page}</LayoutAdmin>
