import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/components/Form'
import { InputBerkas, TautanBerkas } from '@/components/Berkas'

interface Permohonan {
  id: number
  nomor_registrasi: string
  nama_pemohon: string
  kontak: string
  informasi_diminta: string
  tujuan_penggunaan: string | null
  cara_memperoleh: string
  status: 'diajukan' | 'diverifikasi' | 'diproses' | 'selesai' | 'ditolak'
  tanggapan_admin: string | null
  alasan_penolakan: string | null
  dokumen_balasan: string | null
  created_at: string
}

interface Informasi {
  id: number
  jenis: string
  judul: string
  kategori: string | null
  periode: string | null
  file: string | null
  tanggal_publish: string | null
}

interface DasarHukum {
  id: number
  judul_regulasi: string
  nomor_regulasi: string | null
  tahun: number | null
  file_pdf: string | null
}

const STATUS: Permohonan['status'][] = [
  'diajukan',
  'diverifikasi',
  'diproses',
  'selesai',
  'ditolak',
]

const LABEL_STATUS: Record<Permohonan['status'], string> = {
  diajukan: 'Diajukan',
  diverifikasi: 'Diverifikasi',
  diproses: 'Sedang Diproses',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
}

const JENIS_INFORMASI = [
  ['berkala', 'Informasi Berkala'],
  ['serta-merta', 'Informasi Serta-Merta'],
  ['setiap-saat', 'Informasi Setiap Saat'],
] as const

