import { useState, type FormEvent } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { ArrowLeft, Eye, EyeOff, ShieldCheck } from 'lucide-react'
import { GAYA_INPUT, Kolom, Tombol } from '@/Components/ui'

/**
 * Halaman masuk operator CMS.
 *
 * `useForm` milik Inertia menggantikan tiga hal sekaligus yang dulu ditulis
 * tangan: state tiap field, penanda "sedang mengirim", dan pembongkaran galat
 * validasi dari respons axios. Redirect setelah berhasil pun tidak lagi
 * diputuskan di sini — server yang menentukan tujuannya lewat
 * `redirect()->intended()`.
 *
 * Tampilannya memakai bahasa desain situs publik (navy/emas, heading serif),
 * bukan palet netral dashboard. Halaman ini adalah pintu depan yang terbuka
 * untuk umum — siapa pun bisa membukanya — sehingga ia harus terbaca sebagai
 * bagian dari situs resmi desa, bukan layar login generik. Karena itu pula
 * kelas `area-admin` TIDAK dipasang di sini: kelas itu memaksa seluruh heading
 * memakai huruf antarmuka dashboard dan akan membatalkan huruf serifnya.
 *
 * Primitifnya diambil dari `Components/ui` yang sama dengan formulir publik —
 * `Kolom` sekaligus merangkai `htmlFor`, petunjuk, dan pesan galat, sehingga
 * kaitan aksesibilitasnya tidak perlu ditulis ulang di sini.
 */
interface Identitas {
  nama_desa: string | null
  logo: string | null
  banner: string | null
  wilayah: {
    kelurahan: string | null
    kecamatan: string | null
    kabupaten: string | null
    provinsi: string | null
    kode_pos: string | null
  }
}

