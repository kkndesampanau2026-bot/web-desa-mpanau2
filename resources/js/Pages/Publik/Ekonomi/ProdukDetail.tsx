import type { ReactNode } from 'react'
import { ShoppingBag, Store, Tag } from 'lucide-react'
import { DetailDenganSidebar } from '@/Components/DetailDenganSidebar'
import { SeoMeta } from '@/Components/SeoMeta'
import { TombolHubungi } from '@/Components/TombolHubungi'
import { IsiHalaman, KepalaHalaman, Lencana } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatRupiah } from '@/lib/format'
import { useJelajahPotensi } from '@/lib/tautan'
import type { ProdukDetail as Produk, ProdukRingkas } from '@/types/api'

/** Detail produk UMKM — PRD 6.12. */
export default function ProdukDetail({
  produk,
  produk_lainnya,
  kembali = '/potensi?kategori=Ekonomi',
}: {
  produk: Produk
  /** Produk lain untuk sidebar — sekategori didahulukan. */
  produk_lainnya: ProdukRingkas[]
  /** Alamat katalog tempat pengunjung berangkat, disusun oleh controller. */
  kembali?: string
}) {
  const jelajah = useJelajahPotensi()

  return (
    <>
      <SeoMeta
        title={produk.nama_produk}
        description={produk.deskripsi ?? `Produk UMKM ${produk.nama_produk} oleh ${produk.penjual.nama} di Desa Mpanau. Harga ${formatRupiah(produk.harga)}.`}
        ogImage={produk.foto[0]?.url}
        schema={{
          '@context': 'https://schema.org',
          '@type': 'Product',
          name: produk.nama_produk,
          description: produk.deskripsi ?? undefined,
          image: produk.foto.length > 0 ? produk.foto.map((f) => f.url) : undefined,
          category: produk.kategori ?? undefined,
          offers: {
            '@type': 'Offer',
            price: produk.harga,
            priceCurrency: 'IDR',
            availability: produk.tersedia ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            seller: {
              '@type': 'Person',
              name: produk.penjual.nama,
            },
          },
        }}
      />
      <KepalaHalaman
        lebar="lebar"
        kembali={{ ke: kembali, label: 'Kembali ke katalog' }}
        eyebrow={produk.kategori ?? undefined}
        judul={produk.nama_produk}
      />

      <IsiHalaman lebar="lebar">
        <DetailDenganSidebar
          judulSamping="Produk Lainnya"
          ikonCadangan={ShoppingBag}
          pesanKosong="Belum ada produk lain."
          entri={produk_lainnya.map((p) => ({
            kunci: p.id,
            ke: jelajah.detail(p.slug),
            judul: p.nama_produk,
            gambar: p.foto_utama,
            keterangan: [
              {
                ikon: Tag,
                teks: `${formatRupiah(p.harga)}${p.satuan ? ` / ${p.satuan}` : ''}`,
              },
              { ikon: Store, teks: p.penjual.nama },
            ],
          }))}
        >
          <div className="space-y-8">
            {produk.foto.length > 0 && (
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                {produk.foto.map((f, i) => (
                  <li key={i}>
                    <img
                      src={f.url}
                      alt={f.alt_text}
                      loading="lazy"
                      className="aspect-square w-full rounded-lg object-cover"
                    />
                  </li>
                ))}
              </ul>
            )}

            <div>
              <p className="font-heading text-2xl font-bold text-navy">
                {formatRupiah(produk.harga)}
                {produk.satuan && (
                  <span className="text-base font-normal text-slate-500"> / {produk.satuan}</span>
                )}
              </p>
              <div className="mt-2">
                <Lencana gaya={produk.tersedia ? 'hijau' : 'netral'}>
                  {produk.tersedia ? 'Tersedia' : 'Stok habis'}
                </Lencana>
              </div>
            </div>

            {produk.deskripsi && (
              <p className="leading-relaxed whitespace-pre-line text-slate-700">{produk.deskripsi}</p>
            )}

            <section className="rounded-xl border border-navy/10 bg-navy/3 p-5">
              <h2 className="font-semibold text-navy">Penjual</h2>
              <p className="mt-1 text-slate-700">{produk.penjual.nama}</p>
              {produk.alamat_penjual && (
                <p className="text-sm text-slate-600">{produk.alamat_penjual}</p>
              )}

              <div className="mt-4">
                <TombolHubungi penjual={produk.penjual} namaProduk={produk.nama_produk} />
              </div>

              <p className="mt-3 text-xs text-slate-500">
                Pemesanan dan pembayaran dilakukan langsung dengan penjual. Pemerintah desa hanya
                memfasilitasi penayangan produk.
              </p>
            </section>
          </div>
        </DetailDenganSidebar>
      </IsiHalaman>
    </>
  )
}

ProdukDetail.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
