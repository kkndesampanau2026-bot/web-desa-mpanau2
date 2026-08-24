import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { Kartu, Kolom, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface PengaduanRingkas {
  id: number
  nomor_tiket: string
  nama: string
  kategori_pengaduan: string
  isi_ringkas: string
  status: 'baru' | 'diproses' | 'selesai' | 'ditolak'
  jumlah_lampiran: number
  tanggal_pengaduan: string
}

interface PengaduanDetail extends Omit<PengaduanRingkas, 'isi_ringkas' | 'jumlah_lampiran'> {
  no_telepon_wa: string
  isi_pengaduan: string
  tanggapan_admin: string | null
  alasan_penolakan: string | null
  tanggal_tanggapan: string | null
  lampiran: { id: number; nama_asli: string; mime_type: string; ukuran_byte: number }[]
}

interface Rekap {
  per_status: Record<string, number>
  per_kategori: Record<string, number>
  belum_ditanggapi: number
}

const STATUS = ['baru', 'diproses', 'selesai', 'ditolak'] as const

const LABEL_STATUS: Record<string, string> = {
  baru: 'Baru',
  diproses: 'Sedang Diproses',
  selesai: 'Selesai',
  ditolak: 'Ditolak',
}

const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

/** Manajemen Pengaduan — PRD 5.18. */
export default function PengaduanAdminPage() {
  const queryClient = useQueryClient()
  const [dibukaId, setDibukaId] = useState<number | null>(null)
  const [filterStatus, setFilterStatus] = useState('')

  const { data: rekap } = useQuery({
    queryKey: ['admin', 'pengaduan', 'rekap'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<Rekap>>('/admin/pengaduan/rekap')
      return r.data.data
    },
  })

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'pengaduan', filterStatus],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<PengaduanRingkas[]>>('/admin/pengaduan', {
        params: { status: filterStatus || undefined },
      })
      return r.data.data
    },
  })

  if (dibukaId !== null) {
    return (
      <DetailPengaduan
        id={dibukaId}
        onSelesai={() => {
          setDibukaId(null)
          void queryClient.invalidateQueries({ queryKey: ['admin', 'pengaduan'] })
        }}
      />
    )
  }

  return (
    <div className="max-w-4xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Pengaduan Masyarakat</h1>
        <p className="mt-1 text-sm text-slate-600">
          Tanggapi pengaduan warga. Nomor telepon pelapor hanya terbuka saat Anda
          membuka detailnya, dan pembukaan itu tercatat pada log aktivitas.
        </p>
      </div>

      {rekap && (
        <div className="grid gap-4 sm:grid-cols-4">
          {STATUS.map((s) => (
            <div key={s} className="rounded-xl border border-slate-200 bg-white p-4">
              <p className="text-sm text-slate-500">{LABEL_STATUS[s]}</p>
              <p className="mt-1 text-2xl font-semibold text-slate-900">
                {rekap.per_status[s] ?? 0}
              </p>
            </div>
          ))}
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        <Chip aktif={!filterStatus} onClick={() => setFilterStatus('')}>
          Semua
        </Chip>
        {STATUS.map((s) => (
          <Chip key={s} aktif={filterStatus === s} onClick={() => setFilterStatus(s)}>
            {LABEL_STATUS[s]}
          </Chip>
        ))}
      </div>

      <Kartu
        anak={
          isPending ? (
            <p className="text-slate-500">Memuat…</p>
          ) : !data?.length ? (
            <p className="py-8 text-center text-slate-500">Tidak ada pengaduan pada filter ini.</p>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 text-left text-slate-500">
                    <th scope="col" className="pb-2 font-medium">Nomor Tiket</th>
                    <th scope="col" className="pb-2 font-medium">Pengaduan</th>
                    <th scope="col" className="pb-2 font-medium">Kategori</th>
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
                        {p.nomor_tiket}
                      </td>
                      <td className="py-2.5 pr-4">
                        <p className="font-medium text-slate-900">{p.nama}</p>
                        <p className="max-w-xs truncate text-xs text-slate-500">
                          {p.isi_ringkas}
                          {p.jumlah_lampiran > 0 && ` · ${p.jumlah_lampiran} lampiran`}
                        </p>
                      </td>
                      <td className="py-2.5 pr-4 text-slate-700">{p.kategori_pengaduan}</td>
                      <td className="py-2.5 pr-4">
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                          {LABEL_STATUS[p.status]}
                        </span>
                      </td>
                      <td className="py-2.5 text-right">
                        <button
                          onClick={() => setDibukaId(p.id)}
                          className="text-slate-700 hover:underline"
                        >
                          Buka
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

function DetailPengaduan({ id, onSelesai }: { id: number; onSelesai: () => void }) {
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [form, setForm] = useState({ status: '', tanggapan_admin: '', alasan_penolakan: '' })
  const [terisi, setTerisi] = useState(false)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'pengaduan', 'detail', id],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<PengaduanDetail>>(`/admin/pengaduan/${id}`)
      return r.data.data
    },
  })

  // Formulir diisi sekali dari data yang dimuat; setelah itu suntingan
  // operator tidak boleh tertimpa oleh refetch.
  if (data && !terisi) {
    setForm({
      status: data.status,
      tanggapan_admin: data.tanggapan_admin ?? '',
      alasan_penolakan: data.alasan_penolakan ?? '',
    })
    setTerisi(true)
  }

  const simpan = useMutation({
    mutationFn: () =>
      api.put(`/admin/pengaduan/${id}`, {
        status: form.status,
        tanggapan_admin: form.tanggapan_admin || null,
        alasan_penolakan: form.alasan_penolakan || null,
      }),
    onSuccess: onSelesai,
    onError: (e) =>
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0)),
  })

  if (isPending || !data) return <p className="text-slate-500">Memuat…</p>

  return (
    <form
      onSubmit={(e: FormEvent) => {
        e.preventDefault()
        simpan.mutate()
      }}
      className="max-w-3xl space-y-6"
    >
      <div className="flex items-center justify-between gap-3">
        <h1 className="font-semibold text-slate-900">Pengaduan {data.nomor_tiket}</h1>
        <Tombol type="button" variasi="sekunder" onClick={onSelesai}>
          Kembali
        </Tombol>
      </div>

      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <Kartu
        judul="Isi Pengaduan"
        anak={
          <div className="space-y-4">
            <dl className="space-y-3 text-sm">
              <Baris label="Pelapor" nilai={data.nama} />
              <Baris label="Kontak" nilai={data.no_telepon_wa} />
              <Baris label="Kategori" nilai={data.kategori_pengaduan} />
              <Baris label="Isi" nilai={data.isi_pengaduan} />
            </dl>

            {data.lampiran.length > 0 && (
              <div className="border-t border-slate-100 pt-4">
                <p className="text-sm font-medium text-slate-700">Lampiran</p>
                <ul className="mt-2 space-y-1">
                  {data.lampiran.map((l) => (
                    <li key={l.id}>
                      {/*
                        Lampiran berada pada disk privat; tautan ini menuju
                        endpoint admin yang memeriksa izin, bukan URL berkas
                        langsung.
                      */}
                      <a
                        href={`${BASE_URL}/api/v1/admin/pengaduan/${id}/lampiran/${l.id}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-slate-700 underline hover:text-slate-900"
                      >
                        {l.nama_asli}
                      </a>
                      <span className="ml-2 text-xs text-slate-500">
                        {(l.ukuran_byte / 1024).toFixed(0)} KB
                      </span>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        }
      />

      <Kartu
        judul="Tanggapan"
        anak={
          <div className="space-y-4">
            <Kolom label="Status" htmlFor="status">
              <select
                id="status"
                value={form.status}
                onChange={(e) => setForm({ ...form, status: e.target.value })}
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
              label="Tanggapan untuk Pelapor"
              htmlFor="tanggapan"
              galat={galat?.fieldError('tanggapan_admin')}
            >
              <TextArea
                id="tanggapan"
                rows={4}
                value={form.tanggapan_admin}
                onChange={(e) => setForm({ ...form, tanggapan_admin: e.target.value })}
                galat={galat?.fieldError('tanggapan_admin')}
              />
            </Kolom>

            {form.status === 'ditolak' && (
              <Kolom
                label="Alasan Penolakan"
                htmlFor="alasan"
                petunjuk="Wajib diisi agar pelapor mengetahui dasar keputusannya."
                galat={galat?.fieldError('alasan_penolakan')}
              >
                <TextArea
                  id="alasan"
                  rows={3}
                  required
                  value={form.alasan_penolakan}
                  onChange={(e) => setForm({ ...form, alasan_penolakan: e.target.value })}
                  galat={galat?.fieldError('alasan_penolakan')}
                />
              </Kolom>
            )}
          </div>
        }
      />

      <Tombol type="submit" disabled={simpan.isPending}>
        {simpan.isPending ? 'Menyimpan…' : 'Simpan Tanggapan'}
      </Tombol>
    </form>
  )
}

function Baris({ label, nilai }: { label: string; nilai: string }) {
  return (
    <div>
      <dt className="text-slate-500">{label}</dt>
      <dd className="whitespace-pre-line text-slate-800">{nilai}</dd>
    </div>
  )
}

function Chip({
  aktif,
  onClick,
  children,
}: {
  aktif: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      onClick={onClick}
      aria-current={aktif ? 'true' : undefined}
      className={`rounded-full px-3 py-1 text-sm transition ${
        aktif ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
      }`}
    >
      {children}
    </button>
  )
}

PengaduanAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="Pengaduan">{page}</LayoutAdmin>