export default function Masuk({ identitas }: { identitas: Identitas }) {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
  })

  const [sandiTerlihat, setSandiTerlihat] = useState(false)

  const namaDesa = identitas?.nama_desa ?? 'Desa Mpanau'
  const wilayah = [
    identitas?.wilayah?.kecamatan && `Kecamatan ${identitas.wilayah.kecamatan}`,
    identitas?.wilayah?.kabupaten && `Kabupaten ${identitas.wilayah.kabupaten}`,
    identitas?.wilayah?.provinsi,
  ]
    .filter(Boolean)
    .join(', ')

  function tanganiSubmit(event: FormEvent) {
    event.preventDefault()
    post('/admin/masuk')
  }

  return (
    <div className="min-h-svh bg-cream lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
      <Head title="Masuk Dashboard" />

      {/*
        Panel identitas.

        Fotonya sama dengan hero Beranda dan menempuh jalur yang sama: banner
        unggahan perangkat desa bila ada, selain itu berkas bawaan lewat
        `<picture>` tiga ukuran (varian WebP 800px hanya 49 KB). Lapisan navy
        85% di atasnya menjaga teks putih tetap terbaca di atas foto apa pun.
      */}
      <section className="relative isolate overflow-hidden border-b-4 border-gold bg-navy px-6 py-12 sm:px-10 lg:flex lg:items-center lg:border-b-0 lg:border-r-4 lg:py-16">
        {identitas?.banner ? (
          <img
            src={identitas.banner}
            alt=""
            fetchPriority="high"
            decoding="async"
            className="absolute inset-0 -z-10 size-full object-cover object-center"
          />
        ) : (
          <picture>
            <source
              type="image/webp"
              sizes="(min-width: 1024px) 55vw, 100vw"
              srcSet="/gambar/banner-beranda-800.webp 800w, /gambar/banner-beranda-1200.webp 1200w, /gambar/banner-beranda-1672.webp 1672w"
            />
            <img
              src="/gambar/banner-beranda-1200.jpg"
              sizes="(min-width: 1024px) 55vw, 100vw"
              srcSet="/gambar/banner-beranda-800.jpg 800w, /gambar/banner-beranda-1200.jpg 1200w, /gambar/banner-beranda-1672.jpg 1672w"
              alt=""
              fetchPriority="high"
              decoding="async"
              className="absolute inset-0 -z-10 size-full object-cover object-center"
            />
          </picture>
        )}

        <div aria-hidden="true" className="absolute inset-0 -z-10 bg-navy/85" />

        <div className="mx-auto w-full max-w-lg">
          <div className="flex items-center gap-3">
            {identitas?.logo ? (
              <img
                src={identitas.logo}
                alt=""
                className="size-12 shrink-0 rounded-full object-cover sm:size-14"
              />
            ) : (
              <span
                aria-hidden="true"
                className="font-heading grid size-12 shrink-0 place-items-center rounded-full border-2 border-gold text-base font-bold text-gold sm:size-14"
              >
                DM
              </span>
            )}

            {/*
              Label memakai `gold-light`, bukan `gold`: sama seperti hero
              Beranda, emas tua berkontras hanya ±4,2:1 di atas latar yang
              bercampur foto, sedangkan emas muda aman.
            */}
            <p className="text-[11px] font-semibold tracking-[0.14em] text-gold-light uppercase sm:text-xs">
              Situs Resmi
              <span className="block">Pemerintah Desa</span>
            </p>
          </div>

          <h1 className="font-heading mt-6 text-3xl leading-tight font-bold text-white sm:text-4xl">
            {namaDesa}
          </h1>

          {wilayah && <p className="mt-2 text-sm text-white/75">{wilayah}</p>}

          <p className="mt-8 max-w-md border-l-2 border-gold-light/70 pl-5 text-sm leading-relaxed text-white/70">
            Dashboard ini dipakai perangkat desa untuk mengelola isi situs —
            berita, data kependudukan, transparansi anggaran, hingga menanggapi
            pengaduan warga.
          </p>
        </div>
      </section>

      {/* Panel formulir. */}
      <section className="flex items-center px-6 py-12 sm:px-10 lg:py-16">
        <div className="mx-auto w-full max-w-sm">
          <p className="flex items-center gap-2 text-xs font-semibold tracking-[0.12em] text-gold-dark uppercase">
            <ShieldCheck className="size-4" aria-hidden="true" />
            Area Perangkat Desa
          </p>

          <h2 className="font-heading mt-3 text-2xl font-bold text-navy sm:text-3xl">
            Masuk Dashboard
          </h2>
          <p className="mt-2 text-sm text-slate-600">
            Gunakan akun operator yang diberikan Admin Utama.
          </p>

          <form onSubmit={tanganiSubmit} className="mt-8 space-y-5" noValidate>
            {/*
              Satu tempat untuk dua jenis kegagalan: kredensial salah dan akun
              dinonaktifkan. Keduanya dilempar server sebagai galat validasi
              pada field email, sehingga pesannya muncul tepat di bawah kolom
              yang bersangkutan.
            */}
            <Kolom label="Email" htmlFor="email" galat={errors.email}>
              <input
                id="email"
                type="email"
                autoComplete="username"
                required
                autoFocus
                placeholder="nama@desa.id"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
                aria-invalid={errors.email ? true : undefined}
                aria-describedby={errors.email ? 'email-galat' : undefined}
                className={GAYA_INPUT}
              />
            </Kolom>

            <Kolom label="Kata Sandi" htmlFor="password" galat={errors.password}>
              <div className="relative">
                <input
                  id="password"
                  type={sandiTerlihat ? 'text' : 'password'}
                  autoComplete="current-password"
                  required
                  value={data.password}
                  onChange={(e) => setData('password', e.target.value)}
                  aria-invalid={errors.password ? true : undefined}
                  aria-describedby={errors.password ? 'password-galat' : undefined}
                  className={`${GAYA_INPUT} pr-12`}
                />

                {/*
                  Tombol lihat/sembunyi berada DI DALAM kolom, bukan di
                  sampingnya: kata sandi yang salah ketik adalah penyebab
                  kegagalan masuk paling lazim, dan operator desa kerap
                  mengetiknya dari ponsel.
                */}
                <button
                  type="button"
                  onClick={() => setSandiTerlihat((t) => !t)}
                  aria-pressed={sandiTerlihat}
                  aria-label={sandiTerlihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                  className="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-lg text-slate-500 transition hover:text-navy"
                >
                  {sandiTerlihat ? (
                    <EyeOff className="size-4.5" aria-hidden="true" />
                  ) : (
                    <Eye className="size-4.5" aria-hidden="true" />
                  )}
                </button>
              </div>
            </Kolom>

            <Tombol
              type="submit"
              gaya="utama"
              ukuran="besar"
              disabled={processing}
              className="w-full"
            >
              {processing ? 'Memproses…' : 'Masuk'}
            </Tombol>
          </form>

          {/*
            Tanpa tautan ini halaman masuk menjadi jalan buntu: ia berdiri di
            luar kerangka publik, jadi tidak ada navigasi apa pun di layar.
          */}
          <Link
            href="/"
            className="mt-8 inline-flex items-center gap-1.5 text-sm font-semibold text-navy/70 underline-offset-4 transition hover:text-navy hover:underline"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
            Kembali ke beranda
          </Link>
        </div>
      </section>
    </div>
  )
}
