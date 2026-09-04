import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, urlBerkas, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, Pilihan, Tombol } from '@/Components/Admin/Form'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface Anggota {
  id: number
  nama: string
  jabatan: string
  dapil?: string | null
  foto?: string | null
  urutan_tampil: number
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

  const [form, setForm] = useState({
    nama: '',
    jabatan: bpd ? 'Anggota' : '',
    urutan_tampil: '',
    tingkat: '',
  })
  const [foto, setFoto] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: kunciQuery,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Anggota[]>>(`/admin/${jenis}`)
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: (nilai: typeof form) =>
      api.post(
        `/admin/${jenis}`,
        keFormData({
          ...nilai,
          urutan_tampil: nilai.urutan_tampil || 0,
          // Tingkat hanya berlaku untuk bagan aparat; BPD mengabaikannya.
          ...(bpd ? {} : { tingkat: nilai.tingkat || 0 }),
          foto,
        }),
      ),
    onSuccess: () => {
      setForm({ nama: '', jabatan: bpd ? 'Anggota' : '', urutan_tampil: '', tingkat: '' })
      setFoto(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: kunciQuery })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/${jenis}/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: kunciQuery }),
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    tambah.mutate(form)
  }

  return (
    <div className="space-y-6">
      <Kartu
        judul={bpd ? 'Tambah Anggota BPD' : 'Tambah Aparat Desa'}
        anak={
          <form onSubmit={kirim} className="space-y-4">
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-3">
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

              <Kolom
                label="Urutan Tampil"
                htmlFor="urutan_tampil"
                petunjuk="Angka kecil tampil lebih dulu."
              >
                <Input
                  id="urutan_tampil"
                  type="number"
                  min={0}
                  value={form.urutan_tampil}
                  onChange={(e) => setForm({ ...form, urutan_tampil: e.target.value })}
                />
              </Kolom>

              {/* Tingkat menyusun bagan struktur; tidak relevan untuk BPD. */}
              {!bpd && (
                <Kolom
                  label="Tingkat Bagan"
                  htmlFor="tingkat"
                  petunjuk="0 = Kepala Desa, 1 = Sekretaris, 2 = Kaur/Kasi, dst. Tingkat sama = sebaris."
                >
                  <Input
                    id="tingkat"
                    type="number"
                    min={0}
                    value={form.tingkat}
                    onChange={(e) => setForm({ ...form, tingkat: e.target.value })}
                  />
                </Kolom>
              )}
            </div>

            <InputBerkas
              label="Foto"
              jenis="gambar"
              berkas={foto}
              onPilih={setFoto}
              petunjuk="Pas foto tampak depan."
              galat={galat?.fieldError('foto')}
            />

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah'}
            </Tombol>
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
                      {!a.status_aktif && ' · nonaktif'}
                    </p>
                  </div>
                  <button
                    onClick={() => {
                      if (confirm(`Hapus ${a.nama}?`)) hapus.mutate(a.id)
                    }}
                    className="shrink-0 text-sm text-red-600 hover:underline"
                  >
                    Hapus
                  </button>
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
