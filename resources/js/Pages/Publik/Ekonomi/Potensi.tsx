import type { ReactNode } from 'react'
import { Head, router } from '@inertiajs/react'
import { DaftarDestinasi } from '@/Components/DaftarDestinasi'
import { KartuPotensi, KisiPotensi } from '@/Components/KartuPotensi'
import { KatalogUmkm } from '@/Components/KatalogUmkm'
import { ChipFilter, IsiHalaman, KepalaHalaman } from '@/Components/ui'
import { LayoutPublik } from '@/Layouts/LayoutPublik'
import { useJelajahPotensi } from '@/lib/tautan'
import type { Berhalaman } from '@/types/inertia'
import type { DaftarPotensi, KategoriProduk, ProdukRingkas, WisataRingkas } from '@/types/api'

const KATEGORI_WISATA = 'Pariwisata'
const KATEGORI_EKONOMI = 'Ekonomi'

const DESKRIPSI_UMUM =
  'Potensi ekonomi, pariwisata, pertanian, industri kreatif, dan kelestarian lingkungan yang dimiliki desa.'

/**
 * Dua kategori tidak diisi kartu potensi, melainkan modul yang sudah punya
 * datanya sendiri: Pariwisata menampilkan destinasi wisata dan Ekonomi
 * menampilkan katalog UMKM. Keterangan halaman ikut menyesuaikan, kalau tidak
 * pengunjung membaca janji "potensi" lalu menemui daftar produk.
 */
const DESKRIPSI_KATEGORI: Record<string, string> = {
  [KATEGORI_WISATA]: 'Destinasi wisata desa beserta lokasi, jam operasional, dan fasilitasnya.',
  [KATEGORI_EKONOMI]: 'Produk unggulan pelaku UMKM desa yang dapat dipesan langsung ke penjualnya.',
}

interface Props {
  /** Seluruh kategori, bukan hanya yang terisi — lihat `EkonomiController`. */
  kategori: string[]
  /** `jenis` adalah kategori produk pada tab Ekonomi — lihat `EkonomiController`. */
  filter: { kategori: string | null; jenis: string | null; cari: string | null }
  /** Terisi pada kategori biasa. */
  data?: DaftarPotensi | null
  /** Terisi pada kategori Pariwisata. */
  wisata?: WisataRingkas[] | null
  /** Terisi pada kategori Ekonomi. */
  produk?: Berhalaman<ProdukRingkas>
  /** Terisi pada kategori Ekonomi: kategori produk beserta jumlahnya. */
  jenisProduk?: KategoriProduk[]
}

/**
 * Potensi Desa — PRD 6.11.
 *
 * Satu-satunya pintu masuk ke seluruh potensi desa: barisan chip di sini
 * menggantikan menu bercabang di bilah navigasi. Karena itu daftar chipnya
 * tetap lengkap sekalipun sebuah kategori belum berisi — chip yang hilang
 * berarti isinya tidak punya jalan sama sekali untuk dijangkau.
 *
 * Halaman detail tiap isinya juga tinggal di bawah alamat ini, membawa serta
 * query string daftarnya, agar tombol "kembali" di sana pulang ke tab dan
 * penyaring yang tadi dibuka. Alamat-alamat itu disusun `useJelajahPotensi`
 * dari alamat halaman yang sedang terbuka, bukan dioper sebagai prop.
 */
export default function Potensi({ kategori, filter, data, wisata, produk, jenisProduk }: Props) {
  const aktif = filter.kategori
  const jelajah = useJelajahPotensi()

  function gantiKategori(pilihan?: string) {
    router.get(
      jelajah.pindahKategori(pilihan),
      {},
      { preserveState: true, preserveScroll: true, replace: true },
    )
  }

  return (
    <>
      <Head title={aktif ? `Potensi Desa — ${aktif}` : 'Potensi Desa'} />
      <KepalaHalaman
        eyebrow="Potensi & Ekonomi"
        judul="Potensi Desa"
        deskripsi={(aktif && DESKRIPSI_KATEGORI[aktif]) || DESKRIPSI_UMUM}
      />

      <IsiHalaman>
        <nav aria-label="Filter kategori" className="flex flex-wrap gap-2">
          <ChipFilter aktif={!aktif} onClick={() => gantiKategori()}>
            Semua
          </ChipFilter>
          {kategori.map((k) => (
            <ChipFilter key={k} aktif={aktif === k} onClick={() => gantiKategori(k)}>
              {k}
            </ChipFilter>
          ))}
        </nav>

        <div className="mt-8">
          <Isi
            kategori={aktif}
            filter={filter}
            data={data}
            wisata={wisata}
            produk={produk}
            jenisProduk={jenisProduk}
          />
        </div>
      </IsiHalaman>
    </>
  )
}

/** Isi tab yang sedang dibuka. */
function Isi({
  kategori,
  filter,
  data,
  wisata,
  produk,
  jenisProduk,
}: Pick<Props, 'filter' | 'data' | 'wisata' | 'produk' | 'jenisProduk'> & {
  kategori: string | null
}) {
  if (kategori === KATEGORI_WISATA) {
    return wisata?.length ? (
      <DaftarDestinasi wisata={wisata} />
    ) : (
      <PesanKosong>Belum ada destinasi wisata yang ditampilkan.</PesanKosong>
    )
  }

  if (kategori === KATEGORI_EKONOMI) {
    // Pencarian atau penyaring yang tidak menemukan apa pun tetap masuk ke
    // katalog — kotak pencarian dan daftar kategorinya harus ikut tampil
    // supaya pilihannya bisa diperbaiki.
    const sedangMenyaring = Boolean(filter.cari) || Boolean(filter.jenis)

    return produk && (produk.items.length > 0 || sedangMenyaring) ? (
      <KatalogUmkm
        produk={produk}
        cari={filter.cari}
        jenisProduk={jenisProduk}
        jenisAktif={filter.jenis}
      />
    ) : (
      <PesanKosong>Belum ada produk UMKM yang ditampilkan.</PesanKosong>
    )
  }

  return data?.items.length ? (
    <KisiPotensi>
      {data.items.map((p) => (
        <KartuPotensi key={p.id} potensi={p} />
      ))}
    </KisiPotensi>
  ) : (
    <PesanKosong>Belum ada potensi desa pada kategori ini.</PesanKosong>
  )
}

function PesanKosong({ children }: { children: ReactNode }) {
  return (
    <p className="rounded-2xl border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
      {children}
    </p>
  )
}

Potensi.layout = (page: ReactNode) => <LayoutPublik>{page}</LayoutPublik>
