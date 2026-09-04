<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Services\DataInfografis;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Infografis publik — PRD 9.2 & Bagian 11.
 *
 * Enam sub-halaman di bawah satu induk, mengikuti pola situs referensi.
 * Seluruhnya membaca data AGREGAT: tidak satu pun menyentuh tabel berisi data
 * pribadi (`residents`, `bansos_recipients` per individu). Perhatikan pula apa
 * yang tidak ada di kelas ini — tidak ada route yang mengembalikan daftar
 * penerima bantuan. PRD 6.6 melarangnya, dan cara paling andal menegakkan
 * larangan itu adalah dengan tidak pernah menulis jalurnya.
 */
class InfografisController extends Controller
{
    public function __construct(
        private readonly DataInfografis $infografis,
    ) {}

    public function penduduk(): Response
    {
        return Inertia::render('Publik/Infografis/Penduduk', [
            'data' => $this->infografis->penduduk(),
        ]);
    }

    public function apbdes(Request $request): Response
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return Inertia::render('Publik/Infografis/Apbdes', [
            'data' => $this->infografis->apbdes($request->integer('tahun') ?: null),
        ]);
    }

    public function stunting(): Response
    {
        return Inertia::render('Publik/Infografis/Stunting', [
            'data' => $this->infografis->stunting(),
        ]);
    }

    public function idm(Request $request): Response
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return Inertia::render('Publik/Infografis/Idm', [
            'data' => $this->infografis->idm($request->integer('tahun') ?: null),
        ]);
    }

    public function sdgs(Request $request): Response
    {
        $request->validate(['tahun' => ['nullable', 'integer', 'min:2000', 'max:2100']]);

        return Inertia::render('Publik/Infografis/Sdgs', [
            'data' => $this->infografis->sdgs($request->integer('tahun') ?: null),
        ]);
    }

    /**
     * Rekap jumlah penerima per jenis bantuan — agregat, tanpa identitas.
     *
     * Tidak ada penelusuran per orang di modul ini: halaman publik hanya
     * memuat jumlah, sehingga tidak ada jalur yang dapat memastikan apakah
     * seseorang tertentu menerima bantuan.
     */
    public function bansos(): Response
    {
        return Inertia::render('Publik/Infografis/Bansos', [
            'data' => $this->infografis->bansos(),
        ]);
    }
}
