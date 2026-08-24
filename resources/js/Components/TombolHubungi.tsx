import type { Penjual } from '@/types/api'

/**
 * Tautan hubungi penjual — PRD 6.12.
 *
 * Sengaja hanya membuka percakapan WhatsApp berisi nama produk — bukan
 * ringkasan keranjang atau pesanan, karena modul ini tidak memiliki
 * keranjang (DEVIASI A4).
 */
export function TombolHubungi({
  penjual,
  namaProduk,
}: {
  penjual: Penjual
  namaProduk: string
}) {
  if (!penjual.whatsapp_link) {
    return (
      <p className="text-sm text-slate-500">Hubungi kantor desa untuk informasi pemesanan.</p>
    )
  }

  const pesan = encodeURIComponent(
    `Halo, saya ingin menanyakan produk "${namaProduk}" yang tercantum di website Desa Mpanau.`,
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
