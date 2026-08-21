import { Compass, Quote, Ruler, Target, Users } from 'lucide-react'
import { KontenKaya } from '@/components/KontenKaya'
import { StatusMuat } from '@/components/StatusMuat'
import { IsiHalaman, JudulSeksi, Kartu, KepalaHalaman } from '@/components/ui'
import { useBpdMembers, useOfficials, useProfil } from '@/lib/queries'
import { formatAngka } from '@/lib/format'
import type { BpdMember, Official } from '@/types/api'

const DESKRIPSI =
  'Sambutan Kepala Desa, sejarah, visi-misi, struktur organisasi Pemerintah Desa dan BPD, data geografis, serta peta lokasi kantor desa.'

export function ProfilPage() {
  const { data: profil, isPending, error } = useProfil()
  const { data: officials } = useOfficials()
  const { data: bpd } = useBpdMembers()

  return (
    <StatusMuat
      memuat={isPending}
      galat={error}
      kosong={!profil}
      eyebrow="Tentang Desa"
      judul="Profil Desa"
      deskripsi={DESKRIPSI}
    >
      <KepalaHalaman eyebrow="Tentang Desa" judul="Profil Desa" deskripsi={DESKRIPSI} />

      {profil && (
        <IsiHalaman>
          {profil.sambutan && (
            <section className="mb-12">
              <JudulSeksi>Sambutan Kepala Desa</JudulSeksi>

              <Kartu className="p-6 sm:p-8">
                <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
                  {profil.foto_kepala_desa ? (
                    <img
                      src={profil.foto_kepala_desa}
                      alt={`Foto ${profil.nama_kepala_desa ?? 'Kepala Desa'}`}
                      className="size-28 shrink-0 rounded-full border-4 border-gold object-cover"
                    />
                  ) : (
                    <span
                      aria-hidden="true"
                      className="grid size-28 shrink-0 place-items-center rounded-full border-4 border-gold bg-navy/5 text-navy/30"
                    >
                      <Users className="size-10" />
                    </span>
                  )}

                  <div className="min-w-0">
                    <Quote className="mb-2 size-6 text-gold/50" aria-hidden="true" />
                    <KontenKaya html={profil.sambutan} />
                    {profil.nama_kepala_desa && (
                      <p className="font-heading mt-4 font-bold text-navy">
                        {profil.nama_kepala_desa}
                        <span className="block text-sm font-normal text-slate-500">
                          Kepala Desa
                        </span>
                      </p>
                    )}
                  </div>
                </div>
              </Kartu>
            </section>
          )}

          {(profil.visi || profil.misi.length > 0) && (
            <section className="mb-12 grid gap-6 lg:grid-cols-2">
              {profil.visi && (
                <Kartu className="border-none bg-navy p-6 sm:p-8">
                  <h2 className="flex items-center gap-2 text-xs font-semibold tracking-widest text-gold uppercase">
                    <Target className="size-4" aria-hidden="true" />
                    Visi
                  </h2>
                  <p className="font-heading mt-4 text-lg leading-relaxed text-white sm:text-xl">
                    {profil.visi}
                  </p>
                </Kartu>
              )}

              {profil.misi.length > 0 && (
                <Kartu className="p-6 sm:p-8">
                  <h2 className="flex items-center gap-2 text-xs font-semibold tracking-widest text-gold-dark uppercase">
                    <Compass className="size-4" aria-hidden="true" />
                    Misi
                  </h2>
                  <ol className="mt-4 space-y-3">
                    {profil.misi.map((poin, i) => (
                      <li key={i} className="flex gap-3 text-slate-700">
                        <span
                          aria-hidden="true"
                          className="font-heading grid size-6 shrink-0 place-items-center rounded-full bg-gold/15 text-xs font-bold text-gold-dark tabular-nums"
                        >
                          {i + 1}
                        </span>
                        <span className="leading-relaxed">{poin}</span>
                      </li>
                    ))}
                  </ol>
                </Kartu>
              )}
            </section>
          )}

          {profil.sejarah && (
            <section className="mb-12">
              <JudulSeksi>Sejarah Desa</JudulSeksi>
              <Kartu className="p-6 sm:p-8">
                <KontenKaya html={profil.sejarah} />
              </Kartu>
            </section>
          )}

          <section className="mb-12">
            <JudulSeksi>Data Geografis</JudulSeksi>

            <div className="grid gap-4 sm:grid-cols-2">
              <Kartu className="p-6">
                <dl className="space-y-4">
                  <Fakta
                    ikon={Ruler}
                    label="Luas Desa"
                    nilai={
                      profil.geografis.luas_desa_hektar
                        ? `${formatAngka(profil.geografis.luas_desa_hektar)} ha`
                        : null
                    }
                  />
                  <Fakta
                    ikon={Users}
                    label="Jumlah Penduduk"
                    nilai={
                      profil.geografis.jumlah_penduduk
                        ? `${formatAngka(profil.geografis.jumlah_penduduk)} jiwa`
                        : null
                    }
                  />
                </dl>
              </Kartu>

              <Kartu className="p-6">
                <h3 className="mb-3 flex items-center gap-2 text-xs font-semibold tracking-widest text-gold-dark uppercase">
                  <Compass className="size-4" aria-hidden="true" />
                  Batas Wilayah
                </h3>
                <dl className="space-y-2 text-sm">
                  {(
                    [
                      ['Utara', profil.geografis.batas.utara],
                      ['Timur', profil.geografis.batas.timur],
                      ['Selatan', profil.geografis.batas.selatan],
                      ['Barat', profil.geografis.batas.barat],
                    ] as const
                  ).map(([arah, nilai]) => (
                    <div key={arah} className="flex justify-between gap-4 border-b border-navy/5 pb-2 last:border-0">
                      <dt className="text-slate-500">{arah}</dt>
                      <dd className="text-right font-medium text-navy">
                        {nilai ?? <span className="font-normal text-slate-400">—</span>}
                      </dd>
                    </div>
                  ))}
                </dl>
              </Kartu>
            </div>
          </section>

          {/* Dua bagan terpisah — PRD 3.2 menegaskan Pemerintah Desa dan BPD
              adalah dua lembaga berbeda yang keduanya wajib direpresentasikan. */}
          {officials && officials.length > 0 && (
            <section className="mb-12">
              <JudulSeksi keterangan="Perangkat desa yang menjalankan pemerintahan sehari-hari.">
                Struktur Pemerintahan Desa
              </JudulSeksi>
              <DaftarOrang orang={officials} />
            </section>
          )}

          {bpd && bpd.length > 0 && (
            <section>
              <JudulSeksi keterangan="Lembaga permusyawaratan desa, mitra kerja Kepala Desa.">
                Badan Permusyawaratan Desa
              </JudulSeksi>
              <DaftarOrang orang={bpd} />
            </section>
          )}
        </IsiHalaman>
      )}
    </StatusMuat>
  )
}

