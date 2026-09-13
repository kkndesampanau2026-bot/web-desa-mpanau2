import { useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileSignature, Inbox, MapPinned, RefreshCw, Send, Signature } from 'lucide-react'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import {
  AksiBaris,
  Input,
  Kartu,
  Kolom,
  Pemberitahuan,
  Pilihan,
  TautanIkon,
  Tombol,
  TombolIkon,
  useGulirKeForm,
} from '@/Components/Admin/Form'
import { tampilkanToast } from '@/Components/Admin/Toast'
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
  /**
   * Nilai apa adanya, bukan sekadar penanda terisi/kosong — operator perlu
   * mencocokkannya dengan chat ID milik pejabat yang bersangkutan
   * (docs/DEVIASI.md §C19). Null berarti belum diisi.
   */
  telegram_chat_id: string | null
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
    onSuccess: () => {
      tampilkanToast('Berkas PDF dibuat ulang.')
      void queryClient.invalidateQueries({ queryKey: ['admin', 'surat', 'pengajuan'] })
    },
    onError: () =>
      tampilkanToast('Berkas PDF masih gagal dibuat. Periksa log aplikasi.', 'galat'),
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
            <div className="w-48">
              <Pilihan
                id="filter-status"
                value={status}
                onChange={setStatus}
                options={[
                  { value: '', label: 'Semua' },
                  ...Object.entries(LABEL_STATUS).map(([k, v]) => ({ value: k, label: v })),
                ]}
              />
            </div>
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
                          <TautanIkon
                            ikon={Download}
                            gaya="utama"
                            judul="Unduh PDF"
                            label={`Unduh PDF surat ${p.ticket_number}`}
                            href={`/api/v1/admin/surat/pengajuan/${p.id}/unduh`}
                          />
                        ) : p.status === 'DITOLAK' ? (
                          <span className="text-xs text-slate-400">—</span>
                        ) : (
                          <TombolIkon
                            ikon={RefreshCw}
                            gaya="peringatan"
                            judul="Buat ulang PDF"
                            label={`Buat ulang PDF surat ${p.ticket_number}`}
                            onClick={() => buatUlang.mutate(p.id)}
                            disabled={buatUlang.isPending}
                          />
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

  const formulir = useGulirKeForm()

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
    tampilkanToast(pesan)
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
    onSuccess: () => bereskan(sunting ? 'Perubahan pejabat tersimpan.' : 'Pejabat ditambahkan.'),
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/surat/pejabat/${id}`),
    onSuccess: () => bereskan('Pejabat dihapus atau dinonaktifkan.'),
    onError: () => tampilkanToast('Pejabat gagal dihapus. Coba lagi.', 'galat'),
  })

  function mulaiSunting(p: Pejabat) {
    setSunting(p.id)
    setGalat(null)
    setForm({
      role: p.role,
      nama: p.nama,
      jabatan_teks: p.jabatan_teks ?? '',
      rt_id: p.rt_id ? String(p.rt_id) : '',
      dusun_id: p.dusun_id ? String(p.dusun_id) : '',
      // Chat ID ikut terisi apa adanya, jadi menyimpan tanpa menyentuh kolom
      // ini mempertahankan nilainya. Sebelumnya server tidak pernah
      // mengirimnya balik: kolomnya selalu mulai kosong, dan operator yang
      // sekadar memperbaiki ejaan nama ikut menghapus chat ID pejabat tanpa
      // menyadarinya — pengajuan berikutnya lalu tertahan tanpa notifikasi.
      telegram_chat_id: p.telegram_chat_id ?? '',
      is_active: p.is_active,
    })
    setTtd(null)

    // Formulirnya ada di atas daftar; tanpa ini, menekan "Ubah" pada baris
    // paling bawah tidak memperlihatkan perubahan apa pun di layar.
    formulir.gulir()
  }

  const perluRt = form.role === 'KETUA_RT'

  return (
    <div className="space-y-6">
      <Kartu
        judul={sunting ? 'Ubah Pejabat' : 'Tambah Pejabat'}
        ikon={Signature}
        // Sasaran gulir tombol "Ubah" pada daftar di bawah.
        wadahRef={formulir.ref}
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
              <Kolom label="Jabatan" htmlFor="role" galat={galat?.fieldError('role')}>
                <Pilihan
                  id="role"
                  value={form.role}
                  onChange={(v) => setForm({ ...form, role: v as Pejabat['role'] })}
                  galat={galat?.fieldError('role')}
                  options={[
                    { value: 'KETUA_RT', label: 'Ketua RT' },
                    { value: 'KEPALA_DUSUN', label: 'Kepala Dusun' },
                  ]}
                />
              </Kolom>

              {perluRt ? (
                <Kolom label="RT" htmlFor="rt_id" galat={galat?.fieldError('rt_id')}>
                  <Pilihan
                    id="rt_id"
                    value={form.rt_id}
                    onChange={(v) => setForm({ ...form, rt_id: v })}
                    placeholder="Pilih RT…"
                    galat={galat?.fieldError('rt_id')}
                    options={(daftarRt ?? []).map((rt) => ({
                      value: String(rt.id),
                      label: `RT ${rt.nomor} ${rt.dusun ? `— ${rt.dusun}` : ''}`,
                    }))}
                  />
                </Kolom>
              ) : (
                <Kolom label="Dusun" htmlFor="dusun_id" galat={galat?.fieldError('dusun_id')}>
                  <Pilihan
                    id="dusun_id"
                    value={form.dusun_id}
                    onChange={(v) => setForm({ ...form, dusun_id: v })}
                    placeholder="Pilih dusun…"
                    galat={galat?.fieldError('dusun_id')}
                    options={(daftarDusun ?? []).map((d) => ({
                      value: String(d.id),
                      label: d.nama,
                    }))}
                  />
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
                  ? 'Chat ID tersimpan ditampilkan apa adanya. Dikosongkan berarti pejabat ini berhenti menerima notifikasi.'
                  : 'Angka saja. Pejabat mendapatkannya dengan mengirim /start ke bot desa, atau lewat @userinfobot. Boleh sama dengan pejabat lain bila orangnya memang sama.'
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
                    <th className="py-2 text-right">Aksi</th>
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
                        {p.telegram_chat_id ? (
                          <span className="font-mono text-xs text-slate-700">
                            {p.telegram_chat_id}
                          </span>
                        ) : (
                          <span className="text-xs text-amber-700">Belum diisi</span>
                        )}
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
                        <AksiBaris
                          nama={p.nama}
                          onSunting={() => mulaiSunting(p)}
                          onHapus={() => hapus.mutate(p.id)}
                          sedangProses={hapus.isPending}
                          pesanHapus={
                            'Pejabat yang pernah menandatangani surat tidak dihapus, ' +
                            'melainkan dinonaktifkan — riwayat surat yang sudah terbit ' +
                            'tetap dapat ditelusuri.'
                          }
                        />
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

              <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Satu chat ID boleh muncul pada beberapa baris sekaligus, untuk orang yang
                menjabat Ketua RT pada lebih dari satu RT. Buat satu baris per RT dengan
                chat ID yang sama; notifikasi kedua RT masuk ke akun Telegram itu, dan
                yang menentukan wewenangnya tetap RT pada masing-masing baris.
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
      tampilkanToast('RT ditambahkan.')
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
    onSuccess: () => {
      tampilkanToast('Pemetaan dusun diperbarui.')
      void segarkan()
    },
    onError: () => tampilkanToast('Pemetaan dusun gagal disimpan. Coba lagi.', 'galat'),
  })

  const hapus = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/surat/rt/${id}`),
    onSuccess: () => {
      tampilkanToast('RT dihapus.')
      void segarkan()
    },
    // Penolakan paling sering di sini — "RT sudah dipakai pengajuan" — dulu
    // muncul di kotak galat pada formulir TAMBAH di puncak halaman, jauh dari
    // baris yang barusan ditekan tombol hapusnya.
    onError: (e) =>
      tampilkanToast(
        e instanceof ApiRequestError ? e.message : 'RT gagal dihapus. Coba lagi.',
        'galat',
      ),
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
                <Pilihan
                  id="dusun"
                  value={form.dusun_id}
                  onChange={(v) => setForm({ ...form, dusun_id: v })}
                  galat={galat?.fieldError('dusun_id')}
                  options={[
                    { value: '', label: '— belum ditentukan —' },
                    ...(daftarDusun ?? []).map((d) => ({ value: String(d.id), label: d.nama })),
                  ]}
                />
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
                      <th className="py-2 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {daftarRt?.map((rt) => (
                      <tr key={rt.id}>
                        <td className="py-2.5 pr-3 font-medium">RT {rt.nomor}</td>
                        <td className="py-2.5 pr-3">
                          <Pilihan
                            id={`dusun-rt-${rt.id}`}
                            ariaLabel={`Dusun untuk RT ${rt.nomor}`}
                            value={rt.dusun_id !== null ? String(rt.dusun_id) : ''}
                            onChange={(v) => ubahDusun.mutate({ rt, dusunId: v })}
                            options={[
                              { value: '', label: '— belum ditentukan —' },
                              ...(daftarDusun ?? []).map((d) => ({
                                value: String(d.id),
                                label: d.nama,
                              })),
                            ]}
                          />
                        </td>
                        <td className="py-2.5">
                          <AksiBaris
                            nama={`RT ${rt.nomor}`}
                            onHapus={() => hapus.mutate(rt.id)}
                            sedangProses={hapus.isPending}
                            pesanHapus={
                              'RT yang sudah dipakai pada pengajuan surat tidak dapat ' +
                              'dihapus. Warga juga tidak akan dapat memilihnya lagi pada ' +
                              'formulir surat pengantar.'
                            }
                          />
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
