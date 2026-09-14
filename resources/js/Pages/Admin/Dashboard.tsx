import type { ReactNode } from 'react'
import { Head, Link } from '@inertiajs/react'
import {
  ArrowRight,
  CalendarDays,
  ExternalLink,
  Eye,
  FileSignature,
  FileText,
  Inbox,
  MapPin,
  MessageSquareWarning,
  Newspaper,
  Store,
  Users,
  UsersRound,
  type LucideIcon,
} from 'lucide-react'
import { Kartu } from '@/Components/Admin/Form'
import { LayoutAdmin } from '@/Layouts/LayoutAdmin'
import { useHalaman } from '@/types/inertia'
import type { StatistikKunjungan } from '@/types/api'

/**
 * Beranda dashboard CMS — PRD 5.20.
 *
 * Disusun menurut urutan pertanyaan operator saat baru masuk:
 *
 * 1. *Ada yang menunggu saya?* — antrean layanan warga, paling atas.
 * 2. *Situsnya dikunjungi orang?* — ringkasan kunjungan.
 * 3. *Isinya bagaimana?* — jumlah data per modul dan berita terakhir.
 *
 * Seluruh isinya sudah disaring per izin di `DashboardController` dan
 * `NotifikasiAdmin`; tidak ada satu pun bagian di bawah yang menyembunyikan
 * sesuatu hanya dengan `punyaIzin()` di sisi klien.
 */

/** Satu kartu angka pada baris "Ringkasan Data" — bentuknya dari server. */
interface KartuRingkasan {
  kunci: string
  label: string
  nilai: number
  satuan: string
  keterangan?: string | null
  ke: string
}

interface BeritaRingkas {
  id: number
  judul: string
  status: string
  tanggal_publish: string | null
  jumlah_dilihat: number
}

/** Satu hari pada grafik tren. Hari sepi pun hadir, dengan jumlah 0. */
interface TitikTren {
  tanggal: string
  jumlah: number
}

interface PropsDashboard {
  kunjungan: StatistikKunjungan
  tren_kunjungan: TitikTren[]
  ringkasan: KartuRingkasan[]
  /** Null bila operator tidak berwenang atas modul Berita. */
  berita_terbaru: BeritaRingkas[] | null
}

/** Ikon per kartu ringkasan. Dipetakan di klien — server mengirim data, bukan tampilan. */
const IKON_RINGKASAN: Record<string, LucideIcon> = {
  penduduk: UsersRound,
  berita: Newspaper,
  potensi: Store,
  produk: Store,
  peta: MapPin,
  surat: FileSignature,
  pengaduan: MessageSquareWarning,
}

/** Ikon per grup antrean, sepadan dengan kunci dari `NotifikasiAdmin`. */
const IKON_ANTREAN: Record<string, LucideIcon> = {
  pengaduan: MessageSquareWarning,
  ppid: FileText,
  surat: FileSignature,
}

const LABEL_STATUS_BERITA: Record<string, string> = {
  draft: 'Draf',
  terjadwal: 'Terjadwal',
  published: 'Tayang',
  diarsipkan: 'Diarsipkan',
}

const GAYA_STATUS_BERITA: Record<string, string> = {
  draft: 'bg-slate-100 text-slate-600',
  terjadwal: 'bg-amber-100 text-amber-700',
  published: 'bg-emerald-100 text-emerald-700',
  diarsipkan: 'bg-slate-100 text-slate-500',
}

function angka(n: number) {
  return n.toLocaleString('id-ID')
}

