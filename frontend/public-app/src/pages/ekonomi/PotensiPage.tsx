import { useSearchParams } from 'react-router-dom'
import { StatusMuat } from '@/components/StatusMuat'
import { usePotensi } from '@/lib/queries'

const DESKRIPSI =
  'Potensi ekonomi, pariwisata, pertanian, dan industri kreatif yang dimiliki desa.'

/** Potensi Desa — PRD 6.11. */
export function PotensiPage() {
  const [params, setParams] = useSearchParams()
  const kategori = params.get('kategori') ?? undefined

  const { data, isPending, error } = usePotensi(kategori)

  function gantiKategori(pilihan?: string) {
    const baru = new URLSearchParams(params)
    if (pilihan) baru.set('kategori', pilihan)
    else baru.delete('kategori')
    setParams(baru)
  }

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      // Hanya dianggap kosong bila tanpa filter — kategori yang kebetulan
      // kosong ditangani sebagai pesan tersendiri di bawah.
      kosong={!data && !kategori}
      judul="Potensi Desa"
      deskripsi={DESKRIPSI}
    >
      <div className="mx-auto max-w-5xl px-6 py-10">
        <h1 className="font-heading text-2xl font-bold text-navy">Potensi Desa</h1>
        <p className="mt-2 text-slate-600">{DESKRIPSI}</p>

        {data && data.kategori_tersedia.length > 0 && (
          <nav aria-label="Filter kategori" className="mt-6 flex flex-wrap gap-2">
            <Chip aktif={!kategori} onClick={() => gantiKategori()}>
              Semua
            </Chip>
            {data.kategori_tersedia.map((k) => (
              <Chip key={k} aktif={kategori === k} onClick={() => gantiKategori(k)}>
                {k}
              </Chip>
            ))}
          </nav>
        )}

        {!data?.items.length ? (
          <p className="mt-10 rounded-lg border border-dashed border-navy/20 bg-navy/3 px-6 py-10 text-center text-slate-600">
            Belum ada potensi desa pada kategori ini.
          </p>
        ) : (
          <ul className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {data.items.map((p) => (
              <li
                key={p.id}
                className="overflow-hidden rounded-2xl border border-black/5 bg-white shadow-sm transition hover:shadow-sm"
              >
                {p.foto ? (
                  <img
                    src={p.foto}
                    alt=""
                    loading="lazy"
                    className="h-40 w-full object-cover"
                  />
                ) : (
                  <div aria-hidden="true" className="h-40 w-full bg-navy/5" />
                )}

                <div className="p-4">
                  <span className="text-xs font-medium text-slate-500">{p.kategori}</span>
                  <h2 className="mt-1 font-semibold text-navy">{p.judul}</h2>
                  {p.deskripsi && (
                    <p className="mt-1 text-sm text-slate-600">{p.deskripsi}</p>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    </StatusMuat>
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
