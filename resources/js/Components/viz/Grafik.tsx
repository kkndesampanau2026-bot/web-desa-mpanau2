import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Line,
  LineChart,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { formatAngka } from '@/lib/format'
import './tokens.css'

/**
 * Komponen grafik bersama untuk seluruh halaman infografis.
 *
 * Beberapa keputusan yang berlaku untuk semua grafik di sini:
 *
 *  - Sumbu tunggal. Tidak pernah ada dua skala Y pada satu grafik; dua ukuran
 *    berbeda skala dipisah menjadi dua grafik.
 *  - Warna mengikuti entitas, bukan peringkatnya — sehingga urutan data yang
 *    berubah tidak mengecat ulang deret yang sama.
 *  - Grid dan sumbu dibuat samar; datanya yang menonjol, bukan kerangkanya.
 *  - Setiap grafik disertai tabel data tersembunyi bagi pembaca layar, agar
 *    informasi tidak hanya tersampaikan lewat warna dan bentuk.
 */

export interface TitikData {
  label: string
  jumlah: number
}

const GAYA_TOOLTIP = {
  backgroundColor: 'var(--viz-surface)',
  border: '1px solid var(--viz-axis)',
  borderRadius: 8,
  fontSize: 13,
  color: 'var(--viz-text-primary)',
}

/**
 * Recharts memanggil formatter dengan tipe lebar (`ValueType | undefined`),
 * jadi nilainya dinormalkan ke angka di sini alih-alih memaksakan tipe sempit
 * pada setiap pemakaian.
 */
function keAngka(nilai: unknown): number {
  const n = Number(nilai)

  return Number.isFinite(n) ? n : 0
}

