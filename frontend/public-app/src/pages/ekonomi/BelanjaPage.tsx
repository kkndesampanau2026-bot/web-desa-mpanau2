import { Link, useParams, useSearchParams } from 'react-router-dom'
import { StatusMuat } from '@/components/StatusMuat'
import { useKategoriProduk, useProduk, useProdukDetail } from '@/lib/queries'
import { formatRupiah } from '@/lib/format'
import type { Penjual } from '@/types/api'

const DESKRIPSI = 'Produk unggulan pelaku UMKM desa yang dapat dipesan langsung ke penjual.'

/**
 * Katalog UMKM — PRD 6.12.
 *
 * Katalog saja: pengunjung menelusuri produk lalu menghubungi penjual
 * langsung. Fitur keranjang dan pesan checkout WhatsApp tidak dibuat atas
 * permintaan pemilik produk (lihat DEVIASI A4).
 */
export function BelanjaPage() {
  const [params, setParams] = useSearchParams()
  const kategori = params.get('kategori') ?? undefined
  const cari = params.get('cari') ?? ''
  const halaman = Number(params.get('page') ?? 1)

  const { data, isPending, error } = useProduk({ kategori, cari: cari || undefined, page: halaman })
  const { data: daftarKategori } = useKategoriProduk()

  function perbarui(ubah: (p: URLSearchParams) => void) {
    const baru = new URLSearchParams(params)
    ubah(baru)
    baru.delete('page')
    setParams(baru)
  }

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!data?.items.length && !kategori && !cari}
      judul="Belanja — Katalog UMKM"
      deskripsi={DESKRIPSI}
    >
      <div className="mx-auto max-w-5xl px-6 py-10">
        <h1 className="font-heading text-2xl font-bold text-navy">Belanja</h1>
        <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

        <div className="mt-6 flex flex-wrap items-end gap-4">
          <div className="min-w-56 flex-1">
            <label htmlFor="cari-produk" className="block text-sm font-medium text-slate-700">
              Cari Produk atau Penjual
            </label>
            <input
              id="cari-produk"
              defaultValue={cari}
              onChange={(e) => {
                const nilai = e.target.value
                perbarui((p) => (nilai ? p.set('cari', nilai) : p.delete('cari')))
              }}
              placeholder="mis. keripik atau nama penjual"
              className="mt-1 w-full rounded-lg border border-navy/20 px-3 py-2 text-sm outline-none focus:border-navy"
            />
          </div>
        </div>

        {daftarKategori && daftarKategori.length > 0 && (
          <nav aria-label="Filter kategori" className="mt-4 flex flex-wrap gap-2">
            <Chip aktif={!kategori} onClick={() => perbarui((p) => p.delete('kategori'))}>
              Semua
            </Chip>
            {daftarKategori.map((k) => (
              <Chip
                key={k.kategori}
                aktif={kategori === k.kategori}
                onClick={() => perbarui((p) => p.set('kategori', k.kategori))}
              >
                {k.kategori} ({k.jumlah})
              </Chip>
            ))}
          </nav>
        )}

        {!data?.items.length ? (
          <p className="mt-10 rounded-lg border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
            Tidak ada produk yang cocok dengan pencarian Anda.
          </p>
        ) : (
          <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {data.items.map((p) => (
              <li
                key={p.id}
                className="flex flex-col overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-sm"
              >
                <Link to={`/belanja/${p.slug}`} className="block">
                  {p.foto_utama ? (
                    <img
                      src={p.foto_utama}
                      alt=""
                      loading="lazy"
                      className="h-40 w-full object-cover"
                    />
                  ) : (
                    <div aria-hidden="true" className="h-40 w-full bg-navy/5" />
                  )}
                </Link>

                <div className="flex flex-1 flex-col p-4">
                  {p.kategori && (
                    <span className="text-xs font-medium text-slate-500">{p.kategori}</span>
                  )}

                  <h2 className="mt-1 font-semibold text-navy">
                    <Link to={`/belanja/${p.slug}`} className="hover:underline">
                      {p.nama_produk}
                    </Link>
                  </h2>

                  <p className="mt-1 text-navy">
                    {formatRupiah(p.harga)}
                    {p.satuan && (
                      <span className="text-sm text-slate-500"> / {p.satuan}</span>
                    )}
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

        {data?.meta && data.meta.last_page > 1 && (
          <nav
            aria-label="Navigasi halaman"
            className="mt-10 flex items-center justify-center gap-2"
          >
            <button
              onClick={() => {
                const baru = new URLSearchParams(params)
                baru.set('page', String(halaman - 1))
                setParams(baru)
                window.scrollTo({ top: 0 })
              }}
              disabled={halaman <= 1}
              className="rounded-lg border border-navy/20 px-3 py-1.5 text-sm disabled:opacity-40"
            >
              Sebelumnya
            </button>
            <span className="text-sm text-slate-600">
              Halaman {data.meta.current_page} dari {data.meta.last_page}
            </span>
            <button
              onClick={() => {
                const baru = new URLSearchParams(params)
                baru.set('page', String(halaman + 1))
                setParams(baru)
                window.scrollTo({ top: 0 })
              }}
              disabled={halaman >= data.meta.last_page}
              className="rounded-lg border border-navy/20 px-3 py-1.5 text-sm disabled:opacity-40"
            >
              Berikutnya
            </button>
          </nav>
        )}
      </div>
    </StatusMuat>
  )
}

/** Detail produk. */
export function ProdukDetailPage() {
  const { slug } = useParams<{ slug: string }>()
  const { data: produk, isPending, error } = useProdukDetail(slug)

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      judul="Produk Tidak Ditemukan"
      deskripsi="Produk yang Anda cari tidak tersedia."
    >
      {produk && (
        <div className="mx-auto max-w-3xl px-6 py-10">
          <Link to="/belanja" className="text-sm text-slate-500 hover:text-navy">
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

          <h1 className="mt-1 font-heading text-2xl font-bold text-navy">{produk.nama_produk}</h1>

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
              Pemesanan dan pembayaran dilakukan langsung dengan penjual. Pemerintah
              desa hanya memfasilitasi penayangan produk.
            </p>
          </section>
        </div>
      )}
    </StatusMuat>
  )
}

/**
 * Tautan hubungi penjual.
 *
 * Sengaja hanya membuka percakapan WhatsApp berisi nama produk — bukan
 * ringkasan keranjang atau pesanan, karena modul ini tidak memiliki
 * keranjang (DEVIASI A4).
 */
function TombolHubungi({ penjual, namaProduk }: { penjual: Penjual; namaProduk: string }) {
  if (!penjual.whatsapp_link) {
    return (
      <p className="text-sm text-slate-500">
        Hubungi kantor desa untuk informasi pemesanan.
      </p>
    )
  }

  const pesan = encodeURIComponent(
    `Halo, saya ingin menanyakan produk "${namaProduk}" yang tercantum di website Desa Mpanau.`
  )

  return (
    <a
      href={`${penjual.whatsapp_link}?text=${pesan}`}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-block rounded-lg bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-light"
    >
      Hubungi Penjual
    </a>
  )
}

function Chip({
  aktif,
  onClick,
  children,
}: {
  aktif: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      onClick={onClick}
      aria-current={aktif ? 'true' : undefined}
      className={`rounded-full px-3 py-1 text-sm transition ${
        aktif ? 'bg-navy text-white' : 'bg-navy/5 text-slate-700 hover:bg-navy/10'
      }`}
    >
      {children}
    </button>
  )
}
