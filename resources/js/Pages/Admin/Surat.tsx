import { useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileSignature, Inbox, MapPinned, RefreshCw, Send, Signature } from 'lucide-react'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Input, Kartu, Kolom, Pemberitahuan, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'

// ---------------------------------------------------------------------------
// Tipe
// ---------------------------------------------------------------------------

interface Rt {
  id: number
  nomor: string
  dusun_id: number | null
  dusun: string | null
  urutan_tampil: number
}

interface Dusun {
  id: number
  nama: string
  nama_kepala_dusun: string | null
}

interface Pejabat {
  id: number
  role: 'KETUA_RT' | 'KEPALA_DUSUN'
  nama: string
  jabatan_teks: string | null
  rt_id: number | null
  rt: string | null
  dusun_id: number | null
  dusun: string | null
  is_active: boolean
  punya_telegram: boolean
  punya_ttd: boolean
}

interface Pengajuan {
  id: number
  ticket_number: string
  nama: string
  rt: string | null
  dusun: string | null
  status: 'MENUNGGU_APPROVAL_RT' | 'MENUNGGU_APPROVAL_KADUS' | 'DISETUJUI' | 'DITOLAK'
  nomor_surat: string | null
  pdf_tersedia: boolean
  tanggal_pengajuan: string
}

const LABEL_STATUS: Record<Pengajuan['status'], string> = {
  MENUNGGU_APPROVAL_RT: 'Menunggu RT',
  MENUNGGU_APPROVAL_KADUS: 'Menunggu Kadus',
  DISETUJUI: 'Disetujui',
  DITOLAK: 'Ditolak',
}

const GAYA_STATUS: Record<Pengajuan['status'], string> = {
  MENUNGGU_APPROVAL_RT: 'bg-amber-100 text-amber-800',
  MENUNGGU_APPROVAL_KADUS: 'bg-amber-100 text-amber-800',
  DISETUJUI: 'bg-green-100 text-green-800',
  DITOLAK: 'bg-red-100 text-red-700',
}

const TAB = [
  ['pengajuan', 'Pengajuan Masuk'],
  ['pejabat', 'Pejabat Penanda Tangan'],
  ['rt', 'Master RT'],
] as const

/**
 * CMS Surat Pengantar.
 *
 * Layar inilah yang membuat aturan "jangan menulis 21 RT, chat ID, dan tanda
 * tangan di dalam kode" dapat ditegakkan — seluruhnya dikelola dari sini.
 *
 * Tiga tab yang sengaja dipisah karena kewenangannya memang berbeda: memantau
 * antrean surat tidak menuntut hak menyentuh chat ID Telegram maupun tanda
 * tangan pejabat, dan backend memberlakukan pemisahan itu lewat dua permission
 * terpisah (lihat routes/api.php).
 */
