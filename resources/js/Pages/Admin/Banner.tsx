import { useState, type FormEvent, type ReactNode } from 'react'
import { router } from '@inertiajs/react'
import { ArrowDown, ArrowUp, Images, Trash2, Upload } from 'lucide-react'
import { Kartu, Kolom, Input, Pemberitahuan, Tombol } from '@/Components/Admin/Form'
import { InputBanyakGambar } from '@/Components/Admin/Berkas'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import { useHalaman } from '@/types/inertia'

/**
 * CMS Banner Beranda.
 *
 * Menggantikan satu kolom "Banner Beranda" yang dulu menumpang di formulir
 * Pengaturan Umum. Unggah dan hapus gambar dipisahkan dari formulir itu dengan
 * sengaja: formulir Pengaturan menyimpan seluruh isinya sekaligus, sehingga
 * kegagalan pada satu gambar akan ikut membatalkan alamat kantor dan jam kerja
 * yang sudah benar.
 *
 * Urutan diubah lewat tombol naik/turun yang MENUKAR dua baris bertetangga di
 * server, bukan dengan mengirim ulang seluruh daftar. Daftar di layar bisa
 * kedaluwarsa — operator lain menghapus satu baris di sela-selanya — dan
 * menuliskannya utuh akan menghidupkan kembali baris yang sudah hilang.
 */
interface Banner {
  id: number
  url: string
  judul: string | null
}

