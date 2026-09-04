import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { Kartu, Kolom, Input, Pemberitahuan, Pilihan, TextArea, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'

const STATUS_IDM = ['Sangat Tertinggal', 'Tertinggal', 'Berkembang', 'Maju', 'Mandiri'] as const
const PIHAK_PELAKSANA = ['Pusat', 'Provinsi', 'Kabupaten', 'Desa', 'CSR', 'Lainnya'] as const

interface Indikator {
  id?: number
  no: number | null
  nama_indikator: string
  skor: number | null
  keterangan: string | null
  kegiatan_rekomendasi: string | null
  nilai_tambah: number | null
  pihak_pelaksana: string[] | null
}

interface SkorIdm {
  id: number
  tahun: number
  skor_iks: number | null
  skor_ike: number | null
  skor_ikl: number | null
  skor_idm: number | null
  status_idm: string | null
  target_status: string | null
  skor_minimal_target: number | null
  penambahan_skor_dibutuhkan: number | null
  publikasikan: boolean
  indicators: Indikator[]
}

const SKOR_KOSONG = {
  skor_iks: '',
  skor_ike: '',
  skor_ikl: '',
  skor_idm: '',
  status_idm: '',
  target_status: '',
  skor_minimal_target: '',
  penambahan_skor_dibutuhkan: '',
  publikasikan: false,
}

function keNumOrNull(v: string): number | null {
  return v === '' ? null : Number(v)
}

/**
 * CMS Indeks Desa Membangun — PRD 5.9 & 6.7.
 *
 * Skor komposit (IKS/IKE/IKL/IDM) disimpan per tahun; tabel indikator dikelola
 * terpisah dan diganti utuh saat disimpan. Skor IDM dihitung otomatis oleh
 * server bila dikosongkan.
 */
export default function IdmAdminPage() {
  const queryClient = useQueryClient()
  const [tahun, setTahun] = useState(String(new Date().getFullYear()))
  const [skor, setSkor] = useState(SKOR_KOSONG)
  const [indikator, setIndikator] = useState<Indikator[]>([])
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sukses, setSukses] = useState<string | null>(null)

  const { data } = useQuery({
    queryKey: ['admin', 'idm'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<SkorIdm[]>>('/admin/idm')
      return r.data.data
    },
  })

  const tahunTersedia = useMemo(() => (data ?? []).map((s) => s.tahun), [data])
  const skorTahunIni = useMemo(
    () => (data ?? []).find((s) => s.tahun === Number(tahun)) ?? null,
    [data, tahun],
  )

  // Muat isian saat tahun terpilih berubah.
  useEffect(() => {
    if (!data) return

    if (!skorTahunIni) {
      setSkor(SKOR_KOSONG)
      setIndikator([])
      return
    }

    setSkor({
      skor_iks: skorTahunIni.skor_iks?.toString() ?? '',
      skor_ike: skorTahunIni.skor_ike?.toString() ?? '',
      skor_ikl: skorTahunIni.skor_ikl?.toString() ?? '',
      skor_idm: skorTahunIni.skor_idm?.toString() ?? '',
      status_idm: skorTahunIni.status_idm ?? '',
      target_status: skorTahunIni.target_status ?? '',
      skor_minimal_target: skorTahunIni.skor_minimal_target?.toString() ?? '',
      penambahan_skor_dibutuhkan: skorTahunIni.penambahan_skor_dibutuhkan?.toString() ?? '',
      publikasikan: skorTahunIni.publikasikan,
    })
    setIndikator(skorTahunIni.indicators ?? [])
  }, [skorTahunIni, data])

  const simpanSkor = useMutation({
    mutationFn: () =>
      api.post('/admin/idm', {
        tahun: Number(tahun),
        skor_iks: keNumOrNull(skor.skor_iks),
        skor_ike: keNumOrNull(skor.skor_ike),
        skor_ikl: keNumOrNull(skor.skor_ikl),
        skor_idm: keNumOrNull(skor.skor_idm),
        status_idm: skor.status_idm || null,
        target_status: skor.target_status || null,
        skor_minimal_target: keNumOrNull(skor.skor_minimal_target),
        penambahan_skor_dibutuhkan: keNumOrNull(skor.penambahan_skor_dibutuhkan),
        publikasikan: skor.publikasikan,
      }),
    onSuccess: () => {
      setGalat(null)
      setSukses(`Skor IDM tahun ${tahun} berhasil disimpan.`)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'idm'] })
    },
    onError: (e) => {
      setSukses(null)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  const hapusSkor = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/idm/${id}`),
    onSuccess: () => {
      setGalat(null)
      setSukses(`Skor IDM tahun ${tahun} berhasil dihapus.`)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'idm'] })
    },
    onError: (e) => {
      setSukses(null)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menghapus.', 0))
    },
  })

  const simpanIndikator = useMutation({
    mutationFn: () => {
      if (!skorTahunIni) throw new ApiRequestError('Simpan skor tahun ini lebih dulu.', 0)
      return api.put(`/admin/idm/${skorTahunIni.id}/indikator`, {
        indikator: indikator.map((ind, i) => ({
          no: ind.no ?? i + 1,
          nama_indikator: ind.nama_indikator,
          skor: ind.skor,
          keterangan: ind.keterangan || null,
          kegiatan_rekomendasi: ind.kegiatan_rekomendasi || null,
          nilai_tambah: ind.nilai_tambah,
          pihak_pelaksana: ind.pihak_pelaksana ?? [],
        })),
      })
    },
    onSuccess: () => {
      setGalat(null)
      setSukses('Tabel indikator berhasil disimpan.')
      void queryClient.invalidateQueries({ queryKey: ['admin', 'idm'] })
    },
    onError: (e) => {
      setSukses(null)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  function ubahIndikator(i: number, ubahan: Partial<Indikator>) {
    setIndikator((lama) => lama.map((ind, idx) => (idx === i ? { ...ind, ...ubahan } : ind)))
  }

  function togglePihak(i: number, pihak: string) {
    setIndikator((lama) =>
      lama.map((ind, idx) => {
        if (idx !== i) return ind
        const kini = ind.pihak_pelaksana ?? []
        return {
          ...ind,
          pihak_pelaksana: kini.includes(pihak)
            ? kini.filter((p) => p !== pihak)
            : [...kini, pihak],
        }
      }),
    )
  }

  function tambahIndikator() {
    setIndikator((lama) => [
      ...lama,
      {
        no: lama.length + 1,
        nama_indikator: '',
        skor: null,
        keterangan: null,
        kegiatan_rekomendasi: null,
        nilai_tambah: null,
        pihak_pelaksana: [],
      },
    ])
  }

  function kirimSkor(e: FormEvent) {
    e.preventDefault()
    simpanSkor.mutate()
  }

  return (
    <div className="max-w-5xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Indeks Desa Membangun (IDM)</h1>
        <p className="mt-1 text-sm text-slate-600">
          Skor IKS, IKE, dan IKL dinilai 0–1. Skor IDM boleh dikosongkan — server menghitungnya
          otomatis dari ketiga dimensi.
        </p>
      </div>

      <form onSubmit={kirimSkor} className="space-y-6">
        <Kartu
          judul="Skor Tahunan"
          anak={
            <div className="space-y-4">
              {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}
              {sukses && <Pemberitahuan jenis="sukses" pesan={sukses} />}

              <div className="grid gap-4 sm:grid-cols-4">
                <Kolom
                  label="Tahun"
                  htmlFor="tahun"
                  petunjuk="Pilih tahun ada / ketik baru."
                  galat={galat?.fieldError('tahun')}
                >
                  <Input
                    id="tahun"
                    type="number"
                    min={2000}
                    max={2100}
                    required
                    list="tahun-idm"
                    value={tahun}
                    onChange={(e) => setTahun(e.target.value)}
                    galat={galat?.fieldError('tahun')}
                  />
                  <datalist id="tahun-idm">
                    {tahunTersedia.map((t) => (
                      <option key={t} value={t} />
                    ))}
                  </datalist>
                </Kolom>

                {(
                  [
                    ['skor_iks', 'Skor IKS'],
                    ['skor_ike', 'Skor IKE'],
                    ['skor_ikl', 'Skor IKL'],
                  ] as const
                ).map(([kunci, label]) => (
                  <Kolom key={kunci} label={label} htmlFor={kunci} galat={galat?.fieldError(kunci)}>
                    <Input
                      id={kunci}
                      type="number"
                      min={0}
                      max={1}
                      step="0.0001"
                      value={skor[kunci]}
                      onChange={(e) => setSkor({ ...skor, [kunci]: e.target.value })}
                      galat={galat?.fieldError(kunci)}
                    />
                  </Kolom>
                ))}

                <Kolom
                  label="Skor IDM"
                  htmlFor="skor_idm"
                  petunjuk="Kosongkan = hitung otomatis."
                  galat={galat?.fieldError('skor_idm')}
                >
                  <Input
                    id="skor_idm"
                    type="number"
                    min={0}
                    max={1}
                    step="0.0001"
                    value={skor.skor_idm}
                    onChange={(e) => setSkor({ ...skor, skor_idm: e.target.value })}
                    galat={galat?.fieldError('skor_idm')}
                  />
                </Kolom>

                <Kolom label="Status IDM" htmlFor="status_idm" galat={galat?.fieldError('status_idm')}>
                  <Pilihan
                    id="status_idm"
                    value={skor.status_idm}
                    onChange={(v) => setSkor({ ...skor, status_idm: v })}
                    galat={galat?.fieldError('status_idm')}
                    options={[
                      { value: '', label: '—' },
                      ...STATUS_IDM.map((s) => ({ value: s, label: s })),
                    ]}
                  />
                </Kolom>

                <Kolom label="Target Status" htmlFor="target_status">
                  <Pilihan
                    id="target_status"
                    value={skor.target_status}
                    onChange={(v) => setSkor({ ...skor, target_status: v })}
                    options={[
                      { value: '', label: '—' },
                      ...STATUS_IDM.map((s) => ({ value: s, label: s })),
                    ]}
                  />
                </Kolom>

                <Kolom label="Skor Minimal Target" htmlFor="skor_minimal_target">
                  <Input
                    id="skor_minimal_target"
                    type="number"
                    min={0}
                    max={1}
                    step="0.0001"
                    value={skor.skor_minimal_target}
                    onChange={(e) => setSkor({ ...skor, skor_minimal_target: e.target.value })}
                  />
                </Kolom>

                <Kolom label="Penambahan Dibutuhkan" htmlFor="penambahan_skor_dibutuhkan">
                  <Input
                    id="penambahan_skor_dibutuhkan"
                    type="number"
                    min={0}
                    max={1}
                    step="0.0001"
                    value={skor.penambahan_skor_dibutuhkan}
                    onChange={(e) =>
                      setSkor({ ...skor, penambahan_skor_dibutuhkan: e.target.value })
                    }
                  />
                </Kolom>
              </div>

              <label className="flex items-center gap-2 text-sm text-slate-700">
                <input
                  type="checkbox"
                  checked={skor.publikasikan}
                  onChange={(e) => setSkor({ ...skor, publikasikan: e.target.checked })}
                  className="size-4 rounded border-slate-300"
                />
                Tampilkan ke publik
              </label>

              <div className="flex flex-wrap gap-2">
                <Tombol type="submit" disabled={simpanSkor.isPending}>
                  {simpanSkor.isPending ? 'Menyimpan…' : `Simpan Skor Tahun ${tahun}`}
                </Tombol>

                {/*
                  Tombol hapus hanya muncul untuk tahun yang datanya memang
                  sudah tersimpan — menawarkan "hapus" atas tahun yang belum
                  pernah diisi hanya membingungkan.
                */}
                {skorTahunIni && (
                  <Tombol
                    type="button"
                    variasi="bahaya"
                    disabled={hapusSkor.isPending}
                    onClick={() => {
                      if (
                        confirm(
                          `Hapus skor IDM tahun ${tahun} beserta seluruh indikatornya? ` +
                            'Tindakan ini tidak dapat dibatalkan.',
                        )
                      ) {
                        hapusSkor.mutate(skorTahunIni.id)
                      }
                    }}
                  >
                    <Trash2 className="size-4" aria-hidden="true" />
                    {hapusSkor.isPending ? 'Menghapus…' : `Hapus Tahun ${tahun}`}
                  </Tombol>
                )}
              </div>
            </div>
          }
        />
      </form>

      <Kartu
        judul="Tabel Indikator"
        anak={
          !skorTahunIni ? (
            <p className="py-6 text-center text-sm text-slate-500">
              Simpan skor tahun {tahun} terlebih dahulu untuk mengelola tabel indikatornya.
            </p>
          ) : (
            <div className="space-y-4">
              {indikator.length === 0 && (
                <p className="text-sm text-slate-500">Belum ada indikator.</p>
              )}

              {indikator.map((ind, i) => (
                <div
                  key={i}
                  className="space-y-3 rounded-lg border border-slate-200 p-4"
                >
                  <div className="flex items-start gap-2">
                    <span className="mt-2.5 grid size-6 shrink-0 place-items-center rounded-full bg-teal-600/10 text-xs font-semibold text-teal-700">
                      {i + 1}
                    </span>
                    <div className="flex-1">
                      <Input
                        id={`nama-ind-${i}`}
                        aria-label={`Nama indikator ${i + 1}`}
                        placeholder="Nama indikator"
                        required
                        value={ind.nama_indikator}
                        onChange={(e) => ubahIndikator(i, { nama_indikator: e.target.value })}
                      />
                    </div>
                    <button
                      type="button"
                      onClick={() =>
                        setIndikator((lama) => lama.filter((_, idx) => idx !== i))
                      }
                      className="mt-2 shrink-0 text-sm text-red-600 hover:underline"
                    >
                      Hapus
                    </button>
                  </div>

                  <div className="grid gap-3 sm:grid-cols-2">
                    <Input
                      id={`skor-ind-${i}`}
                      aria-label={`Skor indikator ${i + 1}`}
                      type="number"
                      step="0.0001"
                      placeholder="Skor"
                      value={ind.skor ?? ''}
                      onChange={(e) =>
                        ubahIndikator(i, { skor: e.target.value === '' ? null : Number(e.target.value) })
                      }
                    />
                    <Input
                      id={`nilai-ind-${i}`}
                      aria-label={`Nilai tambah indikator ${i + 1}`}
                      type="number"
                      step="0.0001"
                      placeholder="Nilai tambah"
                      value={ind.nilai_tambah ?? ''}
                      onChange={(e) =>
                        ubahIndikator(i, {
                          nilai_tambah: e.target.value === '' ? null : Number(e.target.value),
                        })
                      }
                    />
                  </div>

                  <TextArea
                    id={`rekomendasi-ind-${i}`}
                    aria-label={`Kegiatan rekomendasi indikator ${i + 1}`}
                    rows={2}
                    placeholder="Kegiatan / rekomendasi"
                    value={ind.kegiatan_rekomendasi ?? ''}
                    onChange={(e) => ubahIndikator(i, { kegiatan_rekomendasi: e.target.value })}
                  />

                  <fieldset>
                    <legend className="text-xs font-medium text-slate-500">Pihak pelaksana</legend>
                    <div className="mt-1 flex flex-wrap gap-3">
                      {PIHAK_PELAKSANA.map((p) => (
                        <label key={p} className="flex items-center gap-1.5 text-sm text-slate-700">
                          <input
                            type="checkbox"
                            checked={(ind.pihak_pelaksana ?? []).includes(p)}
                            onChange={() => togglePihak(i, p)}
                            className="size-4 rounded border-slate-300"
                          />
                          {p}
                        </label>
                      ))}
                    </div>
                  </fieldset>
                </div>
              ))}

              <div className="flex flex-wrap gap-3">
                <Tombol type="button" variasi="sekunder" onClick={tambahIndikator}>
                  + Tambah Indikator
                </Tombol>
                <Tombol
                  type="button"
                  onClick={() => simpanIndikator.mutate()}
                  disabled={simpanIndikator.isPending}
                >
                  {simpanIndikator.isPending ? 'Menyimpan…' : 'Simpan Tabel Indikator'}
                </Tombol>
              </div>
            </div>
          )
        }
      />
    </div>
  )
}

IdmAdminPage.layout = (page: ReactNode) => (
  <LayoutAdmin judul="Indeks Desa Membangun">{page}</LayoutAdmin>
)
