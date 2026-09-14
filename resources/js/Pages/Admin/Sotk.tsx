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
  Pilihan,
  Tombol,
  useGulirKeForm,
} from '@/Components/Admin/Form'
import { tampilkanToast } from '@/Components/Admin/Toast'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface Anggota {
  id: number
  nama: string
  jabatan: string
  /** Daerah pemilihan — hanya pada anggota BPD. */
  dapil?: string | null
  foto?: string | null
  status_aktif: boolean
}

const JABATAN_BPD = ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Anggota']

/**
 * CMS SOTK & BPD — PRD 5.4.
 *
 * Dua entitas terpisah ditampilkan sebagai dua tab pada satu halaman, sejalan
 * dengan PRD 3.2 yang menegaskan keduanya lembaga berbeda namun dikelola
 * berdampingan.
 */
export default function SotkPage() {
  const [tab, setTab] = useState<'officials' | 'bpd-members'>('officials')

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">SOTK &amp; BPD</h1>
        <p className="mt-1 text-sm text-slate-600">
          Pemerintah Desa dan BPD adalah dua lembaga terpisah, masing-masing tampil
          sebagai bagan tersendiri di halaman Profil.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {([
          ['officials', 'Aparat Pemerintah Desa'],
          ['bpd-members', 'Anggota BPD'],
        ] as const).map(([kunci, label]) => (
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

      {/* key memaksa remount saat tab berganti, sehingga state formulir tidak
          terbawa dari entitas sebelumnya. */}
      <DaftarAnggota key={tab} jenis={tab} />
    </div>
  )
}

function DaftarAnggota({ jenis }: { jenis: 'officials' | 'bpd-members' }) {
  const queryClient = useQueryClient()
  const kunciQuery = ['admin', jenis]
  const bpd = jenis === 'bpd-members'

  const kosong = { nama: '', jabatan: bpd ? 'Anggota' : '', dapil: '' }

  const [form, setForm] = useState(kosong)
  const [foto, setFoto] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  // Baris yang sedang disunting; null berarti formulir sedang menambah baru.
  const [sunting, setSunting] = useState<Anggota | null>(null)

  const { data, isPending } = useQuery({
    queryKey: kunciQuery,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Anggota[]>>(`/admin/${jenis}`)
      return r.data.data
    },
  })

  const simpan = useMutation({
    mutationFn: (nilai: typeof form) => {
      const isi = {
        ...nilai,
        // Daerah pemilihan hanya dimiliki anggota BPD; mengirimnya pada aparat
        // desa berarti menulis kolom yang tidak ada pada tabelnya.
        ...(bpd ? { dapil: nilai.dapil || null } : { dapil: undefined }),
        foto,
      }

      // Pembaruan dikirim POST + `_method=PUT`: foto dikirim multipart, dan
      // PHP tidak mengurai body multipart pada request PUT.
      return sunting
        ? api.post(`/admin/${jenis}/${sunting.id}`, keFormData(isi, { method: 'PUT' }))
        : api.post(`/admin/${jenis}`, keFormData(isi))
    },
    onSuccess: () => {
      tampilkanToast(sunting ? 'Perubahan tersimpan.' : `${bpd ? 'Anggota BPD' : 'Aparat desa'} ditambahkan.`)
      batalSunting()
      void queryClient.invalidateQueries({ queryKey: kunciQuery })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/${jenis}/${id}`),
    onSuccess: () => {
      tampilkanToast(`${bpd ? 'Anggota BPD' : 'Aparat desa'} dihapus.`)
      void queryClient.invalidateQueries({ queryKey: kunciQuery })
    },
    onError: () => tampilkanToast('Data gagal dihapus. Coba lagi.', 'galat'),
  })

  const formulir = useGulirKeForm()

  function mulaiSunting(a: Anggota) {
    setSunting(a)
    setForm({
      nama: a.nama,
      jabatan: a.jabatan,
      dapil: a.dapil ?? '',
    })
    // Foto dikosongkan, bukan diisi berkas lama: memilih berkas baru berarti
    // mengganti, membiarkannya kosong berarti mempertahankan yang tersimpan.
    setFoto(null)
    setGalat(null)
    formulir.gulir()
  }

  function batalSunting() {
    setSunting(null)
    setForm(kosong)
    setFoto(null)
    setGalat(null)
  }

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate(form)
  }

  return (
    <div className="space-y-6">
      <Kartu
        judul={
          sunting
            ? `Ubah ${sunting.nama}`
            : bpd
              ? 'Tambah Anggota BPD'
              : 'Tambah Aparat Desa'
        }
        wadahRef={formulir.ref}
        anak={
          <form onSubmit={kirim} className="space-y-4">
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className={`grid gap-4 ${bpd ? 'sm:grid-cols-3' : 'sm:grid-cols-2'}`}>
              <Kolom label="Nama" htmlFor="nama" galat={galat?.fieldError('nama')}>
                <Input
                  id="nama"
                  required
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  galat={galat?.fieldError('nama')}
                />
              </Kolom>

              <Kolom label="Jabatan" htmlFor="jabatan" galat={galat?.fieldError('jabatan')}>
                {bpd ? (
                  <Pilihan
                    id="jabatan"
                    value={form.jabatan}
                    onChange={(v) => setForm({ ...form, jabatan: v })}
                    options={JABATAN_BPD.map((j) => ({ value: j, label: j }))}
                  />
                ) : (
                  <Input
                    id="jabatan"
                    required
                    placeholder="mis. Kepala Desa, Kaur Keuangan"
                    value={form.jabatan}
                    onChange={(e) => setForm({ ...form, jabatan: e.target.value })}
                    galat={galat?.fieldError('jabatan')}
                  />
                )}
              </Kolom>

              {/*
                Daerah pemilihan hanya dimiliki anggota BPD — merekalah yang
                dipilih mewakili wilayah tertentu; aparat desa diangkat.
              */}
              {bpd && (
                <Kolom
                  label="Daerah Pemilihan"
                  htmlFor="dapil"
                  petunjuk="Opsional. Tampil di bawah jabatan pada halaman publik."
                  galat={galat?.fieldError('dapil')}
                >
                  <Input
                    id="dapil"
                    placeholder="mis. Dusun 1"
                    value={form.dapil}
                    onChange={(e) => setForm({ ...form, dapil: e.target.value })}
                    galat={galat?.fieldError('dapil')}
                  />
                </Kolom>
              )}

            </div>

            {/*
              "Urutan Tampil" dan "Tingkat Bagan" dulu berdiri di sini.

              Keduanya dihapus atas permintaan pemilik produk: mengisi dua angka
              demi menyusun beberapa nama adalah pekerjaan yang seharusnya tidak
              pernah ada, dan satu angka yang terlewat membuat Kepala Desa
              tampil di tengah daftar. Urutannya kini disimpulkan server dari
              jenjang jabatan (docs/DEVIASI.md §C22).
            */}
            <p className="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
              Urutan tampil di situs publik mengikuti jenjang jabatan secara otomatis
              {bpd
                ? ' — Ketua, Wakil Ketua, Sekretaris, lalu Anggota.'
                : ' — Kepala Desa, Sekretaris Desa, Kaur/Kasi, lalu Kepala Dusun.'}{' '}
              Jabatan setara diurutkan menurut abjad nama.
            </p>

            <InputBerkas
              label="Foto"
              jenis="gambar"
              berkas={foto}
              onPilih={setFoto}
              pathTersimpan={sunting?.foto ?? undefined}
              petunjuk={
                sunting
                  ? 'Biarkan kosong bila foto tidak diganti.'
                  : 'Pas foto tampak depan.'
              }
              galat={galat?.fieldError('foto')}
            />

            <div className="flex flex-wrap gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah'}
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
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada data.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((a) => (
                <li key={a.id} className="flex items-center justify-between gap-4 py-3">
                  {a.foto ? (
                    <img
                      src={urlBerkas(a.foto) ?? undefined}
                      alt={`Foto ${a.nama}`}
                      className="h-10 w-10 shrink-0 rounded-full border border-slate-200 object-cover"
                    />
                  ) : (
                    <span
                      aria-hidden="true"
                      className="h-10 w-10 shrink-0 rounded-full border border-dashed border-slate-300"
                    />
                  )}

                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium text-slate-900">{a.nama}</p>
                    <p className="truncate text-sm text-slate-500">
                      {a.jabatan}
                      {a.dapil && ` · ${a.dapil}`}
                      {!a.status_aktif && ' · nonaktif'}
                    </p>
                  </div>
                  <AksiBaris
                    nama={a.nama}
                    onSunting={() => mulaiSunting(a)}
                    onHapus={() => hapus.mutate(a.id)}
                    sedangProses={hapus.isPending}
                  />
                </li>
              ))}
            </ul>
          )
        }
      />
    </div>
  )
}

SotkPage.layout = (page: ReactNode) => <LayoutAdmin judul="SOTK & BPD">{page}</LayoutAdmin>
