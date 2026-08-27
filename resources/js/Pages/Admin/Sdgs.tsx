import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'

interface SkorSdgs {
  id: number
  tahun: number
  goal_number: number
  nama_goal: string
  skor: number | null
  deskripsi_capaian: string | null
  publikasikan: boolean
}

interface BarisGoal {
  goal_number: number
  nama_goal: string
  skor: string
  deskripsi_capaian: string
}

/** Nama baku 18 tujuan SDGs Desa (Permendesa 21/2020) sebagai isian awal. */
const NAMA_GOAL_BAKU = [
  'Desa Tanpa Kemiskinan',
  'Desa Tanpa Kelaparan',
  'Desa Sehat dan Sejahtera',
  'Pendidikan Desa Berkualitas',
  'Keterlibatan Perempuan Desa',
  'Desa Layak Air Bersih dan Sanitasi',
  'Desa Berenergi Bersih dan Terbarukan',
  'Pertumbuhan Ekonomi Desa Merata',
  'Infrastruktur dan Inovasi Desa sesuai Kebutuhan',
  'Desa Tanpa Kesenjangan',
  'Kawasan Permukiman Desa Aman dan Nyaman',
  'Konsumsi dan Produksi Desa Sadar Lingkungan',
  'Desa Tanggap Perubahan Iklim',
  'Desa Peduli Lingkungan Laut',
  'Desa Peduli Lingkungan Darat',
  'Desa Damai Berkeadilan',
  'Kemitraan untuk Pembangunan Desa',
  'Kelembagaan Desa Dinamis dan Budaya Desa Adaptif',
]

function barisBaku(): BarisGoal[] {
  return NAMA_GOAL_BAKU.map((nama, i) => ({
    goal_number: i + 1,
    nama_goal: nama,
    skor: '',
    deskripsi_capaian: '',
  }))
}

/**
 * CMS SDGs Desa — PRD 5.10 & 6.8.
 *
 * Skor 18 tujuan disimpan sekaligus per tahun. Memilih tahun yang sudah ada
 * memuat isiannya untuk diperbarui; tahun baru dimulai dari nama tujuan baku.
 */