export default function BannerPage({
  daftar_banner: daftar,
  maks_banner: maks,
}: {
  daftar_banner: Banner[]
  maks_banner: number
}) {
  const { flash, errors } = useHalaman().props
  const [pilihan, setPilihan] = useState<File[]>([])
  const [judulSunting, setJudulSunting] = useState<Record<number, string>>({})

  const [mengunggah, setMengunggah] = useState(false)
  const sisa = Math.max(0, maks - daftar.length)

  function kirimUnggahan(event: FormEvent) {
    event.preventDefault()

    // `router.post` dipakai langsung, bukan `unggah.post`, karena payload-nya
    // FormData berisi banyak berkas — bentuk yang tidak dipegang state useForm.
    const formData = new FormData()
    pilihan.forEach((berkas) => formData.append('gambar[]', berkas))

    router.post('/admin/banner', formData, {
      preserveScroll: true,
      forceFormData: true,
      onStart: () => setMengunggah(true),
      onFinish: () => setMengunggah(false),
      onSuccess: () => setPilihan([]),
    })
  }

  function geser(banner: Banner, arah: 'naik' | 'turun') {
    router.put(`/admin/banner/${banner.id}/geser`, { arah }, { preserveScroll: true })
  }

  function simpanJudul(banner: Banner) {
    router.put(
      `/admin/banner/${banner.id}`,
      { judul: judulSunting[banner.id] ?? '' },
      {
        preserveScroll: true,
        onSuccess: () =>
          setJudulSunting((sebelum) => {
            const sesudah = { ...sebelum }
            delete sesudah[banner.id]
            return sesudah
          }),
      },
    )
  }

  function hapus(banner: Banner) {
    if (!confirm('Hapus banner ini? Berkas gambarnya ikut terhapus.')) return
    router.delete(`/admin/banner/${banner.id}`, { preserveScroll: true })
  }

  return (
    <div className="space-y-6">
      {flash?.sukses && <Pemberitahuan jenis="sukses" pesan={flash.sukses} />}
      {errors?.gambar && <Pemberitahuan jenis="galat" pesan={errors.gambar} />}

      <Kartu
        judul="Tambah Banner"
        ikon={Upload}
        anak={
          <form onSubmit={kirimUnggahan} className="space-y-5" noValidate>
            <InputBanyakGambar
              label="Gambar Banner"
              berkas={pilihan}
              onUbah={setPilihan}
              maks={sisa}
              petunjuk={
                sisa === 0
                  ? `Jumlah banner sudah mencapai batas ${maks}. Hapus salah satu lebih dulu.`
                  : `Pilih foto MENDATAR beresolusi tinggi (minimal 1600×900). Bagian tengahnya yang paling terlihat, dan sebuah lapisan gelap dipasang di atasnya agar teks tetap terbaca. Tersisa ruang untuk ${sisa} gambar lagi.`
              }
              galat={errors?.gambar}
            />

            <div className="border-t border-slate-200 pt-5">
              <Tombol type="submit" disabled={pilihan.length === 0 || mengunggah}>
                <Upload className="size-4" aria-hidden="true" />
                {mengunggah ? 'Mengunggah…' : 'Unggah'}
              </Tombol>
            </div>
          </form>
        }
      />

      <Kartu
        judul={`Banner Aktif (${daftar.length}/${maks})`}
        ikon={Images}
        anak={
          daftar.length === 0 ? (
            <div className="py-6 text-center">
              <p className="text-sm text-slate-500">Belum ada banner yang diunggah.</p>
              <p className="mt-1 text-xs text-slate-400">
                Selama daftar ini kosong, beranda memakai foto bawaan situs.
              </p>
            </div>
          ) : (
            <ol className="space-y-3">
              {daftar.map((banner, i) => (
                <li
                  key={banner.id}
                  className="flex flex-wrap items-start gap-4 rounded-xl border border-slate-200 p-3"
                >
                  <img
                    src={banner.url}
                    alt=""
                    className="h-24 w-40 shrink-0 rounded-lg bg-slate-100 object-cover"
                  />

                  <div className="min-w-0 flex-1 space-y-2">
                    <p className="text-xs font-semibold tracking-wide text-slate-400 uppercase">
                      Urutan ke-{i + 1}
                    </p>

                    <Kolom
                      label="Keterangan"
                      htmlFor={`judul-${banner.id}`}
                      petunjuk="Opsional. Tampil di atas gambar dan dipakai sebagai teks alternatif."
                    >
                      <div className="flex flex-wrap items-start gap-2">
                        {/*
                          Dibungkus div: `Input` menuliskan className-nya
                          sendiri SESUDAH spread props, sehingga kelas tata
                          letak yang dioper lewat props diam-diam terbuang.
                        */}
                        <div className="min-w-48 flex-1">
                          <Input
                            id={`judul-${banner.id}`}
                            value={judulSunting[banner.id] ?? banner.judul ?? ''}
                            onChange={(e) =>
                              setJudulSunting((s) => ({ ...s, [banner.id]: e.target.value }))
                            }
                          />
                        </div>
                        {judulSunting[banner.id] !== undefined && (
                          <Tombol type="button" onClick={() => simpanJudul(banner)}>
                            Simpan
                          </Tombol>
                        )}
                      </div>
                    </Kolom>
                  </div>

                  <div className="flex shrink-0 items-center gap-1">
                    <button
                      type="button"
                      onClick={() => geser(banner, 'naik')}
                      disabled={i === 0}
                      aria-label={`Naikkan urutan banner ke-${i + 1}`}
                      className="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                      <ArrowUp className="size-4" aria-hidden="true" />
                    </button>
                    <button
                      type="button"
                      onClick={() => geser(banner, 'turun')}
                      disabled={i === daftar.length - 1}
                      aria-label={`Turunkan urutan banner ke-${i + 1}`}
                      className="rounded-md p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                      <ArrowDown className="size-4" aria-hidden="true" />
                    </button>
                    <button
                      type="button"
                      onClick={() => hapus(banner)}
                      aria-label={`Hapus banner ke-${i + 1}`}
                      className="rounded-md p-1.5 text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                    >
                      <Trash2 className="size-4" aria-hidden="true" />
                    </button>
                  </div>
                </li>
              ))}
            </ol>
          )
        }
      />
    </div>
  )
}

BannerPage.layout = (page: ReactNode) => (
  <LayoutAdmin judul="Banner Beranda">{page}</LayoutAdmin>
)
