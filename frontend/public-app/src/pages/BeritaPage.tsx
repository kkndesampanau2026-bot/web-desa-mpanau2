import { Link, useSearchParams } from 'react-router-dom'
import { CalendarDays, ChevronLeft, ChevronRight, Newspaper } from 'lucide-react'
import { StatusMuat } from '@/components/StatusMuat'
import { ChipFilter, IsiHalaman, Kartu, KepalaHalaman } from '@/components/ui'
import { useBerita, useKategoriBerita } from '@/lib/queries'
import { formatTanggal } from '@/lib/format'

const DESKRIPSI = 'Kabar dan pengumuman terbaru dari Pemerintah Desa Mpanau.'

export function BeritaPage() {
  const [params, setParams] = useSearchParams()
  const kategori = params.get('kategori') ?? undefined
  const halaman = Number(params.get('page') ?? 1)

  const { data, isPending, error } = useBerita({ kategori, page: halaman })
  const { data: daftarKategori } = useKategoriBerita()

  function gantiKategori(slug?: string) {
    const baru = new URLSearchParams(params)
    if (slug) baru.set('kategori', slug)
    else baru.delete('kategori')
    baru.delete('page') // kembali ke halaman 1 saat filter berubah
    setParams(baru)
  }

  function gantiHalaman(ke: number) {
    const baru = new URLSearchParams(params)
    baru.set('page', String(ke))
    setParams(baru)
    window.scrollTo({ top: 0 })
  }

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data?.items.length && !kategori}
      eyebrow="Informasi Desa"
      judul="Berita Desa"
      deskripsi={DESKRIPSI}
    >
      <KepalaHalaman eyebrow="Informasi Desa" judul="Berita Desa" deskripsi={DESKRIPSI} />

      <IsiHalaman lebar="lebar">
        {daftarKategori && daftarKategori.length > 0 && (
          <nav aria-label="Filter kategori" className="mb-8 flex flex-wrap gap-2">
            <ChipFilter aktif={!kategori} onClick={() => gantiKategori()}>
              Semua
            </ChipFilter>
            {daftarKategori.map((k) => (
              <ChipFilter
                key={k.slug}
                aktif={kategori === k.slug}
                onClick={() => gantiKategori(k.slug)}
              >
                {k.nama} ({k.jumlah_berita})
              </ChipFilter>
            ))}
          </nav>
        )}

        {data?.items.length === 0 ? (
          <Kartu className="px-6 py-14 text-center">
            <p className="text-slate-600">Belum ada berita pada kategori ini.</p>
          </Kartu>
        ) : (
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {data?.items.map((berita) => (
              <li key={berita.id}>
                <Link to={`/berita/${berita.slug}`} className="group block h-full">
                  <Kartu interaktif className="flex h-full flex-col overflow-hidden">
                    {berita.gambar_utama ? (
                      <img
                        src={berita.gambar_utama}
                        alt=""
                        loading="lazy"
                        className="h-44 w-full object-cover"
                      />
                    ) : (
                      <div
                        aria-hidden="true"
                        className="grid h-44 w-full place-items-center bg-navy/5 text-navy/20"
                      >
                        <Newspaper className="size-9" />
                      </div>
                    )}

                    <div className="flex flex-1 flex-col p-5">
                      {berita.kategori && (
                        <span className="text-xs font-semibold tracking-wide text-gold-dark uppercase">
                          {berita.kategori.nama}
                        </span>
                      )}

                      <h2 className="font-heading mt-1.5 font-bold text-navy transition group-hover:text-gold-dark">
                        {berita.judul}
                      </h2>

                      {berita.ringkasan && (
                        <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-600">
                          {berita.ringkasan}
                        </p>
                      )}

                      <p className="mt-auto flex items-center gap-1.5 pt-4 text-xs text-slate-500">
                        <CalendarDays className="size-3.5" aria-hidden="true" />
                        {formatTanggal(berita.tanggal_publish)}
                      </p>
                    </div>
                  </Kartu>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {data?.meta && data.meta.last_page > 1 && (
          <nav
            aria-label="Navigasi halaman"
            className="mt-10 flex items-center justify-center gap-3"
          >
            <button
              onClick={() => gantiHalaman(halaman - 1)}
              disabled={halaman <= 1}
              className="inline-flex items-center gap-1 rounded-lg border-2 border-navy/15 bg-white px-4 py-2 text-sm font-semibold text-navy transition hover:border-navy/40 disabled:opacity-40 disabled:hover:border-navy/15"
            >
              <ChevronLeft className="size-4" aria-hidden="true" />
              Sebelumnya
            </button>

            <span className="text-sm text-slate-600 tabular-nums">
              {data.meta.current_page} / {data.meta.last_page}
            </span>

            <button
              onClick={() => gantiHalaman(halaman + 1)}
              disabled={halaman >= data.meta.last_page}
              className="inline-flex items-center gap-1 rounded-lg border-2 border-navy/15 bg-white px-4 py-2 text-sm font-semibold text-navy transition hover:border-navy/40 disabled:opacity-40 disabled:hover:border-navy/15"
            >
              Berikutnya
              <ChevronRight className="size-4" aria-hidden="true" />
            </button>
          </nav>
        )}
      </IsiHalaman>
    </StatusMuat>
  )
}
