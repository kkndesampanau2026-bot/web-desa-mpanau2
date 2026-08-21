import type { ReactNode } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { CalendarDays, ChevronLeft, ChevronRight, Newspaper } from 'lucide-react'
import { EmptyState } from '@/Components/EmptyState'
import { ChipFilter, IsiHalaman, Kartu, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatTanggal } from '@/lib/format'
import type { Berhalaman } from '@/types/inertia'
import type { BeritaRingkas, KategoriBerita } from '@/types/api'

const DESKRIPSI = 'Kabar dan pengumuman terbaru dari Pemerintah Desa Mpanau.'

interface Props {
  berita: Berhalaman<BeritaRingkas>
  kategori: KategoriBerita[]
  filter: { kategori: string | null; cari: string | null }
}

export default function BeritaIndex({ berita, kategori, filter }: Props) {
  /**
   * Filter dan paginasi kini berupa kunjungan ke server, bukan perubahan
   * state lokal yang memicu fetch. `preserveState` menahan bagian halaman
   * yang tidak berubah (mis. posisi gulir daftar kategori) sementara Inertia
   * hanya menukar prop-nya.
   */
  function jelajah(perubahan: Record<string, string | number | undefined>) {
    router.get(
      '/berita',
      {
        kategori: filter.kategori ?? undefined,
        cari: filter.cari ?? undefined,
        ...perubahan,
      },
      { preserveState: true, preserveScroll: true, replace: true },
    )
  }

  // Benar-benar kosong (bukan sekadar filter yang tidak menghasilkan apa-apa)
  // berarti admin desa belum memublikasikan berita sama sekali.
  if (berita.items.length === 0 && !filter.kategori && !filter.cari) {
    return (
      <>
        <Head title="Berita Desa" />
        <EmptyState eyebrow="Informasi Desa" judul="Berita Desa" deskripsi={DESKRIPSI} />
      </>
    )
  }

  return (
    <>
      <Head title="Berita Desa" />

      <KepalaHalaman eyebrow="Informasi Desa" judul="Berita Desa" deskripsi={DESKRIPSI} />

      <IsiHalaman lebar="lebar">
        {kategori.length > 0 && (
          <nav aria-label="Filter kategori" className="mb-8 flex flex-wrap gap-2">
            <ChipFilter
              aktif={!filter.kategori}
              onClick={() => jelajah({ kategori: undefined, page: undefined })}
            >
              Semua
            </ChipFilter>
            {kategori.map((k) => (
              <ChipFilter
                key={k.slug}
                aktif={filter.kategori === k.slug}
                // Halaman sengaja direset: berpindah filter saat berada di
                // halaman 3 hampir selalu berakhir pada daftar kosong.
                onClick={() => jelajah({ kategori: k.slug, page: undefined })}
              >
                {k.nama} ({k.jumlah_berita})
              </ChipFilter>
            ))}
          </nav>
        )}

        {berita.items.length === 0 ? (
          <Kartu className="px-6 py-14 text-center">
            <p className="text-slate-600">Belum ada berita pada kategori ini.</p>
          </Kartu>
        ) : (
          <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {berita.items.map((item) => (
              <li key={item.id}>
                <Link href={`/berita/${item.slug}`} className="group block h-full">
                  <Kartu interaktif className="flex h-full flex-col overflow-hidden">
                    {item.gambar_utama ? (
                      <img
                        src={item.gambar_utama}
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
                      {item.kategori && (
                        <span className="text-xs font-semibold tracking-wide text-gold-dark uppercase">
                          {item.kategori.nama}
                        </span>
                      )}

                      <h2 className="font-heading mt-1.5 font-bold text-navy transition group-hover:text-gold-dark">
                        {item.judul}
                      </h2>

                      {item.ringkasan && (
                        <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-600">
                          {item.ringkasan}
                        </p>
                      )}

                      <p className="mt-auto flex items-center gap-1.5 pt-4 text-xs text-slate-500">
                        <CalendarDays className="size-3.5" aria-hidden="true" />
                        {formatTanggal(item.tanggal_publish)}
                      </p>
                    </div>
                  </Kartu>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {berita.meta.last_page > 1 && (
          <nav
            aria-label="Navigasi halaman"
            className="mt-10 flex items-center justify-center gap-3"
          >
            <button
              onClick={() => jelajah({ page: berita.meta.current_page - 1 })}
              disabled={berita.meta.current_page <= 1}
              className="inline-flex items-center gap-1 rounded-lg border-2 border-navy/15 bg-white px-4 py-2 text-sm font-semibold text-navy transition hover:border-navy/40 disabled:opacity-40 disabled:hover:border-navy/15"
            >
              <ChevronLeft className="size-4" aria-hidden="true" />
              Sebelumnya
            </button>

            <span className="text-sm text-slate-600 tabular-nums">
              {berita.meta.current_page} / {berita.meta.last_page}
            </span>

            <button
              onClick={() => jelajah({ page: berita.meta.current_page + 1 })}
              disabled={berita.meta.current_page >= berita.meta.last_page}
              className="inline-flex items-center gap-1 rounded-lg border-2 border-navy/15 bg-white px-4 py-2 text-sm font-semibold text-navy transition hover:border-navy/40 disabled:opacity-40 disabled:hover:border-navy/15"
            >
              Berikutnya
              <ChevronRight className="size-4" aria-hidden="true" />
            </button>
          </nav>
        )}
      </IsiHalaman>
    </>
  )
}

BeritaIndex.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