export default function SdgsAdminPage() {
  const queryClient = useQueryClient()
  const [tahun, setTahun] = useState(String(new Date().getFullYear()))
  const [publikasikan, setPublikasikan] = useState(false)
  const [baris, setBaris] = useState<BarisGoal[]>(barisBaku())
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sukses, setSukses] = useState<string | null>(null)

  const { data } = useQuery({
    queryKey: ['admin', 'sdgs'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<SkorSdgs[]>>('/admin/sdgs')
      return r.data.data
    },
  })

  const tahunTersedia = useMemo(
    () => [...new Set((data ?? []).map((s) => s.tahun))].sort((a, b) => b - a),
    [data],
  )

  // Sinkronkan tabel isian dengan tahun terpilih: pakai data lama bila ada,
  // selain itu mulai dari nama tujuan baku.
  useEffect(() => {
    if (!data) return

    const tahunNum = Number(tahun)
    const adaTahun = data.filter((s) => s.tahun === tahunNum)

    if (adaTahun.length === 0) {
      setBaris(barisBaku())
      setPublikasikan(false)
      return
    }

    setPublikasikan(adaTahun.some((s) => s.publikasikan))
    setBaris(
      barisBaku().map((b) => {
        const ada = adaTahun.find((s) => s.goal_number === b.goal_number)
        return ada
          ? {
              goal_number: b.goal_number,
              nama_goal: ada.nama_goal,
              skor: ada.skor === null ? '' : String(ada.skor),
              deskripsi_capaian: ada.deskripsi_capaian ?? '',
            }
          : b
      }),
    )
  }, [tahun, data])

  const simpan = useMutation({
    mutationFn: () =>
      api.post('/admin/sdgs', {
        tahun: Number(tahun),
        publikasikan,
        goals: baris.map((b) => ({
          goal_number: b.goal_number,
          nama_goal: b.nama_goal,
          skor: b.skor === '' ? null : Number(b.skor),
          deskripsi_capaian: b.deskripsi_capaian || null,
        })),
      }),
    onSuccess: () => {
      setGalat(null)
      setSukses(`Skor SDGs Desa tahun ${tahun} berhasil disimpan.`)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'sdgs'] })
    },
    onError: (e) => {
      setSukses(null)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  function ubahBaris(i: number, ubahan: Partial<BarisGoal>) {
    setBaris((lama) => lama.map((b, idx) => (idx === i ? { ...b, ...ubahan } : b)))
  }

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate()
  }

  return (
    <div className="max-w-5xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">SDGs Desa</h1>
        <p className="mt-1 text-sm text-slate-600">
          Skor capaian 18 tujuan pembangunan berkelanjutan desa, dinilai 0–100 per tahun.
        </p>
      </div>

      <form onSubmit={kirim} className="space-y-6">
        <Kartu
          judul="Tahun & Publikasi"
          anak={
            <div className="space-y-4">
              {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}
              {sukses && <Pemberitahuan jenis="sukses" pesan={sukses} />}

              <div className="grid gap-4 sm:grid-cols-2">
                <Kolom
                  label="Tahun"
                  htmlFor="tahun"
                  petunjuk="Pilih tahun yang ada untuk memperbarui, atau ketik tahun baru."
                  galat={galat?.fieldError('tahun')}
                >
                  <Input
                    id="tahun"
                    type="number"
                    min={2000}
                    max={2100}
                    required
                    list="tahun-sdgs"
                    value={tahun}
                    onChange={(e) => setTahun(e.target.value)}
                    galat={galat?.fieldError('tahun')}
                  />
                  <datalist id="tahun-sdgs">
                    {tahunTersedia.map((t) => (
                      <option key={t} value={t} />
                    ))}
                  </datalist>
                </Kolom>

                <div className="flex items-end">
                  <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input
                      type="checkbox"
                      checked={publikasikan}
                      onChange={(e) => setPublikasikan(e.target.checked)}
                      className="size-4 rounded border-slate-300"
                    />
                    Tampilkan ke publik
                  </label>
                </div>
              </div>
            </div>
          }
        />

        <Kartu
          judul="Skor 18 Tujuan"
          anak={
            <div className="space-y-4">
              {baris.map((b, i) => (
                <div
                  key={b.goal_number}
                  className="grid gap-3 border-b border-slate-100 pb-4 last:border-0 last:pb-0 sm:grid-cols-[auto_1fr_7rem]"
                >
                  <div className="flex items-center gap-2 sm:col-span-3">
                    <span className="grid size-7 shrink-0 place-items-center rounded-full bg-teal-600/10 text-xs font-semibold text-teal-700">
                      {b.goal_number}
                    </span>
                    <Input
                      id={`nama-${b.goal_number}`}
                      aria-label={`Nama tujuan ${b.goal_number}`}
                      required
                      value={b.nama_goal}
                      onChange={(e) => ubahBaris(i, { nama_goal: e.target.value })}
                    />
                  </div>
                  <div className="sm:col-start-2">
                    <TextArea
                      id={`deskripsi-${b.goal_number}`}
                      aria-label={`Deskripsi capaian tujuan ${b.goal_number}`}
                      rows={1}
                      placeholder="Deskripsi capaian (opsional)"
                      value={b.deskripsi_capaian}
                      onChange={(e) => ubahBaris(i, { deskripsi_capaian: e.target.value })}
                    />
                  </div>
                  <div className="sm:col-start-3">
                    <Input
                      id={`skor-${b.goal_number}`}
                      aria-label={`Skor tujuan ${b.goal_number}`}
                      type="number"
                      min={0}
                      max={100}
                      step="0.01"
                      placeholder="Skor"
                      value={b.skor}
                      onChange={(e) => ubahBaris(i, { skor: e.target.value })}
                    />
                  </div>
                </div>
              ))}
            </div>
          }
        />

        <Tombol type="submit" disabled={simpan.isPending}>
          {simpan.isPending ? 'Menyimpan…' : `Simpan Skor Tahun ${tahun}`}
        </Tombol>
      </form>
    </div>
  )
}

SdgsAdminPage.layout = (page: ReactNode) => <LayoutAdmin judul="SDGs Desa">{page}</LayoutAdmin>
