import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BookOpen, Compass, IdCard, Map, Network, Save } from 'lucide-react'
import { api, ApiRequestError, getData, urlBerkas } from '@/lib/api'
import { keFormData } from '@/lib/berkas'
import { Kartu, Kolom, Input, Pemberitahuan, TextArea, Tombol } from '@/Components/Admin/Form'
import { InputBerkas } from '@/Components/Admin/Berkas'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import type { ReactNode } from 'react'

/**
 * Bagan struktur organisasi yang tampil berdampingan di halaman Profil publik.
 *
 * Dipisahkan dari foto kepala desa karena keduanya gambar lebar yang menjadi
 * satu-satunya sumber bagan pada halaman publik — operator perlu melihat versi
 * yang sedang tayang sebelum menggantinya.
 */
const BAGAN = [
  ['bagan_pemerintahan', 'Bagan Struktur Pemerintah Desa'],
  ['bagan_bpd', 'Bagan Struktur BPD'],
] as const

/** Kolom gambar pada profil desa, beserta labelnya di formulir. */
const GAMBAR = [['foto_kepala_desa', 'Foto Kepala Desa'], ...BAGAN] as const

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
export default function ProfilPage() {
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
            : String(data[k] ?? ''),
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
    <form onSubmit={kirim} className="space-y-6">
      {/* Kepala halaman + aksi simpan, mengikuti desain Figma. */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900">Profil Desa</h1>
          <p className="mt-1 text-sm text-slate-600">Informasi identitas dan sejarah desa</p>
        </div>
        <Tombol type="submit" disabled={simpan.isPending}>
          <Save className="size-4" aria-hidden="true" />
          {simpan.isPending ? 'Menyimpan…' : 'Simpan Perubahan'}
        </Tombol>
      </div>

      {sukses && <Pemberitahuan jenis="sukses" pesan="Profil desa berhasil disimpan." />}
      {galat && !galat.errors && <Pemberitahuan jenis="galat" pesan={galat.message} />}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-6 lg:col-span-2">
          <Kartu
            judul="Identitas & Visi Misi"
            ikon={IdCard}
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
                    rows={4}
                    value={form.sambutan}
                    onChange={(e) => ubah('sambutan', e.target.value)}
                  />
                </Kolom>

                <Kolom label="Visi" htmlFor="visi">
                  <TextArea
                    id="visi"
                    rows={2}
                    value={form.visi}
                    onChange={(e) => ubah('visi', e.target.value)}
                  />
                </Kolom>

                <fieldset>
                  <legend className="text-sm font-medium text-slate-900">Misi</legend>
                  <div className="mt-1.5 space-y-2">
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

                {/* Bagan punya kartunya sendiri di bawah; di sini cukup foto
                    kepala desa yang menyertai sambutan. */}
                <div className="border-t border-slate-100 pt-5">
                  <InputBerkas
                    label="Foto Kepala Desa"
                    jenis="gambar"
                    berkas={gambar.foto_kepala_desa}
                    onPilih={(b) => {
                      setGambar((g) => ({ ...g, foto_kepala_desa: b }))
                      setSukses(false)
                    }}
                    pathTersimpan={data?.foto_kepala_desa as string | null}
                    galat={galat?.fieldError('foto_kepala_desa')}
                  />
                </div>
              </div>
            }
          />

          <Kartu
            judul="Bagan Struktur Organisasi"
            ikon={Network}
            anak={
              <div className="space-y-6">
                <p className="text-sm text-slate-600">
                  Kedua bagan tampil sebagai gambar berdampingan di halaman Profil publik.
                  Gunakan gambar mendatar (landscape) agar kotak jabatan tetap terbaca;
                  mengunggah berkas baru otomatis menggantikan bagan yang lama.
                </p>

                <div className="grid gap-6 md:grid-cols-2">
                  {BAGAN.map(([kunci, label]) => (
                    <div key={kunci} className="space-y-3">
                      <PratinjauBagan
                        label={label}
                        berkas={gambar[kunci]}
                        pathTersimpan={data?.[kunci] as string | null}
                      />

                      <InputBerkas
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
                    </div>
                  ))}
                </div>
              </div>
            }
          />

          <Kartu
            judul="Sejarah & Geografis"
            ikon={BookOpen}
            anak={
              <div className="space-y-4">
                <Kolom label="Sejarah Singkat Desa" htmlFor="sejarah">
                  <TextArea
                    id="sejarah"
                    rows={6}
                    value={form.sejarah}
                    onChange={(e) => ubah('sejarah', e.target.value)}
                  />
                </Kolom>

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
              </div>
            }
          />
        </div>

        <div className="space-y-6">
          <Kartu
            judul="Batas Wilayah"
            ikon={Compass}
            anak={
              <div className="space-y-4">
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

                <div className="grid h-40 place-items-center rounded-lg border border-slate-200 bg-[#d3e4fe]/40 text-center text-slate-400">
                  <div className="px-4">
                    <Map className="mx-auto size-8" aria-hidden="true" />
                    <p className="mt-1 text-xs">
                      Titik peta publik ditentukan oleh koordinat Latitude &amp; Longitude.
                    </p>
                  </div>
                </div>
              </div>
            }
          />
        </div>
      </div>
    </form>
  )
}

/**
 * Pratinjau bagan seukuran layak baca — berkas pilihan baru bila ada, kalau
 * tidak bagan yang sedang tayang.
 *
 * InputBerkas hanya memajang thumbnail 64 px yang dipotong persegi; untuk bagan
 * mendatar itu tidak cukup untuk memastikan gambar yang benar sudah terpasang.
 * Object URL dicabut saat berkas berganti agar sesi CMS yang panjang tidak
 * menahan puluhan gambar di memori.
 */
function PratinjauBagan({
  label,
  berkas,
  pathTersimpan,
}: {
  label: string
  berkas: File | null
  pathTersimpan: string | null | undefined
}) {
  const [pratinjau, setPratinjau] = useState<string | null>(null)

  useEffect(() => {
    if (!berkas) {
      setPratinjau(null)
      return
    }

    const url = URL.createObjectURL(berkas)
    setPratinjau(url)

    return () => URL.revokeObjectURL(url)
  }, [berkas])

  const sumber = pratinjau ?? urlBerkas(pathTersimpan)

  return (
    <figure className="rounded-lg border border-slate-200 bg-slate-50 p-3">
      {sumber ? (
        <img
          src={sumber}
          alt={pratinjau ? `Pratinjau ${label}` : `${label} yang sedang tayang`}
          className="mx-auto max-h-48 w-full object-contain"
        />
      ) : (
        <div className="grid h-48 place-items-center px-4 text-center text-sm text-slate-400">
          Belum ada bagan. Bagian ini tidak tampil di halaman Profil publik sampai
          gambarnya diunggah.
        </div>
      )}

      {sumber && (
        <figcaption className="mt-2 text-center text-xs text-slate-500">
          {pratinjau ? 'Pratinjau berkas baru — belum tersimpan.' : 'Bagan yang tayang saat ini.'}
        </figcaption>
      )}
    </figure>
  )
}

ProfilPage.layout = (page: ReactNode) => <LayoutAdmin judul="Profil Desa">{page}</LayoutAdmin>
