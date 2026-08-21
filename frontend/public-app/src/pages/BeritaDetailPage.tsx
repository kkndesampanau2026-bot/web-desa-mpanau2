import { useEffect } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, CalendarDays, Eye, User } from 'lucide-react'
import { KontenKaya } from '@/components/KontenKaya'
import { StatusMuat } from '@/components/StatusMuat'
import { IsiHalaman } from '@/components/ui'
import { useBeritaDetail } from '@/lib/queries'
import { formatAngka, formatTanggal } from '@/lib/format'

export function BeritaDetailPage() {
  const { slug } = useParams<{ slug: string }>()
  const { data: berita, isPending, error } = useBeritaDetail(slug)

  // Meta tag per artikel — PRD 6.10 & 12.4. Public App masih SPA murni,
  // sehingga meta disetel dari sisi klien; bila SEO menjadi prioritas tinggi,
  // PRD 7.2 membuka opsi SSR pada fase lanjutan.
  useEffect(() => {
    if (!berita) return

    const judulSemula = document.title
    document.title = `${berita.meta.title ?? berita.judul} — Desa Mpanau`

    const deskripsi = document.querySelector('meta[name="description"]')
    const deskripsiSemula = deskripsi?.getAttribute('content') ?? null
    if (deskripsi && berita.meta.description) {
      deskripsi.setAttribute('content', berita.meta.description)
    }

    return () => {
      document.title = judulSemula
      if (deskripsi && deskripsiSemula !== null) {
        deskripsi.setAttribute('content', deskripsiSemula)
      }
    }
  }, [berita])

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      eyebrow="Berita"
      judul="Berita Tidak Ditemukan"
      deskripsi="Artikel yang Anda cari tidak tersedia atau belum dipublikasikan."
    >
      {berita && (
        <>
          {/* Kepala artikel bergaya navy: judul berita menjadi H1 halaman ini,
              menggantikan judul modul. */}
          <header className="border-b-4 border-gold bg-navy">
            <div className="mx-auto max-w-3xl px-6 py-12 sm:py-14">
              <Link
                to="/berita"
                className="inline-flex items-center gap-1.5 text-sm font-semibold text-white/70 transition hover:text-gold"
              >
                <ArrowLeft className="size-4" aria-hidden="true" />
                Kembali ke daftar berita
              </Link>

              {berita.kategori && (
                <p className="mt-6 text-xs font-semibold tracking-widest text-gold uppercase">
                  {berita.kategori.nama}
                </p>
              )}

              <h1 className="font-heading mt-2 text-3xl leading-tight font-bold text-white sm:text-4xl">
                {berita.judul}
              </h1>

              <p className="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-white/60">
                <span className="flex items-center gap-1.5">
                  <CalendarDays className="size-4" aria-hidden="true" />
                  {formatTanggal(berita.tanggal_publish)}
                </span>
                {berita.penulis && (
                  <span className="flex items-center gap-1.5">
                    <User className="size-4" aria-hidden="true" />
                    {berita.penulis}
                  </span>
                )}
                <span className="flex items-center gap-1.5">
                  <Eye className="size-4" aria-hidden="true" />
                  {formatAngka(berita.jumlah_dilihat)} kali dilihat
                </span>
              </p>
            </div>
          </header>

          <IsiHalaman lebar="sempit">
            <article>
              {berita.gambar_utama && (
                <img
                  src={berita.gambar_utama}
                  alt=""
                  className="mb-8 w-full rounded-2xl object-cover shadow-sm"
                />
              )}

              <KontenKaya html={berita.konten} />

              {berita.tags && berita.tags.length > 0 && (
                <ul className="mt-10 flex flex-wrap gap-2 border-t border-navy/10 pt-6">
                  {berita.tags.map((tag) => (
                    <li
                      key={tag.slug}
                      className="rounded-full bg-navy/5 px-3 py-1 text-sm text-navy"
                    >
                      #{tag.nama}
                    </li>
                  ))}
                </ul>
              )}
            </article>
          </IsiHalaman>
        </>
      )}
    </StatusMuat>
  )
}
