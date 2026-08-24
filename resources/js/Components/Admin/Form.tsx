import type { InputHTMLAttributes, ReactNode, TextareaHTMLAttributes } from 'react'
import type { LucideIcon } from 'lucide-react'

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
      <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-900">
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
  'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 disabled:bg-slate-50'

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
    utama: 'bg-teal-600 text-white shadow-sm hover:bg-teal-700',
    sekunder: 'border border-slate-300 text-slate-700 hover:bg-slate-50',
    bahaya: 'border border-red-300 text-red-700 hover:bg-red-50',
  }[variasi]

  return (
    <button
      {...(props as object)}
      className={`inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition disabled:opacity-60 ${gaya}`}
    />
  )
}

export function Kartu({
  judul,
  ikon: Ikon,
  anak,
}: {
  judul?: string
  ikon?: LucideIcon
  anak: ReactNode
}) {
  return (
    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
      {judul && (
        <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-100 px-6 py-4">
          {Ikon && <Ikon className="size-5 shrink-0 text-teal-700" aria-hidden="true" />}
          <h2 className="text-lg font-semibold tracking-tight text-slate-900">{judul}</h2>
        </div>
      )}
      <div className="p-6">{anak}</div>
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
