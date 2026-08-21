import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api, ApiRequestError, getData } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/components/Form'
import { InputBerkas } from '@/components/Berkas'

/** Kolom gambar pada profil desa, beserta labelnya di formulir. */
const GAMBAR = [
  ['foto_kepala_desa', 'Foto Kepala Desa'],
  ['bagan_pemerintahan', 'Bagan Struktur Pemerintah Desa'],
  ['bagan_bpd', 'Bagan Struktur BPD'],
] as const

type KolomGambar = (typeof GAMBAR)[number][0]

interface ProfilForm {
  nama_kepala_desa: string
  sambutan: string
  sejarah: string
  visi: string
  misi: string[]
  luas_desa_m2: string
  jumlah_penduduk_manual: string
  batas_utara: string
  batas_timur: string
  batas_selatan: string
  batas_barat: string
  latitude: string
  longitude: string
}

const KOSONG: ProfilForm = {
  nama_kepala_desa: '', sambutan: '', sejarah: '', visi: '', misi: [''],
  luas_desa_m2: '', jumlah_penduduk_manual: '',
  batas_utara: '', batas_timur: '', batas_selatan: '', batas_barat: '',
  latitude: '', longitude: '',
}

/** CMS Profil Desa — PRD 5.3. */
export function ProfilPage() {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<ProfilForm>(KOSONG)
  const [gambar, setGambar] = useState<Record<KolomGambar, File | null>>({
    foto_kepala_desa: null,
    bagan_pemerintahan: null,
    bagan_bpd: null,
  })
  const [galat, setGalat] = useState<ApiRequestError | null>(null)
  const [sukses, setSukses] = useState(false)

  const { data, isPending } = useQuery({
    queryKey: ['admin', 'profil'],
    queryFn: () => getData<Record<string, unknown>>('/admin/profil'),
  })

  useEffect(() => {
    if (!data) return

    // Nilai null dari API diubah menjadi string kosong: input terkendali React
    // akan beralih ke mode tak-terkendali bila value-nya null/undefined.
    setForm({
      ...KOSONG,
      ...Object.fromEntries(
        Object.entries(KOSONG).map(([k]) => [
          k,
          k === 'misi'
            ? ((data.misi as string[] | null)?.length ? (data.misi as string[]) : [''])
            : (data[k] ?? '') === null ? '' : String(data[k] ?? ''),
        ]),
      ),
    } as ProfilForm)
  }, [data])

  const simpan = useMutation({
    mutationFn: (nilai: ProfilForm) =>
      // POST + `_method=PUT`: formulir ini mengirim gambar sebagai multipart,
      // dan PHP tidak mengurai body multipart pada request PUT.
      api.post(
        '/admin/profil',
        keFormData(
          {
            ...nilai,
            misi: nilai.misi.map((m) => m.trim()).filter(Boolean),
            // Field angka dikirim null bila kosong — string kosong akan ditolak
            // oleh aturan validasi `integer`/`numeric` di backend.
            luas_desa_m2: nilai.luas_desa_m2 || null,
            jumlah_penduduk_manual: nilai.jumlah_penduduk_manual || null,
            latitude: nilai.latitude || null,
            longitude: nilai.longitude || null,
            ...gambar,
          },
          { method: 'PUT' },
        ),
      ),
    onSuccess: () => {
      setGalat(null)
      setSukses(true)
      // Berkas yang sudah terkirim dilepas dari state; bila tidak, menyimpan
      // ulang akan mengunggah gambar yang sama untuk kedua kalinya.
      setGambar({ foto_kepala_desa: null, bagan_pemerintahan: null, bagan_bpd: null })
      void queryClient.invalidateQueries({ queryKey: ['admin', 'profil'] })
    },
    onError: (e) => {
      setSukses(false)
      setGalat(e instanceof ApiRequestError ? e : new ApiRequestError('Gagal menyimpan.', 0))
    },
  })

  function ubah<K extends keyof ProfilForm>(kunci: K, nilai: ProfilForm[K]) {
    setForm((f) => ({ ...f, [kunci]: nilai }))
    setSukses(false)
  }

  function kirim(e: FormEvent) {
    e.preventDefault()
    simpan.mutate(form)
  }

  if (isPending) {
    return <p className="text-slate-500">Memuat…</p>
  }

  return (
    <form onSubmit={kirim} className="max-w-3xl space-y-6">
      <div>
        <h1 className="text-xl font-semibold text-slate-900">Profil Desa</h1>
        <p className="mt-1 text-sm text-slate-600">
          Data di halaman ini tampil pada halaman Profil situs publik.
        </p>
      </div>

      {sukses && <Pemberitahuan jenis="sukses" pesan="Profil desa berhasil disimpan." />}
      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <Kartu
        judul="Sambutan & Identitas"
        anak={
          <div className="space-y-4">
            <Kolom label="Nama Kepala Desa" htmlFor="nama_kepala_desa">
              <Input
                id="nama_kepala_desa"
                value={form.nama_kepala_desa}
                onChange={(e) => ubah('nama_kepala_desa', e.target.value)}
              />
            </Kolom>

            <Kolom
              label="Sambutan Kepala Desa"
              htmlFor="sambutan"
              petunjuk="Mendukung HTML sederhana. Isi akan dibersihkan otomatis dari skrip berbahaya."
            >
              <TextArea
                id="sambutan"
                rows={5}
                value={form.sambutan}
                onChange={(e) => ubah('sambutan', e.target.value)}
              />
            </Kolom>

            <Kolom label="Sejarah Desa" htmlFor="sejarah">
              <TextArea
                id="sejarah"
                rows={6}
                value={form.sejarah}
                onChange={(e) => ubah('sejarah', e.target.value)}
              />
            </Kolom>
          </div>
        }
      />

      <Kartu
        judul="Foto & Bagan"
        anak={
          <div className="space-y-5">
            {GAMBAR.map(([kunci, label]) => (
              <InputBerkas
                key={kunci}
                label={label}
                jenis="gambar"
                berkas={gambar[kunci]}
                onPilih={(b) => {
                  setGambar((g) => ({ ...g, [kunci]: b }))
                  setSukses(false)
                }}
                pathTersimpan={data?.[kunci] as string | null}
                galat={galat?.fieldError(kunci)}
              />
            ))}
          </div>
        }
      />

      <Kartu
        judul="Visi & Misi"
        anak={
          <div className="space-y-4">
            <Kolom label="Visi" htmlFor="visi">
              <TextArea
                id="visi"
                rows={2}
                value={form.visi}
                onChange={(e) => ubah('visi', e.target.value)}
              />
            </Kolom>

            <fieldset>
              <legend className="text-sm font-medium text-slate-700">Misi</legend>
              <div className="mt-1 space-y-2">
                {form.misi.map((poin, i) => (
                  <div key={i} className="flex gap-2">
                    <Input
                      aria-label={`Misi poin ${i + 1}`}
                      value={poin}
                      onChange={(e) => {
                        const baru = [...form.misi]
                        baru[i] = e.target.value
                        ubah('misi', baru)
                      }}
                    />
                    <Tombol
                      type="button"
                      variasi="sekunder"
                      onClick={() => ubah('misi', form.misi.filter((_, n) => n !== i))}
                      aria-label={`Hapus misi poin ${i + 1}`}
                    >
                      Hapus
                    </Tombol>
                  </div>
                ))}
                <Tombol
                  type="button"
                  variasi="sekunder"
                  onClick={() => ubah('misi', [...form.misi, ''])}
                >
                  + Tambah Poin Misi
                </Tombol>
              </div>
            </fieldset>
          </div>
        }
      />

      <Kartu
        judul="Data Geografis"
        anak={
          <div className="grid gap-4 sm:grid-cols-2">
            <Kolom
              label="Luas Desa (m²)"
              htmlFor="luas_desa_m2"
              galat={galat?.fieldError('luas_desa_m2')}
            >
              <Input
                id="luas_desa_m2"
                type="number"
                min={0}
                value={form.luas_desa_m2}
                onChange={(e) => ubah('luas_desa_m2', e.target.value)}
                galat={galat?.fieldError('luas_desa_m2')}
              />
            </Kolom>

            <Kolom
              label="Jumlah Penduduk"
              htmlFor="jumlah_penduduk_manual"
              petunjuk="Kosongkan bila ingin memakai data dari modul Kependudukan."
            >
              <Input
                id="jumlah_penduduk_manual"
                type="number"
                min={0}
                value={form.jumlah_penduduk_manual}
                onChange={(e) => ubah('jumlah_penduduk_manual', e.target.value)}
              />
            </Kolom>

            {(['utara', 'timur', 'selatan', 'barat'] as const).map((arah) => (
              <Kolom
                key={arah}
                label={`Batas ${arah.charAt(0).toUpperCase() + arah.slice(1)}`}
                htmlFor={`batas_${arah}`}
              >
                <Input
                  id={`batas_${arah}`}
                  value={form[`batas_${arah}` as keyof ProfilForm] as string}
                  onChange={(e) =>
                    ubah(`batas_${arah}` as keyof ProfilForm, e.target.value as never)
                  }
                />
              </Kolom>
            ))}

            <Kolom
              label="Latitude Kantor Desa"
              htmlFor="latitude"
              petunjuk="Antara -90 dan 90."
              galat={galat?.fieldError('latitude')}
            >
              <Input
                id="latitude"
                value={form.latitude}
                onChange={(e) => ubah('latitude', e.target.value)}
                galat={galat?.fieldError('latitude')}
              />
            </Kolom>

            <Kolom
              label="Longitude Kantor Desa"
              htmlFor="longitude"
              petunjuk="Antara -180 dan 180."
              galat={galat?.fieldError('longitude')}
            >
              <Input
                id="longitude"
                value={form.longitude}
                onChange={(e) => ubah('longitude', e.target.value)}
                galat={galat?.fieldError('longitude')}
              />
            </Kolom>
          </div>
        }
      />

      <Tombol type="submit" disabled={simpan.isPending}>
        {simpan.isPending ? 'Menyimpan…' : 'Simpan Profil'}
      </Tombol>
    </form>
  )
}
