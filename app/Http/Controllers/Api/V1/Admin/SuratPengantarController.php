<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dusun;
use App\Models\LetterOfficial;
use App\Models\LetterRequest;
use App\Models\Rt;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\PdfSuratPengantar;
use App\Services\SuratPengantarService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CMS Surat Pengantar — master RT, pejabat penanda tangan, dan pemantauan.
 *
 * Mengikuti pola modul admin lain: layar React mengambil datanya lewat XHR ke
 * `/api/v1/admin/*` dengan kontrak App\Support\ApiResponse.
 *
 * Layar inilah yang membuat aturan "jangan hard-code 21 RT, 4 Dusun, chat ID,
 * dan tanda tangan" dapat ditegakkan: seluruhnya dikelola dari sini, tidak
 * satu pun tertulis di kode.
 */
class SuratPengantarController extends Controller
{
    /** MIME tanda tangan yang diterima beserta ekstensi yang DIPAKSAKAN. */
    private const MIME_TTD = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly SuratPengantarService $surat,
        private readonly PdfSuratPengantar $pdf,
    ) {}

    // -----------------------------------------------------------------
    // Master RT
    // -----------------------------------------------------------------

    public function daftarRt(): JsonResponse
    {
        $rts = Rt::with('dusun:id,nama')
            ->where('village_id', $this->village->id())
            ->urut()
            ->get()
            ->map(fn (Rt $rt) => [
                'id' => $rt->id,
                'nomor' => $rt->nomor,
                'dusun_id' => $rt->dusun_id,
                'dusun' => $rt->dusun?->nama,
                'urutan_tampil' => $rt->urutan_tampil,
            ]);

        return ApiResponse::success($rts);
    }

    public function simpanRt(Request $request): JsonResponse
    {
        $data = $this->validasiRt($request);

        $rt = Rt::create([...$data, 'village_id' => $this->village->id()]);

        $this->logger->log('created', $rt, "Menambah RT {$rt->nomor}");

        return ApiResponse::success($rt, 'RT ditambahkan.', 201);
    }

    public function ubahRt(Request $request, Rt $rt): JsonResponse
    {
        $this->pastikanMilikDesaIni($rt->village_id);

        $sebelum = $rt->only(['nomor', 'dusun_id', 'urutan_tampil']);
        $rt->update($this->validasiRt($request, $rt));

        $this->logger->log('updated', $rt, "Mengubah RT {$rt->nomor}", $sebelum, $rt->only(array_keys($sebelum)));

        return ApiResponse::success($rt, 'RT diperbarui.');
    }

    public function hapusRt(Rt $rt): JsonResponse
    {
        $this->pastikanMilikDesaIni($rt->village_id);

        // RT yang pernah dipakai pengajuan tidak boleh hilang: surat yang
        // sudah terbit menunjuk ke sini untuk nomor dan kopnya. FK-nya
        // memang restrictOnDelete, tetapi pesan ini jauh lebih berguna bagi
        // operator daripada galat integritas basis data.
        if (LetterRequest::where('rt_id', $rt->id)->exists()) {
            return ApiResponse::error(
                'RT ini sudah dipakai pada pengajuan surat dan tidak dapat dihapus.',
                422
            );
        }

        $nomor = $rt->nomor;
        $rt->delete();

        $this->logger->log('deleted', null, "Menghapus RT {$nomor}");

        return ApiResponse::success(null, 'RT dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasiRt(Request $request, ?Rt $rt = null): array
    {
        return $request->validate([
            'nomor' => [
                'required', 'string', 'max:10',
                Rule::unique('rts', 'nomor')
                    ->where('village_id', $this->village->id())
                    ->ignore($rt?->id),
            ],
            'dusun_id' => [
                'nullable',
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],
            'urutan_tampil' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'nomor.unique' => 'Nomor RT ini sudah terdaftar.',
        ]);
    }

    // -----------------------------------------------------------------
    // Pejabat penanda tangan
    // -----------------------------------------------------------------

    public function daftarPejabat(): JsonResponse
    {
        $pejabat = LetterOfficial::with(['rt:id,nomor', 'dusun:id,nama'])
            ->where('village_id', $this->village->id())
            ->orderBy('role')
            ->get()
            ->map(fn (LetterOfficial $o) => [
                'id' => $o->id,
                'role' => $o->role,
                'nama' => $o->nama,
                'jabatan_teks' => $o->jabatan_teks,
                'rt_id' => $o->rt_id,
                'rt' => $o->rt?->nomor,
                'dusun_id' => $o->dusun_id,
                'dusun' => $o->dusun?->nama,
                'is_active' => $o->is_active,
                // Chat ID hanya dikembalikan sebagai penanda ADA/TIDAK.
                // Nilainya tidak pernah dikirim balik ke peramban: ia setara
                // alamat kontak pribadi pejabat, dan layar ini hanya perlu
                // tahu apakah notifikasi akan sampai.
                'punya_telegram' => filled($o->telegram_chat_id),
                'punya_ttd' => filled($o->signature_path),
            ]);

        return ApiResponse::success($pejabat);
    }

    public function simpanPejabat(Request $request): JsonResponse
    {
        $data = $this->validasiPejabat($request);

        $pejabat = LetterOfficial::create([
            ...$this->normalkanWilayah($data),
            'village_id' => $this->village->id(),
        ]);

        if ($request->hasFile('tanda_tangan')) {
            $this->simpanTandaTangan($pejabat, $request);
        }

        // Nama pejabat & wilayahnya dicatat; chat ID tidak — audit trail tidak
        // boleh berubah menjadi tabel bayangan berisi kontak pribadi.
        $this->logger->log('created', $pejabat, "Menambah pejabat surat: {$pejabat->nama}");

        return ApiResponse::success($pejabat->fresh(), 'Pejabat ditambahkan.', 201);
    }

    public function ubahPejabat(Request $request, LetterOfficial $letterOfficial): JsonResponse
    {
        $this->pastikanMilikDesaIni($letterOfficial->village_id);

        $data = $this->validasiPejabat($request, $letterOfficial);
        $letterOfficial->update($this->normalkanWilayah($data));

        if ($request->hasFile('tanda_tangan')) {
            $this->simpanTandaTangan($letterOfficial, $request);
        }

        $this->logger->log('updated', $letterOfficial, "Mengubah pejabat surat: {$letterOfficial->nama}");

        return ApiResponse::success($letterOfficial->fresh(), 'Pejabat diperbarui.');
    }

    public function hapusPejabat(LetterOfficial $letterOfficial): JsonResponse
    {
        $this->pastikanMilikDesaIni($letterOfficial->village_id);

        // Pejabat yang pernah menandatangani surat TIDAK dihapus, melainkan
        // dinonaktifkan: `letter_approval_logs` menunjuk ke barisnya, dan
        // surat yang sudah terbit harus tetap dapat ditelusuri ke orang yang
        // benar-benar menyetujuinya.
        $pernahMenyetujui = LetterRequest::where('approved_rt_by', $letterOfficial->id)
            ->orWhere('approved_kadus_by', $letterOfficial->id)
            ->exists();

        if ($pernahMenyetujui) {
            $letterOfficial->update(['is_active' => false]);

            $this->logger->log('updated', $letterOfficial, "Menonaktifkan pejabat: {$letterOfficial->nama}");

            return ApiResponse::success(null, 'Pejabat dinonaktifkan (sudah pernah menandatangani surat).');
        }

        $this->hapusBerkasTtd($letterOfficial->signature_path);
        $nama = $letterOfficial->nama;
        $letterOfficial->delete();

        $this->logger->log('deleted', null, "Menghapus pejabat: {$nama}");

        return ApiResponse::success(null, 'Pejabat dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasiPejabat(Request $request, ?LetterOfficial $pejabat = null): array
    {
        return $request->validate([
            'role' => ['required', Rule::in(LetterOfficial::ROLES)],
            'nama' => ['required', 'string', 'max:255'],
            'jabatan_teks' => ['nullable', 'string', 'max:255'],

            'rt_id' => [
                'nullable',
                'required_if:role,'.LetterOfficial::ROLE_KETUA_RT,
                Rule::exists('rts', 'id')->where('village_id', $this->village->id()),
            ],
            'dusun_id' => [
                'nullable',
                'required_if:role,'.LetterOfficial::ROLE_KEPALA_DUSUN,
                Rule::exists('dusuns', 'id')->where('village_id', $this->village->id()),
            ],

            // Chat ID Telegram: bilangan bulat, boleh negatif (grup/channel).
            'telegram_chat_id' => [
                'nullable', 'string', 'max:32', 'regex:/^-?\d{1,20}$/',
                Rule::unique('letter_officials', 'telegram_chat_id')->ignore($pejabat?->id),
            ],

            'tanda_tangan' => [
                'nullable', 'file',
                'mimetypes:'.implode(',', array_keys(self::MIME_TTD)),
                'max:2048',
            ],

            'is_active' => ['nullable', 'boolean'],
        ], [
            'rt_id.required_if' => 'Ketua RT wajib ditautkan ke sebuah RT.',
            'dusun_id.required_if' => 'Kepala Dusun wajib ditautkan ke sebuah dusun.',
            'telegram_chat_id.regex' => 'Chat ID Telegram hanya berupa angka. '
                .'Dapatkan dengan mengirim /start ke bot desa, atau lewat @userinfobot.',
            'telegram_chat_id.unique' => 'Chat ID ini sudah dipakai pejabat lain.',
            'tanda_tangan.mimetypes' => 'Tanda tangan harus berupa gambar PNG, JPG, atau WebP.',
            'tanda_tangan.max' => 'Ukuran gambar tanda tangan maksimal 2 MB.',
        ]);
    }

    /**
     * Mengosongkan kolom wilayah yang tidak relevan dengan rolenya.
     *
     * Tanpa ini, mengubah pejabat dari Ketua RT menjadi Kepala Dusun akan
     * meninggalkan `rt_id` lama — dan `berwenangAtas()` akan menganggapnya
     * masih berwenang atas RT itu.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalkanWilayah(array $data): array
    {
        unset($data['tanda_tangan']);

        if (($data['role'] ?? null) === LetterOfficial::ROLE_KETUA_RT) {
            $data['dusun_id'] = null;
        } else {
            $data['rt_id'] = null;
        }

        return $data;
    }

    /**
     * Menyimpan gambar tanda tangan ke disk PRIVAT.
     *
     * Sepola dengan LampiranPengaduanService: nama berkas diacak, MIME dibaca
     * dari isi berkas, dan ekstensi ditentukan ulang server. Tanda tangan
     * pejabat tidak boleh berada di disk `public` — siapa pun yang menebak
     * namanya dapat mengunduh lalu menempelkannya ke dokumen lain.
     */
    private function simpanTandaTangan(LetterOfficial $pejabat, Request $request): void
    {
        $berkas = $request->file('tanda_tangan');
        $mime = $berkas->getMimeType();

        if (! isset(self::MIME_TTD[$mime])) {
            return;
        }

        $path = 'tanda-tangan/'.Str::uuid().'.'.self::MIME_TTD[$mime];

        Storage::disk(PdfSuratPengantar::DISK)->put($path, file_get_contents($berkas->getRealPath()));

        $this->hapusBerkasTtd($pejabat->signature_path);

        $pejabat->update(['signature_path' => $path]);
    }

    private function hapusBerkasTtd(?string $path): void
    {
        if (filled($path)) {
            Storage::disk(PdfSuratPengantar::DISK)->delete($path);
        }
    }

    /** Pratinjau tanda tangan untuk layar CMS; tidak pernah publik. */
    public function lihatTandaTangan(LetterOfficial $letterOfficial)
    {
        $this->pastikanMilikDesaIni($letterOfficial->village_id);

        abort_if(blank($letterOfficial->signature_path), 404);
        abort_unless(
            Storage::disk(PdfSuratPengantar::DISK)->exists($letterOfficial->signature_path),
            404
        );

        return Storage::disk(PdfSuratPengantar::DISK)->response($letterOfficial->signature_path);
    }

    // -----------------------------------------------------------------
    // Pemantauan pengajuan
    // -----------------------------------------------------------------

    public function daftarPengajuan(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(LetterRequest::STATUS)],
            'rt_id' => ['nullable', 'integer'],
        ]);

        $pengajuan = LetterRequest::with(['rt:id,nomor', 'dusun:id,nama'])
            ->where('village_id', $this->village->id())
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('rt_id'), fn ($q, $id) => $q->where('rt_id', $id))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        // Daftar TIDAK memuat NIK, alamat, maupun keperluan. Ketiganya baru
        // terbuka bila operator membuka satu pengajuan — dan pembukaannya
        // tercatat sebagai jejak akses.
        $pengajuan->setCollection($pengajuan->getCollection()->map(fn (LetterRequest $p) => [
            'id' => $p->id,
            'ticket_number' => $p->ticket_number,
            'nama' => $p->nama,
            'rt' => $p->rt?->nomor,
            'dusun' => $p->dusun?->nama,
            'status' => $p->status,
            'nomor_surat' => $p->nomor_surat,
            'pdf_tersedia' => filled($p->pdf_path),
            'tanggal_pengajuan' => $p->created_at->toIso8601String(),
        ]));

        return ApiResponse::paginated($pengajuan);
    }

    public function lihatPengajuan(LetterRequest $letterRequest): JsonResponse
    {
        $this->pastikanMilikDesaIni($letterRequest->village_id);

        $this->logger->logAccess(
            $letterRequest,
            "Membuka pengajuan surat {$letterRequest->ticket_number}"
        );

        $letterRequest->load(['rt', 'dusun', 'logs.pejabat:id,nama', 'penyetujuRt:id,nama', 'penyetujuKadus:id,nama']);

        return ApiResponse::success([
            'ticket_number' => $letterRequest->ticket_number,
            'nama' => $letterRequest->nama,
            // Tersamar bahkan bagi operator: yang membutuhkan NIK utuh
            // membuka PDF-nya, dan pembukaan itu ikut tercatat.
            'nik_tersamar' => $letterRequest->nikTersamar(),
            'tempat_lahir' => $letterRequest->tempat_lahir,
            'tanggal_lahir' => $letterRequest->tanggal_lahir?->toDateString(),
            'pekerjaan' => $letterRequest->pekerjaan,
            'agama' => $letterRequest->agama,
            'status_perkawinan' => $letterRequest->status_perkawinan,
            'warga_negara' => $letterRequest->warga_negara,
            'alamat' => $letterRequest->alamat,
            'maksud_keperluan' => $letterRequest->maksud_keperluan,
            'rt' => $letterRequest->rt?->nomor,
            'dusun' => $letterRequest->dusun?->nama,
            'status' => $letterRequest->status,
            'nomor_surat' => $letterRequest->nomor_surat,
            'alasan_penolakan' => $letterRequest->rejection_reason,
            'ditolak_oleh' => $letterRequest->rejected_by,
            'pdf_tersedia' => filled($letterRequest->pdf_path),
            'tanggal_pengajuan' => $letterRequest->created_at->toIso8601String(),
            'riwayat' => $letterRequest->logs->sortBy('id')->values()->map(fn ($l) => [
                'action' => $l->action,
                'role' => $l->role,
                'actor_nama' => $l->actor_nama,
                'reason' => $l->reason,
                'waktu' => $l->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Membuat ulang PDF yang gagal dirender.
     *
     * Kegagalan rendering tidak pernah membatalkan pengajuan (lihat
     * SuratPengantarService), sehingga harus ada jalan memperbaikinya
     * belakangan tanpa meminta warga mengajukan ulang.
     */
    public function buatUlangPdf(LetterRequest $letterRequest): JsonResponse
    {
        $this->pastikanMilikDesaIni($letterRequest->village_id);

        if (! $this->surat->buatUlangPdf($letterRequest)) {
            return ApiResponse::error('Berkas PDF masih gagal dibuat. Periksa log aplikasi.', 500);
        }

        $this->logger->log('updated', $letterRequest, "Membuat ulang PDF {$letterRequest->ticket_number}");

        return ApiResponse::success(null, 'Berkas PDF dibuat ulang.');
    }

    public function unduhPdf(LetterRequest $letterRequest)
    {
        $this->pastikanMilikDesaIni($letterRequest->village_id);

        abort_if(blank($letterRequest->pdf_path), 404, 'Berkas belum tersedia.');
        abort_unless(
            Storage::disk(PdfSuratPengantar::DISK)->exists($letterRequest->pdf_path),
            404
        );

        $this->logger->logAccess(
            $letterRequest,
            "Mengunduh PDF surat {$letterRequest->ticket_number}"
        );

        return Storage::disk(PdfSuratPengantar::DISK)->download(
            $letterRequest->pdf_path,
            'Surat-Pengantar-'.$letterRequest->ticket_number.'.pdf'
        );
    }

    // -----------------------------------------------------------------
    // Dusun (baca saja; pengelolaannya ada di modul Profil)
    // -----------------------------------------------------------------

    public function daftarDusun(): JsonResponse
    {
        return ApiResponse::success(
            Dusun::where('village_id', $this->village->id())
                ->orderBy('urutan_tampil')
                ->get(['id', 'nama', 'nama_kepala_dusun'])
        );
    }

    private function pastikanMilikDesaIni(?int $villageId): void
    {
        abort_if($villageId !== null && $villageId !== $this->village->id(), 404);
    }
}