/** CMS PPID — PRD 5.17. */
export function PpidAdminPage() {
  const [tab, setTab] = useState<'permohonan' | 'informasi' | 'dasar-hukum'>('permohonan')

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">PPID</h1>
        <p className="mt-1 text-sm text-slate-600">
          Kelola dokumen informasi publik dan tanggapi permohonan informasi dari warga.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {(
          [
            ['permohonan', 'Permohonan Masuk'],
            ['informasi', 'Dokumen Informasi'],
            ['dasar-hukum', 'Dasar Hukum'],
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

      {tab === 'permohonan' && <DaftarPermohonan />}
      {tab === 'informasi' && <DaftarInformasi />}
      {tab === 'dasar-hukum' && <DaftarDasarHukum />}
    </div>
  )
}

function DaftarPermohonan() {
  const queryClient = useQueryClient()
  const [dibuka, setDibuka] = useState<Permohonan | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'ppid', 'permohonan'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Permohonan[]>>('/admin/ppid/permohonan')
      return r.data.data
    },
  })

  if (dibuka) {
    return (
      <FormTanggapan
        permohonan={dibuka}
        onSelesai={() => {
          setDibuka(null)
          void queryClient.invalidateQueries({ queryKey: ['admin', 'ppid', 'permohonan'] })
        }}
      />
    )
  }

  return (
    <Kartu
      anak={
        isPending ? (
          <p className="text-slate-500">Memuat…</p>
        ) : !data?.length ? (
          <p className="py-8 text-center text-slate-500">
            Belum ada permohonan informasi masuk.
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 text-left text-slate-500">
                  <th scope="col" className="pb-2 font-medium">Nomor Registrasi</th>
                  <th scope="col" className="pb-2 font-medium">Pemohon</th>
                  <th scope="col" className="pb-2 font-medium">Status</th>
                  <th scope="col" className="pb-2 font-medium">
                    <span className="sr-only">Aksi</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {data.map((p) => (
                  <tr key={p.id} className="border-b border-slate-100">
                    <td className="py-2.5 pr-4 font-mono text-xs text-slate-700">
                      {p.nomor_registrasi}
                    </td>
                    <td className="py-2.5 pr-4">
                      <p className="font-medium text-slate-900">{p.nama_pemohon}</p>
                      <p className="max-w-xs truncate text-xs text-slate-500">
                        {p.informasi_diminta}
                      </p>
                    </td>
                    <td className="py-2.5 pr-4">
                      <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                        {LABEL_STATUS[p.status]}
                      </span>
                    </td>
                    <td className="py-2.5 text-right">
                      <button
                        onClick={() => setDibuka(p)}
                        className="text-slate-700 hover:underline"
                      >
                        Tanggapi
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
  )
}

function FormTanggapan({
  permohonan,
  onSelesai,
}: {
  permohonan: Permohonan
  onSelesai: () => void
}) {
  const [status, setStatus] = useState(permohonan.status)
  const [tanggapan, setTanggapan] = useState(permohonan.tanggapan_admin ?? '')
  const [alasan, setAlasan] = useState(permohonan.alasan_penolakan ?? '')
  const [dokumen, setDokumen] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const simpan = useMutation({
    mutationFn: () =>
      // POST + `_method=PUT`: dokumen balasan dikirim sebagai multipart, dan
      // PHP tidak mengurai body multipart pada request PUT.
      api.post(
        `/admin/ppid/permohonan/${permohonan.id}`,
        keFormData(
          {
            status,
            tanggapan_admin: tanggapan || null,
            alasan_penolakan: alasan || null,
            dokumen_balasan: dokumen,
          },
          { method: 'PUT' },
        ),
      ),
    onSuccess: onSelesai,
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate()
  }

  return (
    <form onSubmit={kirim} className="space-y-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="font-semibold text-slate-900">
          Tanggapi {permohonan.nomor_registrasi}
        </h2>
        <Tombol type="button" variasi="sekunder" onClick={onSelesai}>
          Kembali
        </Tombol>
      </div>

      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <Kartu
        judul="Isi Permohonan"
        anak={
          <dl className="space-y-3 text-sm">
            {[
              ['Pemohon', permohonan.nama_pemohon],
              ['Kontak', permohonan.kontak],
              ['Informasi Diminta', permohonan.informasi_diminta],
              ['Tujuan Penggunaan', permohonan.tujuan_penggunaan ?? '—'],
              ['Cara Memperoleh', permohonan.cara_memperoleh],
            ].map(([label, nilai]) => (
              <div key={label}>
                <dt className="text-slate-500">{label}</dt>
                <dd className="text-slate-800">{nilai}</dd>
              </div>
            ))}
          </dl>
        }
      />

      <Kartu
        judul="Tanggapan"
        anak={
          <div className="space-y-4">
            <Kolom label="Status" htmlFor="status">
              <select
                id="status"
                value={status}
                onChange={(e) => setStatus(e.target.value as Permohonan['status'])}
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
              label="Tanggapan untuk Pemohon"
              htmlFor="tanggapan"
              galat={galat?.fieldError('tanggapan_admin')}
            >
              <TextArea
                id="tanggapan"
                rows={4}
                value={tanggapan}
                onChange={(e) => setTanggapan(e.target.value)}
                galat={galat?.fieldError('tanggapan_admin')}
              />
            </Kolom>

            {/* Alasan penolakan wajib menurut UU KIP — pemohon berhak
                mengetahui dasarnya untuk dapat mengajukan keberatan. */}
            {status === 'ditolak' && (
              <Kolom
                label="Alasan Penolakan"
                htmlFor="alasan"
                petunjuk="Wajib diisi sesuai Undang-Undang Keterbukaan Informasi Publik."
                galat={galat?.fieldError('alasan_penolakan')}
              >
                <TextArea
                  id="alasan"
                  rows={3}
                  required
                  value={alasan}
                  onChange={(e) => setAlasan(e.target.value)}
                  galat={galat?.fieldError('alasan_penolakan')}
                />
              </Kolom>
            )}

            <InputBerkas
              label="Dokumen Balasan"
              jenis="dokumen"
              berkas={dokumen}
              onPilih={setDokumen}
              pathTersimpan={permohonan.dokumen_balasan}
              petunjuk="Salinan informasi yang diminta pemohon."
              galat={galat?.fieldError('dokumen_balasan')}
            />
          </div>
        }
      />

      <Tombol type="submit" disabled={simpan.isPending}>
        {simpan.isPending ? 'Menyimpan…' : 'Simpan Tanggapan'}
      </Tombol>
    </form>
  )
}

function DaftarInformasi() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    jenis: 'berkala',
    judul: '',
    kategori: '',
    periode: '',
  })
  const [berkas, setBerkas] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'ppid', 'informasi'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Informasi[]>>('/admin/ppid/informasi')
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: () => api.post('/admin/ppid/informasi', keFormData({ ...form, file: berkas })),
    onSuccess: () => {
      setForm({ jenis: form.jenis, judul: '', kategori: '', periode: '' })
      setBerkas(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'ppid', 'informasi'] })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/ppid/informasi/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'ppid', 'informasi'] }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah Dokumen Informasi"
        anak={
          <form
            onSubmit={(e) => {
              e.preventDefault()
              tambah.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Jenis Informasi" htmlFor="jenis">
                <select
                  id="jenis"
                  value={form.jenis}
                  onChange={(e) => setForm({ ...form, jenis: e.target.value })}
                  className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900"
                >
                  {JENIS_INFORMASI.map(([nilai, label]) => (
                    <option key={nilai} value={nilai}>
                      {label}
                    </option>
                  ))}
                </select>
              </Kolom>

              <Kolom label="Judul Dokumen" htmlFor="judul" galat={galat?.fieldError('judul')}>
                <Input
                  id="judul"
                  required
                  value={form.judul}
                  onChange={(e) => setForm({ ...form, judul: e.target.value })}
                  galat={galat?.fieldError('judul')}
                />
              </Kolom>

              <Kolom label="Kategori" htmlFor="kategori">
                <Input
                  id="kategori"
                  placeholder="mis. Laporan Keuangan"
                  value={form.kategori}
                  onChange={(e) => setForm({ ...form, kategori: e.target.value })}
                />
              </Kolom>

              <Kolom label="Periode" htmlFor="periode">
                <Input
                  id="periode"
                  placeholder="mis. Semester I 2026"
                  value={form.periode}
                  onChange={(e) => setForm({ ...form, periode: e.target.value })}
                />
              </Kolom>
            </div>

            <InputBerkas
              label="Berkas Dokumen"
              jenis="dokumen"
              berkas={berkas}
              onPilih={setBerkas}
              petunjuk="Dokumen yang dapat diunduh warga dari halaman PPID."
              galat={galat?.fieldError('file')}
            />

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah Dokumen'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">
              Belum ada dokumen informasi publik.
            </p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((i) => (
                <li key={i.id} className="flex items-center justify-between gap-4 py-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-slate-900">{i.judul}</p>
                    <p className="truncate text-sm text-slate-500">
                      {[i.jenis, i.kategori, i.periode].filter(Boolean).join(' · ')}
                    </p>
                    <TautanBerkas path={i.file} />
                  </div>
                  <button
                    onClick={() => {
                      if (confirm(`Hapus dokumen "${i.judul}"?`)) hapus.mutate(i.id)
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

/**
 * Dasar hukum PPID — daftar regulasi yang melandasi layanan informasi publik.
 *
 * UU Keterbukaan Informasi Publik mewajibkan badan publik mencantumkan dasar
 * hukum layanannya, sehingga daftar ini bukan pelengkap melainkan syarat.
 */
function DaftarDasarHukum() {
  const queryClient = useQueryClient()
  const kunciQuery = ['admin', 'ppid', 'dasar-hukum']

  const [form, setForm] = useState({ judul_regulasi: '', nomor_regulasi: '', tahun: '' })
  const [berkas, setBerkas] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data, isPending } = useQuery({
    queryKey: kunciQuery,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<DasarHukum[]>>('/admin/ppid/dasar-hukum')
      return r.data.data
    },
  })

  const tambah = useMutation({
    mutationFn: () =>
      api.post(
        '/admin/ppid/dasar-hukum',
        keFormData({
          ...form,
          // Tahun kosong dikirim null; string kosong ditolak aturan `integer`.
          tahun: form.tahun || null,
          file_pdf: berkas,
        }),
      ),
    onSuccess: () => {
      setForm({ judul_regulasi: '', nomor_regulasi: '', tahun: '' })
      setBerkas(null)
      setGalat(null)
      void queryClient.invalidateQueries({ queryKey: kunciQuery })
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/ppid/dasar-hukum/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: kunciQuery }),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah Dasar Hukum"
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
              <div className="sm:col-span-2">
                <Kolom
                  label="Judul Regulasi"
                  htmlFor="judul_regulasi"
                  galat={galat?.fieldError('judul_regulasi')}
                >
                  <Input
                    id="judul_regulasi"
                    required
                    placeholder="mis. Peraturan Desa tentang Keterbukaan Informasi Publik"
                    value={form.judul_regulasi}
                    onChange={(e) => setForm({ ...form, judul_regulasi: e.target.value })}
                    galat={galat?.fieldError('judul_regulasi')}
                  />
                </Kolom>
              </div>

              <Kolom label="Nomor Regulasi" htmlFor="nomor_regulasi">
                <Input
                  id="nomor_regulasi"
                  placeholder="mis. 3 Tahun 2026"
                  value={form.nomor_regulasi}
                  onChange={(e) => setForm({ ...form, nomor_regulasi: e.target.value })}
                />
              </Kolom>

              <Kolom label="Tahun" htmlFor="tahun" galat={galat?.fieldError('tahun')}>
                <Input
                  id="tahun"
                  type="number"
                  min={1945}
                  max={2100}
                  value={form.tahun}
                  onChange={(e) => setForm({ ...form, tahun: e.target.value })}
                  galat={galat?.fieldError('tahun')}
                />
              </Kolom>
            </div>

            <InputBerkas
              label="Salinan Regulasi"
              jenis="dokumen"
              berkas={berkas}
              onPilih={setBerkas}
              petunjuk="Dapat diunduh warga dari halaman PPID."
              galat={galat?.fieldError('file_pdf')}
            />

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah Dasar Hukum'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Belum ada dasar hukum.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {data.map((d) => (
                <li key={d.id} className="flex items-center justify-between gap-4 py-3">
                  <div className="min-w-0">
                    <p className="truncate font-medium text-slate-900">{d.judul_regulasi}</p>
                    <p className="truncate text-sm text-slate-500">
                      {[d.nomor_regulasi, d.tahun].filter(Boolean).join(' · ') || '—'}
                    </p>
                    <TautanBerkas path={d.file_pdf} />
                  </div>
                  <button
                    onClick={() => {
                      if (confirm(`Hapus dasar hukum "${d.judul_regulasi}"?`)) hapus.mutate(d.id)
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
