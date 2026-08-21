/**
 * Merender HTML rich text dari CMS (sambutan, sejarah, isi berita).
 *
 * HTML sudah disanitasi di sisi server dengan HTMLPurifier sebelum disimpan
 * (lihat Admin\ProfileController & Admin\NewsController), sehingga yang
 * tersimpan di basis data dijamin bersih. Sanitasi dilakukan sekali saat
 * menulis, bukan berulang di setiap konsumen data.
 *
 * Komponen ini sengaja dibuat terpisah agar setiap pemakaian
 * dangerouslySetInnerHTML pada aplikasi terkumpul di satu berkas dan mudah
 * diaudit.
 */
export function KontenKaya({ html, className = '' }: { html: string; className?: string }) {
  return (
    <div
      className={`prose-desa ${className}`}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  )
}
