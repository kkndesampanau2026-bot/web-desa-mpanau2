import { useEffect, useRef, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import { TombolHubungi } from '@/Components/TombolHubungi'
import { Pilihan } from '@/Components/ui'
import { formatRupiah } from '@/lib/format'
import { useJelajahPotensi, type Jelajah } from '@/lib/tautan'
import type { Berhalaman } from '@/types/inertia'
import type { KategoriProduk, ProdukRingkas } from '@/types/api'

interface Props {
  produk: Berhalaman<ProdukRingkas>
  cari: string | null
  /** Kategori produk beserta jumlahnya, untuk penyaring di atas katalog. */
  jenisProduk?: KategoriProduk[]
  jenisAktif?: string | null
}

/**
 * Katalog produk UMKM — PRD 6.12.
 *
 * Katalog saja: pengunjung menelusuri produk lalu menghubungi penjual
 * langsung. Fitur keranjang dan pesan checkout WhatsApp tidak dibuat atas
 * permintaan pemilik produk (lihat DEVIASI A4).
 */
export function KatalogUmkm({ produk, cari: cariTersimpan, jenisProduk, jenisAktif = null }: Props) {
  const jelajah = useJelajahPotensi()
  const [cari, setCari] = useState(cariTersimpan ?? '')

  /**
   * Kata kunci yang sudah terwakili oleh daftar di layar.
   *
   * Sengaja bukan penanda "baru pertama kali dijalankan": efek di React
   * dijalankan dua kali saat pemasangan di mode pengembangan, dan penanda
   * seperti itu lolos pada jalan kedua — mengirim kunjungan yang membuang
   * nomor halaman, tepat sesudah tombol "kembali" memulihkannya.
   */
  const sudahDicari = useRef(cariTersimpan ?? '')

  /**
   * Pencarian ditunda 350 ms setelah ketikan terakhir.
   *
   * Dulu setiap penekanan tombol langsung mengubah query string; sejak daftar
   * ini dirender server, pola itu berarti satu kunjungan penuh per huruf.
   * Penundaan ini yang menahannya.
   */
  useEffect(() => {
    if (cari === sudahDicari.current) {
      return
    }

    const timer = setTimeout(() => {
      sudahDicari.current = cari
      telusuri({ cari: cari || undefined, page: undefined })
    }, 350)

    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cari])

  function telusuri(perubahan: Jelajah) {
    router.get(
      jelajah.daftar(perubahan),
      {},
      { preserveState: true, preserveScroll: true, replace: true },
    )
  }

  return (
    <>
      <div className="flex flex-wrap items-end gap-4">
        <div className="min-w-56 flex-1">
          <label htmlFor="cari-produk" className="block text-sm font-medium text-slate-700">
            Cari Produk atau Penjual
          </label>
          <input
            id="cari-produk"
            value={cari}
            onChange={(e) => setCari(e.target.value)}
            placeholder="mis. keripik atau nama penjual"
            className="mt-1 w-full rounded-lg border border-navy/20 px-3 py-2 text-sm outline-none focus:border-navy"
          />
        </div>

        {/* Penyaring kategori produk sengaja berupa daftar pilihan, bukan
            barisan chip: halaman induknya sudah punya barisan chip sendiri,
            dan dua baris chip berdampingan hanya membuat pengunjung
            menebak-nebak yang mana menyaring apa. */}
        {jenisProduk && jenisProduk.length > 0 && (
          <div className="min-w-48">
            <label htmlFor="jenis-produk" className="block text-sm font-medium text-slate-700">
              Kategori Produk
            </label>
            <div className="mt-1">
              <Pilihan
                id="jenis-produk"
                value={jenisAktif ?? ''}
                onChange={(v) => telusuri({ jenis: v || undefined, page: undefined })}
                options={[
                  { value: '', label: 'Semua kategori' },
                  ...jenisProduk.map((j) => ({
                    value: j.kategori,
                    label: `${j.kategori} (${j.jumlah})`,
                  })),
                ]}
              />
            </div>
          </div>
        )}
      </div>

      {produk.items.length === 0 ? (
        <p className="mt-10 rounded-lg border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
          Tidak ada produk yang cocok dengan pencarian Anda.
        </p>
      ) : (
        <ul className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:gap-6 2xl:grid-cols-4">
          {produk.items.map((p) => (
            <li
              key={p.id}
              className="flex flex-col overflow-hidden rounded-xl border border-navy/10 bg-white shadow-sm transition hover:shadow-sm"
            >
              <Link href={jelajah.detail(p.slug)} className="block">
                {p.foto_utama ? (
                  <img
                    src={p.foto_utama}
                    alt=""
                    loading="lazy"
                    className="aspect-[4/3] w-full object-cover"
                  />
                ) : (
                  <div aria-hidden="true" className="aspect-[4/3] w-full bg-navy/5" />
                )}
              </Link>

              <div className="flex flex-1 flex-col p-4">
                {p.kategori && (
                  <span className="text-xs font-medium text-slate-500">{p.kategori}</span>
                )}

                <h2 className="mt-1 font-semibold text-navy">
                  <Link href={jelajah.detail(p.slug)} className="hover:underline">
                    {p.nama_produk}
                  </Link>
                </h2>

                <p className="mt-1 text-navy">
                  {formatRupiah(p.harga)}
                  {p.satuan && <span className="text-sm text-slate-500"> / {p.satuan}</span>}
                </p>

                {/* Ketersediaan dinyatakan dengan teks, bukan warna saja. */}
                {!p.tersedia && (
                  <p className="mt-1 text-sm font-medium text-slate-500">Stok habis</p>
                )}

                <p className="mt-2 text-sm text-slate-600">{p.penjual.nama}</p>

                <div className="mt-auto pt-4">
                  <TombolHubungi penjual={p.penjual} namaProduk={p.nama_produk} />
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      {produk.meta.last_page > 1 && (
        <nav aria-label="Navigasi halaman" className="mt-10 flex items-center justify-center gap-2">
          <button
            onClick={() => telusuri({ page: produk.meta.current_page - 1 })}
            disabled={produk.meta.current_page <= 1}
            className="rounded-lg border border-navy/20 px-3 py-1.5 text-sm disabled:opacity-40"
          >
            Sebelumnya
          </button>
          <span className="text-sm text-slate-600">
            Halaman {produk.meta.current_page} dari {produk.meta.last_page}
          </span>
          <button
            onClick={() => telusuri({ page: produk.meta.current_page + 1 })}
            disabled={produk.meta.current_page >= produk.meta.last_page}
            className="rounded-lg border border-navy/20 px-3 py-1.5 text-sm disabled:opacity-40"
          >
            Berikutnya
          </button>
        </nav>
      )}
    </>
  )
}
