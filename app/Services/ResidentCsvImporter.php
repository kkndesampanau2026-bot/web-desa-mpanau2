<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Impor data penduduk dari CSV — PRD 6.3 & 10.5.
 *
 * Prinsip yang menentukan rancangan kelas ini: seorang operator desa yang
 * mengunggah 2.000 baris dan 3 di antaranya salah TIDAK boleh kehilangan
 * 1.997 baris yang benar. Karena itu setiap baris divalidasi dan disimpan
 * sendiri-sendiri, lalu dikembalikan ringkasan berisi alasan kegagalan
 * per baris — bukan satu transaksi besar yang gagal seluruhnya.
 *
 * Deduplikasi memakai blind index NIK (lihat PiiCipher), sehingga baris
 * ganda terdeteksi tanpa perlu mendekripsi data yang sudah tersimpan.
 */
class ResidentCsvImporter
{
    /** Kolom yang harus ada pada baris header. */
    public const KOLOM_WAJIB = ['nik', 'nama', 'jenis_kelamin'];

    public const KOLOM_TEMPLATE = [
        'nik', 'nama', 'jenis_kelamin', 'tanggal_lahir', 'no_kk',
        'status_hubungan_kk', 'dusun', 'pendidikan_terakhir', 'pekerjaan',
        'status_perkawinan', 'agama', 'status_domisili',
    ];

    public function __construct(private readonly PiiCipher $cipher) {}

    /**
     * @param  array<int, array<string, string>>  $baris  hasil parsing CSV
     * @return array{berhasil: int, gagal: int, galat: list<array{baris: int, nik: string, pesan: string}>}
     */
    public function impor(array $baris, int $villageId, array $petaDusun = []): array
    {
        $berhasil = 0;
        $galat = [];

        // NIK yang sudah diproses DALAM berkas ini — menangkap baris kembar
        // di dalam satu unggahan, yang tidak akan tertangkap oleh pengecekan
        // ke basis data karena baris pertamanya belum tersimpan saat dicek.
        $nikDalamBerkas = [];

        foreach ($baris as $i => $data) {
            // +2: baris 1 adalah header, dan indeks larik dimulai dari 0.
            $nomorBaris = $i + 2;

            // Melengkapi kolom opsional yang tidak ada pada berkas menjadi
            // string kosong. Admin desa lazim mengunggah CSV berisi kolom
            // wajib saja; tanpa normalisasi ini setiap baris akan gagal
            // karena kunci larik yang tidak terdefinisi.
            $data = array_merge(
                array_fill_keys(self::KOLOM_TEMPLATE, ''),
                array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $data)
            );

            $nik = (string) $data['nik'];

            // Aturan `nullable` hanya melewati null, bukan string kosong —
            // sedangkan sel CSV yang tidak diisi selalu berupa string kosong.
            // Tanpa konversi ini, kolom opsional yang dikosongkan justru
            // dianggap tidak valid.
            $untukValidasi = array_map(fn ($v) => $v === '' ? null : $v, $data);

            $validator = Validator::make($untukValidasi, $this->aturan(), $this->pesan());

            if ($validator->fails()) {
                $galat[] = [
                    'baris' => $nomorBaris,
                    'nik' => $this->cipher->samarkan($nik) ?? '-',
                    'pesan' => $validator->errors()->first(),
                ];

                continue;
            }

            $hash = $this->cipher->hash($nik);

            if (isset($nikDalamBerkas[$hash])) {
                $galat[] = [
                    'baris' => $nomorBaris,
                    'nik' => $this->cipher->samarkan($nik) ?? '-',
                    'pesan' => "NIK ganda dalam berkas ini (lihat baris {$nikDalamBerkas[$hash]}).",
                ];

                continue;
            }

            try {
                // updateOrCreate berdasarkan blind index: mengunggah ulang
                // berkas yang sama memperbarui data, bukan menggandakannya.
                DB::transaction(function () use ($data, $villageId, $hash, $petaDusun) {
                    Resident::updateOrCreate(
                        ['village_id' => $villageId, 'nik_hash' => $hash],
                        [
                            'nik' => $data['nik'],
                            'no_kk' => $data['no_kk'] ?: null,
                            'nama' => $data['nama'],
                            'jenis_kelamin' => strtoupper($data['jenis_kelamin']),
                            'tanggal_lahir' => $data['tanggal_lahir'] ?: null,
                            'dusun_id' => $petaDusun[strtolower($data['dusun'])] ?? null,
                            'status_hubungan_kk' => $data['status_hubungan_kk'] ?: null,
                            'pendidikan_terakhir' => $data['pendidikan_terakhir'] ?: null,
                            'pekerjaan' => $data['pekerjaan'] ?: null,
                            'status_perkawinan' => $data['status_perkawinan'] ?: null,
                            'agama' => $data['agama'] ?: null,
                            'status_domisili' => $data['status_domisili'] ?: 'Penduduk Tetap',
                        ]
                    );
                });

                $nikDalamBerkas[$hash] = $nomorBaris;
                $berhasil++;
            } catch (\Throwable $e) {
                // Pesan galat basis data tidak diteruskan apa adanya: ia dapat
                // memuat potongan data pribadi maupun detail skema.
                report($e);

                $galat[] = [
                    'baris' => $nomorBaris,
                    'nik' => $this->cipher->samarkan($nik) ?? '-',
                    'pesan' => 'Gagal menyimpan baris ini. Periksa kembali isinya.',
                ];
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => count($galat), 'galat' => $galat];
    }

    /** @return array<string, mixed> */
    private function aturan(): array
    {
        return [
            // NIK: tepat 16 digit angka (PRD 6.3).
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'string', 'in:L,P,l,p'],
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'no_kk' => ['nullable', 'string', 'regex:/^\d{16}$/'],
            'status_hubungan_kk' => ['nullable', Rule::in(Resident::HUBUNGAN_KK)],
            'status_perkawinan' => ['nullable', Rule::in(Resident::PERKAWINAN)],
            'agama' => ['nullable', Rule::in(Resident::AGAMA)],
            'status_domisili' => ['nullable', Rule::in(Resident::DOMISILI)],
            // Sempat terlewat, dan itu berarti satu baris CSV berisi jenjang
            // di luar daftar menggagalkan impor dengan galat basis data —
            // bukan sebagai baris bermasalah yang dilaporkan ke operator.
            'pendidikan_terakhir' => ['nullable', Rule::in(Resident::PENDIDIKAN)],
        ];
    }

    /** @return array<string, string> */
    private function pesan(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.regex' => 'NIK harus tepat 16 digit angka.',
            'no_kk.regex' => 'No. KK harus tepat 16 digit angka.',
            'nama.required' => 'Nama wajib diisi.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib diisi.',
            'jenis_kelamin.in' => 'Jenis kelamin harus L atau P.',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak dikenali (gunakan YYYY-MM-DD).',
            'tanggal_lahir.before' => 'Tanggal lahir tidak boleh di masa depan.',
        ];
    }
}
