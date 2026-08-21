<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BpdMember;
use App\Services\ActivityLogger;
use App\Services\CurrentVillage;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * CMS Anggota BPD — PRD 5.4 & 9.1.
 */
class BpdMemberController extends Controller
{
    public function __construct(
        private readonly CurrentVillage $village,
        private readonly ActivityLogger $logger,
        private readonly MediaService $media,
    ) {}

    public function index(): JsonResponse
    {
        $data = BpdMember::where('village_id', $this->village->id())
            ->orderBy('urutan_tampil')
            ->orderBy('nama')
            ->get();

        return ApiResponse::success($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);
        $data['village_id'] = $this->village->id();
        $data = $this->prosesFoto($request, $data);

        $anggota = BpdMember::create($data);
        $this->bersihkanCache();

        $this->logger->log('created', $anggota, "Menambah anggota BPD: {$anggota->nama}");

        return ApiResponse::success($anggota, 'Data anggota BPD berhasil ditambahkan.', 201);
    }

    public function update(Request $request, BpdMember $bpdMember): JsonResponse
    {
        $this->pastikanMilikDesaIni($bpdMember);

        $sebelum = $bpdMember->getOriginal();
        $bpdMember->update($this->prosesFoto($request, $this->validasi($request), $bpdMember->foto));
        $this->bersihkanCache();

        $this->logger->log(
            'updated', $bpdMember, "Memperbarui anggota BPD: {$bpdMember->nama}",
            $sebelum, $bpdMember->getAttributes()
        );

        return ApiResponse::success($bpdMember, 'Data anggota BPD berhasil diperbarui.');
    }

    public function destroy(BpdMember $bpdMember): JsonResponse
    {
        $this->pastikanMilikDesaIni($bpdMember);

        $sebelum = $bpdMember->getOriginal();
        $nama = $bpdMember->nama;

        $this->media->hapus($bpdMember->foto);
        $bpdMember->delete();
        $this->bersihkanCache();

        $this->logger->log('deleted', $bpdMember, "Menghapus anggota BPD: {$nama}", $sebelum);

        return ApiResponse::success(message: 'Data anggota BPD berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', Rule::in(BpdMember::URUTAN_JABATAN)],
            'dapil' => ['nullable', 'string', 'max:255'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
            'urutan_tampil' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status_aktif' => ['nullable', 'boolean'],
            'foto' => MediaService::aturanGambar(),
        ], MediaService::pesanValidasi('foto'));
    }

    /**
     * Mengganti objek berkas hasil validasi dengan path tempat ia disimpan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prosesFoto(Request $request, array $data, ?string $fotoLama = null): array
    {
        if ($request->hasFile('foto')) {
            $data['foto'] = $this->media->simpanGambar($request->file('foto'), 'bpd', $fotoLama);
        } else {
            unset($data['foto']);
        }

        return $data;
    }

    private function pastikanMilikDesaIni(BpdMember $anggota): void
    {
        abort_unless($anggota->village_id === $this->village->id(), 404);
    }

    private function bersihkanCache(): void
    {
        Cache::forget('bpd:'.$this->village->id());
    }
}