/** Tabel setara untuk pembaca layar — pendamping wajib tiap grafik. */
function TabelSetara({
  judul,
  data,
  kolom,
}: {
  judul: string
  data: Record<string, string | number>[]
  kolom: [string, string][]
}) {
  return (
    <table className="sr-only">
      <caption>{judul}</caption>
      <thead>
        <tr>
          {kolom.map(([kunci, label]) => (
            <th key={kunci} scope="col">
              {label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {data.map((baris, i) => (
          <tr key={i}>
            {kolom.map(([kunci]) => (
              <td key={kunci}>{baris[kunci]}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  )
}

/**
 * Batang horizontal untuk perbandingan besaran antar-kategori.
 *
 * Dipilih horizontal karena label kategori pada data desa sering panjang
 * ("Diploma IV/Strata I", "Mengurus Rumah Tangga") — pada batang vertikal
 * label semacam itu akan miring atau terpotong.
 */
export function BatangKategori({
  judul,
  data,
  warna = 'var(--viz-series-1)',
}: {
  judul: string
  data: TitikData[]
  warna?: string
}) {
  if (data.length === 0) return null

  const tinggi = Math.max(180, data.length * 38)

  return (
    <figure className="viz-root m-0 rounded-2xl border border-black/5 bg-white p-6 shadow-md">
      <figcaption className="mb-3 font-heading text-lg font-bold text-navy">{judul}</figcaption>

      <div aria-hidden="true">
        <ResponsiveContainer width="100%" height={tinggi}>
          <BarChart data={data} layout="vertical" margin={{ left: 8, right: 48, top: 4, bottom: 4 }}>
            <CartesianGrid horizontal={false} stroke="var(--viz-grid)" />
            <XAxis type="number" hide />
            <YAxis
              type="category"
              dataKey="label"
              width={150}
              tickLine={false}
              axisLine={{ stroke: 'var(--viz-axis)' }}
              tick={{ fill: 'var(--viz-text-secondary)', fontSize: 12 }}
            />
            <Tooltip
              contentStyle={GAYA_TOOLTIP}
              cursor={{ fill: 'var(--viz-grid)', opacity: 0.4 }}
              formatter={(nilai) => [formatAngka(keAngka(nilai)), 'Jumlah']}
            />
            {/* Label nilai ditampilkan langsung: angka tidak boleh hanya
                terbaca lewat panjang batang. */}
            <Bar
              dataKey="jumlah"
              fill={warna}
              radius={[0, 4, 4, 0]}
              label={{
                position: 'right',
                fill: 'var(--viz-text-secondary)',
                fontSize: 12,
              }}
            />
          </BarChart>
        </ResponsiveContainer>
      </div>

      <TabelSetara
        judul={judul}
        data={data as unknown as Record<string, string | number>[]}
        kolom={[
          ['label', 'Kategori'],
          ['jumlah', 'Jumlah'],
        ]}
      />
    </figure>
  )
}

/** Garis tren antar-periode — satu atau dua deret. */
export function GarisTren({
  judul,
  data,
  sumbuX,
  deret,
  formatNilai = formatAngka,
}: {
  judul: string
  data: Record<string, string | number>[]
  sumbuX: string
  deret: { kunci: string; label: string; warna: string }[]
  formatNilai?: (n: number) => string
}) {
  if (data.length === 0) return null

  return (
    <figure className="viz-root m-0 rounded-2xl border border-black/5 bg-white p-6 shadow-md">
      <figcaption className="mb-3 font-heading text-lg font-bold text-navy">{judul}</figcaption>

      {data.length === 1 && (
        <p className="mb-2 text-xs text-slate-500">
          Tren memerlukan data minimal dua periode untuk terbaca.
        </p>
      )}

      <div aria-hidden="true">
        <ResponsiveContainer width="100%" height={260}>
          <LineChart data={data} margin={{ left: 8, right: 16, top: 8, bottom: 4 }}>
            <CartesianGrid vertical={false} stroke="var(--viz-grid)" />
            <XAxis
              dataKey={sumbuX}
              tickLine={false}
              axisLine={{ stroke: 'var(--viz-axis)' }}
              tick={{ fill: 'var(--viz-text-secondary)', fontSize: 12 }}
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              tick={{ fill: 'var(--viz-text-muted)', fontSize: 12 }}
              tickFormatter={(n: number) => formatNilai(n)}
              width={72}
            />
            <Tooltip
              contentStyle={GAYA_TOOLTIP}
              formatter={(nilai, nama) => [formatNilai(keAngka(nilai)), String(nama)]}
            />
            {/* Legenda selalu hadir bila deret lebih dari satu, sehingga
                identitas tidak bergantung pada warna semata. */}
            {deret.length > 1 && (
              <Legend
                wrapperStyle={{ fontSize: 13, color: 'var(--viz-text-secondary)' }}
              />
            )}
            {deret.map((d) => (
              <Line
                key={d.kunci}
                type="monotone"
                dataKey={d.kunci}
                name={d.label}
                stroke={d.warna}
                strokeWidth={2}
                dot={{ r: 4, strokeWidth: 0, fill: d.warna }}
                activeDot={{ r: 6 }}
              />
            ))}
          </LineChart>
        </ResponsiveContainer>
      </div>

      <TabelSetara
        judul={judul}
        data={data}
        kolom={[[sumbuX, 'Periode'], ...deret.map((d) => [d.kunci, d.label] as [string, string])]}
      />
    </figure>
  )
}

/**
 * Perbandingan dua nilai bersisian (mis. laki-laki vs perempuan).
 *
 * Sengaja BUKAN diagram lingkaran: perbandingan panjang batang jauh lebih
 * mudah dinilai mata daripada perbandingan sudut juring.
 */
export function BatangPerbandingan({
  judul,
  data,
}: {
  judul: string
  data: { label: string; jumlah: number; warna: string }[]
}) {
  const total = data.reduce((n, d) => n + d.jumlah, 0)

  if (total === 0) return null

  return (
    <figure className="viz-root m-0 rounded-2xl border border-black/5 bg-white p-6 shadow-md">
      <figcaption className="mb-3 font-heading text-lg font-bold text-navy">{judul}</figcaption>

      <div aria-hidden="true">
        <ResponsiveContainer width="100%" height={140}>
          <BarChart data={data} layout="vertical" margin={{ left: 8, right: 56, top: 4, bottom: 4 }}>
            <XAxis type="number" hide />
            <YAxis
              type="category"
              dataKey="label"
              width={90}
              tickLine={false}
              axisLine={false}
              tick={{ fill: 'var(--viz-text-secondary)', fontSize: 13 }}
            />
            <Tooltip
              contentStyle={GAYA_TOOLTIP}
              cursor={{ fill: 'var(--viz-grid)', opacity: 0.4 }}
              formatter={(nilai) => [formatAngka(keAngka(nilai)), 'Jumlah']}
            />
            <Bar
              dataKey="jumlah"
              radius={[0, 4, 4, 0]}
              label={{ position: 'right', fill: 'var(--viz-text-secondary)', fontSize: 12 }}
            >
              {/* Cell per baris: warna melekat pada entitas, bukan urutannya. */}
              {data.map((d) => (
                <Cell key={d.label} fill={d.warna} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>

      <TabelSetara
        judul={judul}
        data={data as unknown as Record<string, string | number>[]}
        kolom={[
          ['label', 'Kategori'],
          ['jumlah', 'Jumlah'],
        ]}
      />
    </figure>
  )
}

/**
 * Palet kualitatif untuk juring diagram lingkaran.
 *
 * Tiga slot pertama memakai token deret yang sudah tervalidasi; sisanya warna
 * pelengkap agar juring yang bersebelahan tetap terbedakan saat kategori lebih
 * dari tiga. Karena diagram lingkaran menyandingkan legenda berisi nilai dan
 * persentase, warna bukan satu-satunya pembawa makna di sini.
 */
const PALET_LINGKARAN = [
  'var(--viz-series-1)',
  'var(--viz-series-2)',
  'var(--viz-series-3)',
  '#8b5cf6',
  '#e0b021',
  '#d6608f',
  '#0f9c9c',
  '#6b7280',
]

/**
 * Diagram lingkaran (donat) untuk komposisi satu keseluruhan menjadi bagian.
 *
 * Dipakai saat proporsi "bagian terhadap total" yang ingin ditonjolkan, bukan
 * perbandingan nilai absolut antar-kategori (untuk itu {@link BatangKategori}
 * lebih tepat). Setiap juring diberi nilai dan persentase pada legenda agar
 * angkanya tidak hanya terbaca lewat sudut juring.
 */
export function Lingkaran({
  judul,
  data,
  formatNilai = formatAngka,
}: {
  judul: string
  data: { label: string; jumlah: number; warna?: string }[]
  formatNilai?: (n: number) => string
}) {
  const juring = data
    .filter((d) => d.jumlah > 0)
    .map((d, i) => ({
      ...d,
      warna: d.warna ?? PALET_LINGKARAN[i % PALET_LINGKARAN.length],
    }))

  const total = juring.reduce((n, d) => n + d.jumlah, 0)

  if (total === 0) return null

  return (
    <figure className="viz-root m-0 rounded-2xl border border-black/5 bg-white p-6 shadow-md">
      <figcaption className="mb-3 font-heading text-lg font-bold text-navy">{judul}</figcaption>

      <div className="flex flex-col items-center gap-5 sm:flex-row">
        <div aria-hidden="true" className="shrink-0">
          <ResponsiveContainer width={200} height={200}>
            <PieChart>
              <Pie
                data={juring}
                dataKey="jumlah"
                nameKey="label"
                cx="50%"
                cy="50%"
                innerRadius={56}
                outerRadius={92}
                paddingAngle={juring.length > 1 ? 1.5 : 0}
                stroke="var(--viz-surface)"
                strokeWidth={2}
              >
                {juring.map((d) => (
                  <Cell key={d.label} fill={d.warna} />
                ))}
              </Pie>
              <Tooltip
                contentStyle={GAYA_TOOLTIP}
                formatter={(nilai) => [formatNilai(keAngka(nilai)), 'Jumlah']}
              />
            </PieChart>
          </ResponsiveContainer>
        </div>

        {/* Legenda membawa nilai dan persentase eksplisit tiap juring. */}
        <ul className="w-full space-y-2">
          {juring.map((d) => (
            <li key={d.label} className="flex items-center gap-2 text-sm">
              <span
                className="inline-block h-3 w-3 shrink-0 rounded-sm"
                style={{ backgroundColor: d.warna }}
              />
              <span className="flex-1 text-slate-700">{d.label}</span>
              <span className="font-medium text-slate-900 tabular-nums">
                {formatNilai(d.jumlah)}
              </span>
              <span className="w-14 text-right text-slate-500 tabular-nums">
                {((d.jumlah / total) * 100).toFixed(1)}%
              </span>
            </li>
          ))}
        </ul>
      </div>

      <TabelSetara
        judul={judul}
        data={juring as unknown as Record<string, string | number>[]}
        kolom={[
          ['label', 'Kategori'],
          ['jumlah', 'Jumlah'],
        ]}
      />
    </figure>
  )
}

/** Angka ringkasan — dipakai saat satu nilai lebih jelas daripada grafik. */
export function KartuAngka({
  label,
  nilai,
  satuan,
  keterangan,
}: {
  label: string
  nilai: string | number
  satuan?: string
  keterangan?: string
}) {
  return (
    <div className="rounded-2xl bg-navy p-5 text-center shadow-md">
      <p className="text-xs font-medium tracking-wide text-gold uppercase">{label}</p>
      <p className="font-heading mt-1 text-2xl font-bold text-white">
        {typeof nilai === 'number' ? formatAngka(nilai) : nilai}
        {satuan && <span className="ml-1 text-base font-normal text-white/70">{satuan}</span>}
      </p>
      {keterangan && <p className="mt-1 text-xs text-white/60">{keterangan}</p>}
    </div>
  )
}