function tanggalSingkat(iso: string | null) {
  if (!iso) return null

  return new Date(iso).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export default function Dashboard({
  kunjungan,
  tren_kunjungan,
  ringkasan,
  berita_terbaru,
}: PropsDashboard) {
  const { props } = useHalaman()
  const pengguna = props.auth.user
  const notifikasi = props.notifikasi

  // Grup kosong tetap ditampilkan di sini — berbeda dari lonceng. Pada
  // dashboard "Pengaduan Baru — 0" justru kabar baik yang ingin dibaca
  // operator; yang dihindari lonceng adalah daftar panjang tanpa isi.
  const antrean = notifikasi?.grup ?? []
  const totalAntrean = notifikasi?.jumlah ?? 0

  const hariIni = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })

  return (
    <div className="space-y-6">
      <Head title="Dashboard" />

      {/* Sapaan — sekaligus menjawab "saya masuk sebagai siapa?". */}
      <section className="overflow-hidden rounded-xl bg-linear-to-br from-teal-700 to-teal-600 px-6 py-5 text-white shadow-sm">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div className="min-w-0">
            <p className="text-sm text-teal-100">
              <CalendarDays className="mr-1.5 inline size-4 align-[-2px]" aria-hidden="true" />
              {hariIni}
            </p>
            <h1 className="mt-1 truncate text-2xl font-bold tracking-tight">
              Selamat datang, {pengguna?.nama ?? 'Operator'}
            </h1>
            <p className="mt-1 text-sm text-teal-50">
              {totalAntrean > 0
                ? `Ada ${angka(totalAntrean)} permintaan warga yang menunggu ditangani.`
                : 'Tidak ada permintaan warga yang menunggu saat ini.'}
            </p>
          </div>

          <a
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-sm font-medium backdrop-blur transition hover:bg-white/25"
          >
            Lihat Situs Desa
            <ExternalLink className="size-4" aria-hidden="true" />
          </a>
        </div>
      </section>

      {/*
        Kunjungan situs.

        Angka pembanding ditulis apa adanya ("Kemarin 128"), TANPA panah naik
        atau persentase. Periode berjalan selalu belum penuh — membandingkan
        bulan ini yang baru berjalan seminggu dengan bulan lalu yang utuh akan
        menampilkan panah merah setiap awal bulan, dan operator belajar
        mengabaikannya.
      */}
      <section aria-labelledby="judul-kunjungan" className="space-y-3">
        <h2 id="judul-kunjungan" className="text-sm font-semibold text-slate-700">
          Kunjungan Situs
        </h2>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <UbinKunjungan label="Hari Ini" nilai={kunjungan.hari_ini} bandingLabel="Kemarin" bandingNilai={kunjungan.kemarin} />
          <UbinKunjungan label="Minggu Ini" nilai={kunjungan.minggu_ini} bandingLabel="Minggu lalu" bandingNilai={kunjungan.minggu_lalu} />
          <UbinKunjungan label="Bulan Ini" nilai={kunjungan.bulan_ini} bandingLabel="Bulan lalu" bandingNilai={kunjungan.bulan_lalu} />
          <UbinKunjungan label="Total Kunjungan" nilai={kunjungan.total} bandingLabel="Sejak situs aktif" />
        </div>

        <TrenKunjungan data={tren_kunjungan} />
      </section>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
        {/* Antrean layanan warga — pekerjaan yang punya tenggat. */}
        {notifikasi && (
          <Kartu
            judul="Menunggu Ditangani"
            ikon={Inbox}
            anak={
              <div className="space-y-3">
                {antrean.map((grup) => {
                  const Ikon = IKON_ANTREAN[grup.kunci] ?? Inbox
                  const kosong = grup.jumlah === 0

                  return (
                    <section
                      key={grup.kunci}
                      aria-label={grup.label}
                      className="rounded-lg border border-slate-200"
                    >
                      <div className="flex items-center gap-3 px-4 py-3">
                        <span
                          className={`grid size-9 shrink-0 place-items-center rounded-lg ${
                            kosong ? 'bg-slate-100 text-slate-400' : 'bg-teal-50 text-teal-700'
                          }`}
                        >
                          <Ikon className="size-4.5" aria-hidden="true" />
                        </span>

                        <span className="min-w-0 flex-1">
                          <span className="block text-sm font-semibold text-slate-900">
                            {grup.label}
                          </span>
                          <span className="block text-xs text-slate-500">{grup.keterangan}</span>
                        </span>

                        <span
                          className={`shrink-0 rounded-full px-2.5 py-1 text-sm font-semibold ${
                            kosong ? 'bg-slate-100 text-slate-500' : 'bg-rose-600 text-white'
                          }`}
                        >
                          {angka(grup.jumlah)}
                        </span>
                      </div>

                      {grup.item.length > 0 && (
                        <ul className="border-t border-slate-100">
                          {grup.item.map((item, i) => (
                            <li key={`${grup.kunci}-${i}`} className="border-t border-slate-100 first:border-t-0">
                              <Link href={item.ke} className="block px-4 py-2.5 transition hover:bg-slate-50">
                                <span className="flex items-center justify-between gap-2">
                                  <span className="truncate text-sm font-medium text-slate-900">
                                    {item.judul}
                                  </span>
                                  <span className="shrink-0 text-xs text-slate-400">{item.waktu}</span>
                                </span>
                                <span className="mt-0.5 block truncate text-xs text-slate-500">
                                  {item.ringkas}
                                </span>
                              </Link>
                            </li>
                          ))}
                        </ul>
                      )}

                      {!kosong && (
                        <div className="border-t border-slate-100 px-4 py-2">
                          <Link
                            href={grup.ke}
                            className="inline-flex items-center gap-1 text-sm font-medium text-teal-700 hover:underline"
                          >
                            Buka {grup.label.toLowerCase()}
                            <ArrowRight className="size-3.5" aria-hidden="true" />
                          </Link>
                        </div>
                      )}
                    </section>
                  )
                })}
              </div>
            }
          />
        )}

        <div className="space-y-6">
          {/* Ringkasan isi situs. */}
          {ringkasan.length > 0 && (
            <Kartu
              judul="Ringkasan Data"
              ikon={Users}
              anak={
                <div className="grid gap-3 sm:grid-cols-2">
                  {ringkasan.map((kartu) => {
                    const Ikon = IKON_RINGKASAN[kartu.kunci] ?? Users

                    return (
                      <Link
                        key={kartu.kunci}
                        href={kartu.ke}
                        className="group rounded-lg border border-slate-200 p-4 transition hover:border-teal-300 hover:bg-teal-50/40"
                      >
                        <span className="flex items-center gap-2 text-xs font-medium text-slate-500">
                          <Ikon className="size-4 shrink-0 text-teal-700" aria-hidden="true" />
                          {kartu.label}
                        </span>

                        <span className="mt-2 flex items-baseline gap-1.5">
                          <span className="text-2xl font-bold tracking-tight text-slate-900 tabular-nums">
                            {angka(kartu.nilai)}
                          </span>
                          <span className="text-xs text-slate-500">{kartu.satuan}</span>
                        </span>

                        {kartu.keterangan && (
                          <span className="mt-1 block text-xs font-medium text-amber-700">
                            {kartu.keterangan}
                          </span>
                        )}
                      </Link>
                    )
                  })}
                </div>
              }
            />
          )}

          {/* Berita terakhir disunting — penanda apakah situs masih dirawat. */}
          {berita_terbaru && (
            <Kartu
              judul="Berita Terakhir"
              ikon={Newspaper}
              anak={
                berita_terbaru.length === 0 ? (
                  <div className="py-6 text-center">
                    <p className="text-sm font-medium text-slate-900">Belum Ada Berita</p>
                    <p className="mt-1 text-sm text-slate-500">
                      Kabar desa yang dimuat di sini akan tampil di halaman depan situs.
                    </p>
                    <Link
                      href="/admin/berita"
                      className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-teal-700 hover:underline"
                    >
                      Tulis berita pertama
                      <ArrowRight className="size-3.5" aria-hidden="true" />
                    </Link>
                  </div>
                ) : (
                  <>
                    <ul className="-my-1 divide-y divide-slate-100">
                      {berita_terbaru.map((berita) => (
                        <li key={berita.id} className="py-2.5">
                          <p className="truncate text-sm font-medium text-slate-900">
                            {berita.judul}
                          </p>
                          <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                            <span
                              className={`rounded-full px-2 py-0.5 font-medium ${
                                GAYA_STATUS_BERITA[berita.status] ?? 'bg-slate-100 text-slate-600'
                              }`}
                            >
                              {LABEL_STATUS_BERITA[berita.status] ?? berita.status}
                            </span>
                            {tanggalSingkat(berita.tanggal_publish) && (
                              <span>{tanggalSingkat(berita.tanggal_publish)}</span>
                            )}
                            <span className="inline-flex items-center gap-1">
                              <Eye className="size-3.5" aria-hidden="true" />
                              {angka(berita.jumlah_dilihat)}
                            </span>
                          </p>
                        </li>
                      ))}
                    </ul>

                    <Link
                      href="/admin/berita"
                      className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-teal-700 hover:underline"
                    >
                      Kelola berita
                      <ArrowRight className="size-3.5" aria-hidden="true" />
                    </Link>
                  </>
                )
              }
            />
          )}
        </div>
      </div>

      {/*
        Operator tanpa kewenangan modul apa pun akan melihat seluruh bagian di
        atas menghilang. Daripada halaman kosong tanpa penjelasan (PRD: setiap
        layar wajib punya keadaan kosong yang informatif), katakan sebabnya.
      */}
      {!notifikasi && ringkasan.length === 0 && !berita_terbaru && (
        <Kartu
          anak={
            <div className="py-8 text-center">
              <p className="text-sm font-medium text-slate-900">Belum Ada Modul yang Ditugaskan</p>
              <p className="mx-auto mt-1 max-w-md text-sm text-slate-500">
                Akun Anda belum diberi kewenangan atas modul mana pun, sehingga tidak ada
                ringkasan yang dapat ditampilkan. Hubungi Admin Utama desa untuk meminta
                penambahan hak akses.
              </p>
            </div>
          }
        />
      )}
    </div>
  )
}