export default function SuratPage() {
  const [tab, setTab] = useState<(typeof TAB)[number][0]>('pengajuan')

  return (
    <div className="max-w-5xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Surat Pengantar</h1>
        <p className="mt-1 text-sm text-slate-600">
          Pantau pengajuan warga, serta kelola RT, pejabat penanda tangan, Telegram
          Chat ID, dan gambar tanda tangannya.
        </p>
      </div>

      <div role="tablist" className="flex gap-1 border-b border-slate-200">
        {TAB.map(([kunci, label]) => (
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

      {tab === 'pengajuan' && <TabPengajuan />}
      {tab === 'pejabat' && <TabPejabat />}
      {tab === 'rt' && <TabRt />}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Tab: pengajuan masuk
// ---------------------------------------------------------------------------

function TabPengajuan() {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState('')

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'surat', 'pengajuan', status],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Pengajuan[]>>('/admin/surat/pengajuan', {
        params: status ? { status } : {},
      })
      return r.data.data
    },
  })

  // Kegagalan render PDF tidak pernah membatalkan pengajuan (lihat
  // SuratPengantarService), sehingga harus ada jalan memperbaikinya
  // belakangan tanpa meminta warga mengajukan ulang.
  const buatUlang = useMutation({
    mutationFn: (id: number) => api.post(`/admin/surat/pengajuan/${id}/pdf`),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['admin', 'surat', 'pengajuan'] }),
  })

  return (
    <Kartu
      judul="Pengajuan Masuk"
      ikon={Inbox}
      anak={
        <div className="space-y-4">
          <div className="flex flex-wrap items-center gap-3">
            <label htmlFor="filter-status" className="text-sm text-slate-600">
              Status
            </label>
            <select
              id="filter-status"
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="rounded-lg border border-slate-200 px-3 py-2 text-sm"
            >
              <option value="">Semua</option>
              {Object.entries(LABEL_STATUS).map(([k, v]) => (
                <option key={k} value={k}>
                  {v}
                </option>
              ))}
            </select>
          </div>

          {isPending && <p className="text-sm text-slate-500">Memuat…</p>}

          {data?.length === 0 && (
            <p className="text-sm text-slate-500">Belum ada pengajuan.</p>
          )}

          {data && data.length > 0 && (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[720px] text-sm">
                <thead className="border-b border-slate-200 text-left text-xs text-slate-500 uppercase">
                  <tr>
                    <th className="py-2 pr-3">Nomor Tiket</th>
                    <th className="py-2 pr-3">Pemohon</th>
                    <th className="py-2 pr-3">RT / Dusun</th>
                    <th className="py-2 pr-3">Status</th>
                    <th className="py-2 pr-3">No. Surat</th>
                    <th className="py-2">Berkas</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {data.map((p) => (
                    <tr key={p.id}>
                      <td className="py-2.5 pr-3 font-mono text-xs">{p.ticket_number}</td>
                      <td className="py-2.5 pr-3">{p.nama}</td>
                      <td className="py-2.5 pr-3 text-slate-600">
                        RT {p.rt ?? '—'} · {p.dusun ?? '—'}
                      </td>
                      <td className="py-2.5 pr-3">
                        <span
                          className={`rounded-full px-2 py-0.5 text-xs font-medium ${GAYA_STATUS[p.status]}`}
                        >
                          {LABEL_STATUS[p.status]}
                        </span>
                      </td>
                      <td className="py-2.5 pr-3 font-mono text-xs text-slate-600">
                        {p.nomor_surat ?? '—'}
                      </td>
                      <td className="py-2.5">
                        {p.pdf_tersedia ? (
                          <a
                            href={`/api/v1/admin/surat/pengajuan/${p.id}/unduh`}
                            className="text-teal-700 hover:underline"
                          >
                            Unduh
                          </a>
                        ) : p.status === 'DITOLAK' ? (
                          <span className="text-xs text-slate-400">—</span>
                        ) : (
                          <button
                            onClick={() => buatUlang.mutate(p.id)}
                            disabled={buatUlang.isPending}
                            className="inline-flex items-center gap-1 text-xs text-amber-700 hover:underline"
                          >
                            <RefreshCw className="size-3" aria-hidden="true" />
                            Buat ulang PDF
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      }
    />
  )
}

// ---------------------------------------------------------------------------
// Tab: pejabat penanda tangan
// ---------------------------------------------------------------------------

const FORM_PEJABAT_KOSONG = {
  role: 'KETUA_RT' as Pejabat['role'],
  nama: '',
  jabatan_teks: '',
  rt_id: '',
  dusun_id: '',
  telegram_chat_id: '',
  is_active: true,
}

function TabPejabat() {
  const queryClient = useQueryClient()
  const kunci = ['admin', 'surat', 'pejabat']

  const [form, setForm] = useState(FORM_PEJABAT_KOSONG)
  const [ttd, setTtd] = useState<File | null>(null)
  const [sunting, setSunting] = useState<number | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sukses, setSukses] = useState<string | null>(null)

  const { data: pejabat, isPending } = useQuery({
    queryKey: kunci,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Pejabat[]>>('/admin/surat/pejabat')
      return r.data.data
    },
  })

  const { data: daftarRt } = useQuery({
    queryKey: ['admin', 'surat', 'rt'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Rt[]>>('/admin/surat/rt')
      return r.data.data
    },
  })

  const { data: daftarDusun } = useQuery({
    queryKey: ['admin', 'surat', 'dusun'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Dusun[]>>('/admin/surat/dusun')
      return r.data.data
    },
  })

  function bereskan(pesan: string) {
    setForm(FORM_PEJABAT_KOSONG)
    setTtd(null)
    setSunting(null)
    setGalat(null)
    setSukses(pesan)
    void queryClient.invalidateQueries({ queryKey: kunci })
  }

  const simpan = useMutation({
    mutationFn: () => {
      const muatan = keFormData(
        {
          ...form,
          // Kolom wilayah yang tidak relevan tidak dikirim sama sekali;
          // server juga menormalkannya, tetapi mengirim keduanya membuat
          // pesan validasi `required_if` sulit dibaca operator.
          rt_id: form.role === 'KETUA_RT' ? form.rt_id : null,
          dusun_id: form.role === 'KEPALA_DUSUN' ? form.dusun_id : null,
          tanda_tangan: ttd,
        },
        // Unggahan multipart tidak terbaca PHP pada permintaan PUT, jadi
        // perubahan dikirim sebagai POST ke alamat yang sama.
        {},
      )

      return sunting
        ? api.post(`/admin/surat/pejabat/${sunting}`, muatan)
        : api.post('/admin/surat/pejabat', muatan)
    },
    onSuccess: () => bereskan(sunting ? 'Pejabat diperbarui.' : 'Pejabat ditambahkan.'),
    onError: (e) => {
      setSukses(null)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/surat/pejabat/${id}`),
    onSuccess: () => bereskan('Pejabat dihapus atau dinonaktifkan.'),
  })

  function mulaiSunting(p: Pejabat) {
    setSunting(p.id)
    setGalat(null)
    setSukses(null)
    setForm({
      role: p.role,
      nama: p.nama,
      jabatan_teks: p.jabatan_teks ?? '',
      rt_id: p.rt_id ? String(p.rt_id) : '',
      dusun_id: p.dusun_id ? String(p.dusun_id) : '',
      // Chat ID TIDAK pernah dikirim balik dari server (lihat
      // daftarPejabat), jadi kolomnya selalu mulai kosong. Dibiarkan kosong
      // saat menyimpan berarti chat ID lama dihapus — karena itu kolomnya
      // diberi keterangan tegas di formulir.
      telegram_chat_id: '',
      is_active: p.is_active,
    })
    setTtd(null)
  }

  const perluRt = form.role === 'KETUA_RT'

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? 'Ubah Pejabat' : 'Tambah Pejabat'}
        ikon={Signature}
        anak={
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              simpan.mutate()
            }}
            className="space-y-4"
          >
            {sukses && <Pemberitahuan jenis="sukses" pesan={sukses} />}
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Jabatan" htmlFor="role" galat={galat?.fieldError('role')}>
                <select
                  id="role"
                  value={form.role}
                  onChange={(e) =>
                    setForm({ ...form, role: e.target.value as Pejabat['role'] })
                  }
                  className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                >
                  <option value="KETUA_RT">Ketua RT</option>
                  <option value="KEPALA_DUSUN">Kepala Dusun</option>
                </select>
              </Kolom>

              {perluRt ? (
                <Kolom label="RT" htmlFor="rt_id" galat={galat?.fieldError('rt_id')}>
                  <select
                    id="rt_id"
                    required
                    value={form.rt_id}
                    onChange={(e) => setForm({ ...form, rt_id: e.target.value })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                  >
                    <option value="">Pilih RT…</option>
                    {daftarRt?.map((rt) => (
                      <option key={rt.id} value={rt.id}>
                        RT {rt.nomor} {rt.dusun ? `— ${rt.dusun}` : ''}
                      </option>
                    ))}
                  </select>
                </Kolom>
              ) : (
                <Kolom label="Dusun" htmlFor="dusun_id" galat={galat?.fieldError('dusun_id')}>
                  <select
                    id="dusun_id"
                    required
                    value={form.dusun_id}
                    onChange={(e) => setForm({ ...form, dusun_id: e.target.value })}
                    className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                  >
                    <option value="">Pilih dusun…</option>
                    {daftarDusun?.map((d) => (
                      <option key={d.id} value={d.id}>
                        {d.nama}
                      </option>
                    ))}
                  </select>
                </Kolom>
              )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <Kolom label="Nama Pejabat" htmlFor="nama" galat={galat?.fieldError('nama')}>
                <Input
                  id="nama"
                  required
                  value={form.nama}
                  onChange={(e) => setForm({ ...form, nama: e.target.value })}
                  galat={galat?.fieldError('nama')}
                />
              </Kolom>

              <Kolom
                label="Sebutan pada Surat"
                htmlFor="jabatan_teks"
                petunjuk="Yang tercetak di blok tanda tangan. Kosongkan untuk memakai bawaan."
                galat={galat?.fieldError('jabatan_teks')}
              >
                <Input
                  id="jabatan_teks"
                  value={form.jabatan_teks}
                  onChange={(e) => setForm({ ...form, jabatan_teks: e.target.value })}
                  galat={galat?.fieldError('jabatan_teks')}
                />
              </Kolom>
            </div>

            <Kolom
              label="Telegram Chat ID"
              htmlFor="telegram_chat_id"
              petunjuk={
                sunting
                  ? 'Demi keamanan, chat ID tersimpan tidak ditampilkan kembali. Isi hanya bila ingin MENGGANTINYA; dikosongkan berarti chat ID lama dihapus.'
                  : 'Angka saja. Pejabat mendapatkannya dengan mengirim /start ke bot desa, atau lewat @userinfobot.'
              }
              galat={galat?.fieldError('telegram_chat_id')}
            >
              <Input
                id="telegram_chat_id"
                inputMode="numeric"
                placeholder="mis. 123456789"
                value={form.telegram_chat_id}
                onChange={(e) => setForm({ ...form, telegram_chat_id: e.target.value })}
                galat={galat?.fieldError('telegram_chat_id')}
              />
            </Kolom>

            <Kolom
              label="Gambar Tanda Tangan"
              htmlFor="tanda_tangan"
              petunjuk="PNG berlatar transparan paling rapi. Maksimal 2 MB. Hanya dibubuhkan pada surat yang sudah disetujui."
              galat={galat?.fieldError('tanda_tangan')}
            >
              <input
                id="tanda_tangan"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={(e) => setTtd(e.target.files?.[0] ?? null)}
                className="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-medium"
              />
            </Kolom>

            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                className="rounded border-slate-300"
              />
              Aktif — hanya pejabat aktif yang menerima notifikasi dan boleh menyetujui
            </label>

            <div className="flex gap-2">
              <Tombol type="submit" disabled={simpan.isPending}>
                {simpan.isPending ? 'Menyimpan…' : sunting ? 'Simpan Perubahan' : 'Tambah'}
              </Tombol>

              {sunting && (
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => {
                    setSunting(null)
                    setForm(FORM_PEJABAT_KOSONG)
                    setTtd(null)
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
        judul="Daftar Pejabat"
        ikon={FileSignature}
        anak={
          isPending ? (
            <p className="text-sm text-slate-500">Memuat…</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full min-w-[680px] text-sm">
                <thead className="border-b border-slate-200 text-left text-xs text-slate-500 uppercase">
                  <tr>
                    <th className="py-2 pr-3">Jabatan</th>
                    <th className="py-2 pr-3">Wilayah</th>
                    <th className="py-2 pr-3">Nama</th>
                    <th className="py-2 pr-3">Telegram</th>
                    <th className="py-2 pr-3">TTD</th>
                    <th className="py-2 pr-3">Status</th>
                    <th className="py-2">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {pejabat?.map((p) => (
                    <tr key={p.id}>
                      <td className="py-2.5 pr-3">
                        {p.role === 'KETUA_RT' ? 'Ketua RT' : 'Kepala Dusun'}
                      </td>
                      <td className="py-2.5 pr-3 text-slate-600">
                        {p.role === 'KETUA_RT' ? `RT ${p.rt ?? '—'}` : (p.dusun ?? '—')}
                      </td>
                      <td className="py-2.5 pr-3">{p.nama}</td>
                      <td className="py-2.5 pr-3">
                        <Penanda ada={p.punya_telegram} kosong="Belum diisi" />
                      </td>
                      <td className="py-2.5 pr-3">
                        <Penanda ada={p.punya_ttd} kosong="Belum ada" />
                      </td>
                      <td className="py-2.5 pr-3">
                        <span
                          className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                            p.is_active
                              ? 'bg-green-100 text-green-800'
                              : 'bg-slate-100 text-slate-500'
                          }`}
                        >
                          {p.is_active ? 'Aktif' : 'Nonaktif'}
                        </span>
                      </td>
                      <td className="py-2.5">
                        <div className="flex gap-2">
                          <button
                            onClick={() => mulaiSunting(p)}
                            className="text-xs text-teal-700 hover:underline"
                          >
                            Ubah
                          </button>
                          <button
                            onClick={() => {
                              if (window.confirm(`Hapus/nonaktifkan ${p.nama}?`)) {
                                hapus.mutate(p.id)
                              }
                            }}
                            className="text-xs text-red-600 hover:underline"
                          >
                            Hapus
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <p className="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Pejabat tanpa Telegram Chat ID tetap tercatat, namun tidak akan menerima
                notifikasi — pengajuan warga akan tertahan pada tahapnya. Isi chat ID
                sebelum mengaktifkan pejabat.
              </p>
            </div>
          )
        }
      />
    </div>
  )
}

function Penanda({ ada, kosong }: { ada: boolean; kosong: string }) {
  return ada ? (
    <span className="text-xs font-medium text-green-700">Terisi</span>
  ) : (
    <span className="text-xs text-amber-700">{kosong}</span>
  )
}

// ---------------------------------------------------------------------------
// Tab: master RT
// ---------------------------------------------------------------------------

function TabRt() {
  const queryClient = useQueryClient()
  const kunci = ['admin', 'surat', 'rt']

  const [form, setForm] = useState({ nomor: '', dusun_id: '', urutan_tampil: '' })
  const [galat, setGalat] = useState<ApiRequestError | null>(null)

  const { data: daftarRt, isPending } = useQuery({
    queryKey: kunci,
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Rt[]>>('/admin/surat/rt')
      return r.data.data
    },
  })

  const { data: daftarDusun } = useQuery({
    queryKey: ['admin', 'surat', 'dusun'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Dusun[]>>('/admin/surat/dusun')
      return r.data.data
    },
  })

  const segarkan = () => queryClient.invalidateQueries({ queryKey: kunci })

  const tambah = useMutation({
    mutationFn: () =>
      api.post('/admin/surat/rt', {
        ...form,
        dusun_id: form.dusun_id || null,
        urutan_tampil: form.urutan_tampil || 0,
      }),
    onSuccess: () => {
      setForm({ nomor: '', dusun_id: '', urutan_tampil: '' })
      setGalat(null)
      void segarkan()
    },
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  // Pemetaan RT ke dusun menentukan Kepala Dusun mana yang menerima
  // permohonan, sehingga ia harus dapat dikoreksi langsung dari baris tabel —
  // bukan lewat formulir terpisah yang mudah terlewat.
  const ubahDusun = useMutation({
    mutationFn: ({ rt, dusunId }: { rt: Rt; dusunId: string }) =>
      api.put(`/admin/surat/rt/${rt.id}`, {
        nomor: rt.nomor,
        dusun_id: dusunId || null,
        urutan_tampil: rt.urutan_tampil,
      }),
    onSuccess: () => segarkan(),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/surat/rt/${id}`),
    onSuccess: () => segarkan(),
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menghapus.', 0)),
  })

  return (
    <div className="space-y-6">
      <Kartu
        judul="Tambah RT"
        ikon={MapPinned}
        anak={
          <form
            onSubmit={(e: FormEvent) => {
              e.preventDefault()
              tambah.mutate()
            }}
            className="space-y-4"
          >
            {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

            <div className="grid gap-4 sm:grid-cols-3">
              <Kolom
                label="Nomor RT"
                htmlFor="nomor"
                petunjuk="Tertulis apa adanya pada surat, mis. 01 atau 20."
                galat={galat?.fieldError('nomor')}
              >
                <Input
                  id="nomor"
                  required
                  value={form.nomor}
                  onChange={(e) => setForm({ ...form, nomor: e.target.value })}
                  galat={galat?.fieldError('nomor')}
                />
              </Kolom>

              <Kolom label="Dusun" htmlFor="dusun" galat={galat?.fieldError('dusun_id')}>
                <select
                  id="dusun"
                  value={form.dusun_id}
                  onChange={(e) => setForm({ ...form, dusun_id: e.target.value })}
                  className="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm"
                >
                  <option value="">— belum ditentukan —</option>
                  {daftarDusun?.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.nama}
                    </option>
                  ))}
                </select>
              </Kolom>

              <Kolom label="Urutan" htmlFor="urutan_tampil">
                <Input
                  id="urutan_tampil"
                  inputMode="numeric"
                  value={form.urutan_tampil}
                  onChange={(e) => setForm({ ...form, urutan_tampil: e.target.value })}
                />
              </Kolom>
            </div>

            <Tombol type="submit" disabled={tambah.isPending}>
              {tambah.isPending ? 'Menyimpan…' : 'Tambah RT'}
            </Tombol>
          </form>
        }
      />

      <Kartu
        judul={`Daftar RT${daftarRt ? ` (${daftarRt.length})` : ''}`}
        ikon={Send}
        anak={
          isPending ? (
            <p className="text-sm text-slate-500">Memuat…</p>
          ) : (
            <div className="space-y-3">
              <p className="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Pemetaan RT ke dusun menentukan Kepala Dusun mana yang menerima
                permohonan. Periksa seluruh barisnya terhadap data resmi desa sebelum
                layanan dibuka untuk warga.
              </p>

              <div className="overflow-x-auto">
                <table className="w-full min-w-[520px] text-sm">
                  <thead className="border-b border-slate-200 text-left text-xs text-slate-500 uppercase">
                    <tr>
                      <th className="py-2 pr-3">RT</th>
                      <th className="py-2 pr-3">Dusun</th>
                      <th className="py-2">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {daftarRt?.map((rt) => (
                      <tr key={rt.id}>
                        <td className="py-2.5 pr-3 font-medium">RT {rt.nomor}</td>
                        <td className="py-2.5 pr-3">
                          <select
                            aria-label={`Dusun untuk RT ${rt.nomor}`}
                            value={rt.dusun_id ?? ''}
                            onChange={(e) =>
                              ubahDusun.mutate({ rt, dusunId: e.target.value })
                            }
                            className="rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
                          >
                            <option value="">— belum ditentukan —</option>
                            {daftarDusun?.map((d) => (
                              <option key={d.id} value={d.id}>
                                {d.nama}
                              </option>
                            ))}
                          </select>
                        </td>
                        <td className="py-2.5">
                          <button
                            onClick={() => {
                              if (window.confirm(`Hapus RT ${rt.nomor}?`)) hapus.mutate(rt.id)
                            }}
                            className="text-xs text-red-600 hover:underline"
                          >
                            Hapus
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )
        }
      />
    </div>
  )
}

function Bungkus(page: ReactNode) {
  return <LayoutAdmin>{page}</LayoutAdmin>
}

SuratPage.layout = Bungkus
