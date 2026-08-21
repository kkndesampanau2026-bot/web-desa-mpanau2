/** Pemformat tampilan berbahasa Indonesia. */

const FORMAT_TANGGAL = new Intl.DateTimeFormat('id-ID', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

export function formatTanggal(iso: string | null | undefined): string {
  if (!iso) return '—'

  const tanggal = new Date(iso)
  return Number.isNaN(tanggal.getTime()) ? '—' : FORMAT_TANGGAL.format(tanggal)
}

export function formatAngka(nilai: number | null | undefined): string {
  return nilai == null ? '—' : nilai.toLocaleString('id-ID')
}

const NAMA_HARI: Record<string, string> = {
  senin: 'Senin',
  selasa: 'Selasa',
  rabu: 'Rabu',
  kamis: 'Kamis',
  jumat: 'Jumat',
  sabtu: 'Sabtu',
  minggu: 'Minggu',
}

export function labelHari(kunci: string): string {
  return NAMA_HARI[kunci] ?? kunci
}

const FORMAT_RUPIAH = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
})

export function formatRupiah(nilai: number | null | undefined): string {
  return nilai == null ? '—' : FORMAT_RUPIAH.format(nilai)
}

/**
 * Rupiah ringkas untuk label sumbu grafik — "Rp1,2 M" alih-alih
 * "Rp1.240.000.000", yang akan bertumpuk pada sumbu yang sempit.
 */
export function formatRupiahRingkas(nilai: number): string {
  const abs = Math.abs(nilai)

  if (abs >= 1_000_000_000) return `Rp${(nilai / 1_000_000_000).toFixed(1).replace('.', ',')} M`
  if (abs >= 1_000_000) return `Rp${(nilai / 1_000_000).toFixed(0)} jt`
  if (abs >= 1_000) return `Rp${(nilai / 1_000).toFixed(0)} rb`

  return `Rp${nilai}`
}

/** Persentase dengan koma desimal ala Indonesia. */
export function formatPersen(nilai: number | null | undefined, desimal = 2): string {
  return nilai == null ? '—' : `${nilai.toFixed(desimal).replace('.', ',')}%`
}
