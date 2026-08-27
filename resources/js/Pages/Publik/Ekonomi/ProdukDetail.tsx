import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import { TombolHubungi } from '@/Components/TombolHubungi'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { formatRupiah } from '@/lib/format'
import type { ProdukDetail as Produk } from '@/types/api'

/** Detail produk UMKM — PRD 6.12. */
export default function ProdukDetail({
  produk,
  kembali = '/potensi?kategori=Ekonomi',
}: {
  produk: Produk
  /** Alamat katalog tempat pengunjung berangkat, disusun oleh controller. */
  kembali?: string
}) {
  return (
    <div className="mx-auto max-w-3xl px-6 py-10">
      <Head title={produk.nama_produk} />

      <Link href={kembali} className="text-sm text-slate-500 hover:text-navy">
        ← Kembali ke katalog
      </Link>

      {produk.foto.length > 0 && (
        <ul className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
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

      {produk.kategori && (
        <p className="mt-6 text-sm font-medium text-slate-500">{produk.kategori}</p>
      )}

      <h1 className="font-heading mt-1 text-2xl font-bold text-navy">{produk.nama_produk}</h1>

      <p className="mt-2 text-xl text-navy">
        {formatRupiah(produk.harga)}
        {produk.satuan && <span className="text-base text-slate-500"> / {produk.satuan}</span>}
      </p>

      <p className="mt-1 text-sm font-medium text-slate-600">
        {produk.tersedia ? 'Tersedia' : 'Stok habis'}
      </p>

      {produk.deskripsi && <p className="mt-6 text-slate-700">{produk.deskripsi}</p>}

      <section className="mt-8 rounded-2xl border border-black/5 bg-navy/3 p-5">
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
  )
}

ProdukDetail.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
