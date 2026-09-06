import { useEffect, useState, type FormEvent } from 'react'
import { Link } from '@inertiajs/react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, type ApiSuccess } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

interface NomorPenting {
  nama_layanan: string
  nomor: string
}

interface SosialMedia {
  platform: string
  url: string
}

interface DataPengaturan {
  setting: Record<string, string | null>
  nomor_telepon_penting: NomorPenting[]
  sosial_media: SosialMedia[]
}

const KOLOM_TEKS = [
  ['nama_desa', 'Nama Desa'],
  ['kode_wilayah', 'Kode Wilayah'],
  ['kelurahan', 'Kelurahan/Desa'],
  ['kecamatan', 'Kecamatan'],
  ['kabupaten', 'Kabupaten'],
  ['provinsi', 'Provinsi'],
  ['kode_pos', 'Kode Pos'],
  ['telepon', 'Telepon'],
  ['email', 'Email'],
  ['whatsapp', 'WhatsApp'],
] as const

/** CMS Pengaturan Umum — PRD 5.19 & 6.17. */
export default function PengaturanPage() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<Record<string, string>>({})
  const [nomor, setNomor] = useState<NomorPenting[]>([])
  const [sosmed, setSosmed] = useState<SosialMedia[]>([])
  const [logo, setLogo] = useState<File | null>(null)
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sukses, setSukses] = useState(false)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: async () => {
      const r = await api.get<ApiSuccess<DataPengaturan>>('/admin/settings')
      return r.data.data
    },
  })

  useEffect(() => {
    if (!data) return

    setForm(
      Object.fromEntries(
        [...KOLOM_TEKS.map(([k]) => k), 'alamat_kantor'].map((k) => [
          k,
          data.setting?.[k] ?? '',
        ]),
      ),
    )
    setNomor(data.nomor_telepon_penting ?? [])
    setSosmed(data.sosial_media ?? [])
  }, [data])

  const simpan = useMutation({
    mutationFn: () =>
      // POST + `_method=PUT` karena logo dikirim sebagai multipart, dan PHP
      // tidak mengurai body multipart pada request PUT.
      api.post(
        '/admin/settings',
        keFormData(
          {
            ...form,
            // Baris yang belum terisi lengkap dibuang agar tidak memicu galat
            // validasi atas kolom yang memang belum diisi operator.
            nomor_telepon_penting: nomor.filter((n) => n.nama_layanan && n.nomor),
            sosial_media: sosmed.filter((s) => s.platform && s.url),
            logo,
          },
          { method: 'PUT' },
        ),
      ),
    onSuccess: () => {
      setGalat(null)
      setSukses(true)
      // Berkas dilepas setelah terkirim; bila tidak, menyimpan ulang akan
      // mengunggah logo yang sama untuk kedua kalinya.
      setLogo(null)
      void queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] })
    },
    onError: (e) => {
      setSukses(false)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate()
  }

  if (isPending) return <p className="text-slate-500">Memuat…</p>

  return (
    <form onSubmit={kirim} className="max-w-3xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Pengaturan Umum</h1>
        <p className="mt-1 text-sm text-slate-600">
          Identitas dan kontak desa yang tampil pada footer situs publik.
        </p>
      </div>

      {sukses && <Pemberitahuan jenis="sukses" pesan="Pengaturan berhasil disimpan." />}
      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <Kartu
        judul="Identitas & Wilayah"
        anak={
          <div className="grid gap-4 sm:grid-cols-2">
            {KOLOM_TEKS.map(([kunci, label]) => (
              <Kolom
                key={kunci}
                label={label}
                htmlFor={kunci}
                petunjuk={kunci === 'kode_wilayah' ? 'Format: 72.10.01.2013' : undefined}
                galat={galat?.fieldError(kunci)}
              >
                <Input
                  id={kunci}
                  value={form[kunci] ?? ''}
                  onChange={(e) => {
                    setForm({ ...form, [kunci]: e.target.value })
                    setSukses(false)
                  }}
                  galat={galat?.fieldError(kunci)}
                />
              </Kolom>
            ))}

            <div className="sm:col-span-2">
              <Kolom label="Alamat Kantor" htmlFor="alamat_kantor">
                <TextArea
                  id="alamat_kantor"
                  rows={2}
                  value={form.alamat_kantor ?? ''}
                  onChange={(e) => setForm({ ...form, alamat_kantor: e.target.value })}
                />
              </Kolom>
            </div>

            <div className="sm:col-span-2">
              <InputBerkas
                label="Logo Desa"
                jenis="gambar"
                berkas={logo}
                onPilih={(b) => {
                  setLogo(b)
                  setSukses(false)
                }}
                pathTersimpan={data?.setting?.logo}
                petunjuk="Tampil pada kepala situs publik dan dokumen resmi."
                galat={galat?.fieldError('logo')}
              />
            </div>

            {/*
              Banner beranda TIDAK lagi di sini.

              Hero kini memuat beberapa gambar bergilir, dan unggah/hapus
              gambar tidak cocok dengan formulir yang menyimpan seluruh isinya
              sekaligus — kegagalan pada satu gambar akan membatalkan alamat
              kantor dan jam kerja yang sudah benar. Pengelolaannya pindah ke
              layar "Banner Beranda" tersendiri.
            */}
            <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600 sm:col-span-2">
              Banner beranda kini dapat memuat beberapa gambar yang bergilir
              otomatis. Kelola lewat menu{' '}
              <Link href="/admin/banner" className="font-semibold text-teal-700 hover:underline">
                Banner Beranda
              </Link>
              .
            </div>
          </div>
        }
      />

      <Kartu
        judul="Nomor Telepon Penting"
        anak={
          <div className="space-y-2">
            {nomor.map((n, i) => (
              <div key={i} className="flex gap-2">
                <Input
                  aria-label={`Nama layanan ${i + 1}`}
                  placeholder="mis. Puskesmas"
                  value={n.nama_layanan}
                  onChange={(e) => {
                    const baru = [...nomor]
                    baru[i] = { ...baru[i], nama_layanan: e.target.value }
                    setNomor(baru)
                  }}
                />
                <Input
                  aria-label={`Nomor telepon ${i + 1}`}
                  placeholder="mis. 118"
                  value={n.nomor}
                  onChange={(e) => {
                    const baru = [...nomor]
                    baru[i] = { ...baru[i], nomor: e.target.value }
                    setNomor(baru)
                  }}
                />
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => setNomor(nomor.filter((_, n2) => n2 !== i))}
                  aria-label={`Hapus nomor ${i + 1}`}
                >
                  Hapus
                </Tombol>
              </div>
            ))}
            <Tombol
              type="button"
              variasi="sekunder"
              onClick={() => setNomor([...nomor, { nama_layanan: '', nomor: '' }])}
            >
              + Tambah Nomor
            </Tombol>
          </div>
        }
      />

      <Kartu
        judul="Sosial Media"
        anak={
          <div className="space-y-2">
            {sosmed.map((s, i) => (
              <div key={i} className="flex gap-2">
                <Input
                  aria-label={`Platform ${i + 1}`}
                  placeholder="mis. Facebook"
                  value={s.platform}
                  onChange={(e) => {
                    const baru = [...sosmed]
                    baru[i] = { ...baru[i], platform: e.target.value }
                    setSosmed(baru)
                  }}
                />
                <Input
                  aria-label={`URL ${i + 1}`}
                  type="url"
                  placeholder="https://…"
                  value={s.url}
                  onChange={(e) => {
                    const baru = [...sosmed]
                    baru[i] = { ...baru[i], url: e.target.value }
                    setSosmed(baru)
                  }}
                />
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => setSosmed(sosmed.filter((_, n) => n !== i))}
                  aria-label={`Hapus sosial media ${i + 1}`}
                >
                  Hapus
                </Tombol>
              </div>
            ))}
            <Tombol
              type="button"
              variasi="sekunder"
              onClick={() => setSosmed([...sosmed, { platform: '', url: '' }])}
            >
              + Tambah Sosial Media
            </Tombol>
          </div>
        }
      />

      <Tombol type="submit" disabled={simpan.isPending}>
        {simpan.isPending ? 'Menyimpan…' : 'Simpan Pengaturan'}
      </Tombol>
    </form>
  )
}

PengaturanPage.layout = (page: ReactNode) => <LayoutAdmin judul="Pengaturan Umum">{page}</LayoutAdmin>
