import type { InputHTMLAttributes, ReactNode, TextareaHTMLAttributes } from 'react'

/**
 * Elemen formulir bersama untuk seluruh halaman CMS.
 *
 * Tujuannya bukan sekadar keseragaman tampilan, melainkan memastikan setiap
 * input punya <label> yang tertaut dan galat validasi yang terhubung lewat
 * aria-describedby — syarat aksesibilitas PRD 12.4 yang mudah terlewat bila
 * setiap halaman menulis markup formulirnya sendiri.
 */

export function Kolom({
  label,
  htmlFor,
  galat,
  petunjuk,
  children,
}: {
  label: string
  htmlFor: string
  galat?: string
  petunjuk?: string
  children: ReactNode
}) {
  return (
    <div>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
        {label}
      </label>
      {petunjuk && (
        <p id={`${htmlFor}-petunjuk`} className="mt-0.5 text-xs text-slate-500">
          {petunjuk}
        </p>
      )}
      <div className="mt-1">{children}</div>
      {galat && (
        <p id={`${htmlFor}-galat`} role="alert" className="mt-1 text-sm text-red-600">
          {galat}
        </p>
      )}
    </div>
  )
}

const GAYA_INPUT =
  'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900 disabled:bg-slate-50'

export function Input({ galat, ...props }: InputHTMLAttributes<HTMLInputElement> & { galat?: string }) {
  return (
    <input
      {...props}
      aria-invalid={galat ? 'true' : undefined}
      aria-describedby={galat ? `${props.id}-galat` : undefined}
      className={`${GAYA_INPUT} ${galat ? 'border-red-400' : ''}`}
    />
  )
}

export function TextArea({
  galat,
  ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement> & { galat?: string }) {
  return (
    <textarea
      {...props}
      aria-invalid={galat ? 'true' : undefined}
      aria-describedby={galat ? `${props.id}-galat` : undefined}
      className={`${GAYA_INPUT} ${galat ? 'border-red-400' : ''}`}
    />
  )
}

export function Tombol({
  variasi = 'utama',
  ...props
}: InputHTMLAttributes<HTMLButtonElement> & { variasi?: 'utama' | 'sekunder' | 'bahaya' }) {
  const gaya = {
    utama: 'bg-slate-900 text-white hover:bg-slate-800',
    sekunder: 'border border-slate-300 text-slate-700 hover:bg-slate-50',
    bahaya: 'border border-red-300 text-red-700 hover:bg-red-50',
  }[variasi]

  return (
    <button
      {...(props as object)}
      className={`rounded-lg px-4 py-2 text-sm font-medium transition disabled:opacity-60 ${gaya}`}
    />
  )
}

export function Kartu({ judul, anak }: { judul?: string; anak: ReactNode }) {
  return (
    <section className="rounded-xl border border-slate-200 bg-white p-6">
      {judul && <h2 className="mb-4 font-semibold text-slate-900">{judul}</h2>}
      {anak}
    </section>
  )
}

/** Notifikasi hasil aksi — sukses atau gagal. */
export function Pemberitahuan({ jenis, pesan }: { jenis: 'sukses' | 'galat'; pesan: string }) {
  return (
    <p
      role="alert"
      className={`rounded-lg px-3 py-2 text-sm ${
        jenis === 'sukses' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-700'
      }`}
    >
      {pesan}
    </p>
  )
}