function Fakta({
  ikon: Ikon,
  label,
  nilai,
}: {
  ikon: typeof Ruler
  label: string
  nilai: string | null
}) {
  return (
    <div className="flex items-center gap-4">
      <span
        aria-hidden="true"
        className="grid size-10 shrink-0 place-items-center rounded-xl bg-navy/5 text-navy"
      >
        <Ikon className="size-5" />
      </span>
      <div>
        <dt className="text-xs text-slate-500">{label}</dt>
        <dd className="font-heading text-lg font-bold text-navy">
          {nilai ?? <span className="text-base font-normal text-slate-400">Belum diisi</span>}
        </dd>
      </div>
    </div>
  )
}

function DaftarOrang({ orang }: { orang: (Official | BpdMember)[] }) {
  return (
    <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {orang.map((o) => (
        <li key={o.id}>
          <Kartu className="flex items-center gap-4 p-4">
            {o.foto ? (
              <img
                src={o.foto}
                alt=""
                loading="lazy"
                className="size-14 shrink-0 rounded-full border-2 border-gold object-cover"
              />
            ) : (
              <span
                aria-hidden="true"
                className="font-heading grid size-14 shrink-0 place-items-center rounded-full border-2 border-gold/40 bg-navy/5 text-lg font-bold text-navy/40"
              >
                {o.nama.charAt(0)}
              </span>
            )}

            <div className="min-w-0">
              <p className="font-heading truncate font-bold text-navy">{o.nama}</p>
              <p className="truncate text-sm text-slate-500">{o.jabatan}</p>
            </div>
          </Kartu>
        </li>
      ))}
    </ul>
  )
}