/**
 * Grafik tren kunjungan 30 hari.
 *
 * Digambar sebagai SVG biasa, BUKAN dengan `Components/viz/Grafik` yang
 * dipakai halaman infografis. Dua alasannya:
 *
 *  - Komponen itu membawa recharts (~116 kB gzip) — beban yang jatuh tepat di
 *    layar pertama yang dibuka setiap operator, demi satu deret 30 angka.
 *  - Bahasa visualnya milik situs publik (judul serif, rangka navy), justru
 *    yang sengaja tidak dipakai area CMS (lihat `.area-admin` di app.css).
 *
 * Yang ikut dibawa dari sana adalah kebiasaan baiknya: grafiknya
 * `aria-hidden`, didampingi tabel setara untuk pembaca layar, sehingga
 * informasinya tidak hanya tersampaikan lewat bentuk.
 */
function TrenKunjungan({ data }: { data: TitikTren[] }) {
  if (data.length < 2) return null

  const puncak = Math.max(...data.map((d) => d.jumlah))
  // Pembagi minimal 1 agar rentang yang seluruhnya nol tidak menghasilkan
  // NaN dan melenyapkan garisnya.
  const skala = Math.max(puncak, 1)

  const LEBAR = 300
  const TINGGI = 100
  const ATAS = 8

  const titik = data.map((d, i) => ({
    x: (i / (data.length - 1)) * LEBAR,
    y: TINGGI - (d.jumlah / skala) * (TINGGI - ATAS),
  }))

  const garis = titik.map((t, i) => `${i === 0 ? 'M' : 'L'}${t.x.toFixed(2)},${t.y.toFixed(2)}`).join(' ')
  const bidang = `${garis} L${LEBAR},${TINGGI} L0,${TINGGI} Z`

  const awal = tanggalSingkat(data[0].tanggal)
  const akhir = tanggalSingkat(data[data.length - 1].tanggal)
  const total = data.reduce((n, d) => n + d.jumlah, 0)

  return (
    <figure className="m-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <figcaption className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <span className="text-sm font-semibold text-slate-900">Tren 30 Hari Terakhir</span>
        <span className="text-xs text-slate-500">
          Puncak {angka(puncak)} kunjungan · total {angka(total)}
        </span>
      </figcaption>

      {/*
        `preserveAspectRatio="none"` meregangkan gambar mengikuti lebar kartu;
        `vector-effect="non-scaling-stroke"` menjaga tebal garisnya tetap
        sama, sebab tanpa itu garis ikut melar menjadi pita tebal.
      */}
      <svg
        viewBox={`0 0 ${LEBAR} ${TINGGI}`}
        preserveAspectRatio="none"
        className="mt-3 h-32 w-full"
        aria-hidden="true"
        focusable="false"
      >
        <defs>
          <linearGradient id="tren-kunjungan" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="var(--color-teal-600)" stopOpacity="0.22" />
            <stop offset="100%" stopColor="var(--color-teal-600)" stopOpacity="0" />
          </linearGradient>
        </defs>

        <path d={bidang} fill="url(#tren-kunjungan)" />
        <path
          d={garis}
          fill="none"
          stroke="var(--color-teal-600)"
          strokeWidth="2"
          strokeLinejoin="round"
          strokeLinecap="round"
          vectorEffect="non-scaling-stroke"
        />
      </svg>

      <div className="mt-1 flex justify-between text-xs text-slate-400">
        <span>{awal}</span>
        <span>{akhir}</span>
      </div>

      {total === 0 && (
        <p className="mt-2 text-xs text-slate-500">
          Belum ada kunjungan tercatat pada rentang ini.
        </p>
      )}

      {/* Tabel setara — grafik di atas tak terbaca pembaca layar. */}
      <table className="sr-only">
        <caption>Kunjungan unik per hari, 30 hari terakhir</caption>
        <thead>
          <tr>
            <th scope="col">Tanggal</th>
            <th scope="col">Kunjungan</th>
          </tr>
        </thead>
        <tbody>
          {data.map((d) => (
            <tr key={d.tanggal}>
              <th scope="row">{tanggalSingkat(d.tanggal)}</th>
              <td>{angka(d.jumlah)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </figure>
  )
}

/** Satu ubin angka kunjungan. */
function UbinKunjungan({
  label,
  nilai,
  bandingLabel,
  bandingNilai,
}: {
  label: string
  nilai: number
  bandingLabel: string
  bandingNilai?: number
}) {
  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <p className="text-xs font-medium tracking-wide text-slate-500 uppercase">{label}</p>
      <p className="mt-2 text-3xl font-bold tracking-tight text-slate-900 tabular-nums">
        {angka(nilai)}
      </p>
      <p className="mt-1 text-xs text-slate-500">
        {bandingNilai === undefined ? bandingLabel : `${bandingLabel} ${angka(bandingNilai)}`}
      </p>
    </div>
  )
}

Dashboard.layout = (page: ReactNode) => <LayoutAdmin>{page}</LayoutAdmin>
