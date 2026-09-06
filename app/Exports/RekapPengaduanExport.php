<?php

namespace App\Exports;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\LampiranPengaduanService;
use GdImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap pengaduan warga dalam bentuk berkas Excel.
 *
 * Berkas ini memuat DATA PRIBADI pelapor — nama, nomor WhatsApp, isi aduan,
 * dan (sejak lampiran ikut ditanam) FOTO yang diunggahnya. Karena itu ia hanya
 * keluar lewat endpoint ber-permission `respond-complaint`, dan setiap
 * pengunduhan dicatat pada audit trail (lihat `EksporPengaduanController`).
 * Jangan pernah menyajikannya lewat URL storage atau tautan yang dapat ditebak.
 *
 * ## Kolom Lampiran
 *
 * Gambar (JPEG/PNG/WebP) DITANAM sebagai gambar sungguhan di dalam sel,
 * bukan sekadar nama berkasnya, sehingga rekap dapat dibaca tanpa membuka
 * dashboard.
 *
 * PDF TIDAK dapat ditanam. Lembar kerja hanya mengenal gambar sebagai objek
 * yang dapat digambar; menampilkan PDF di dalam sel menuntut objek OLE
 * tertanam, sesuatu yang hanya dapat dibuat Excel sendiri dan tidak tersedia
 * pada PhpSpreadsheet — dan bahkan bila ada, hasilnya berupa ikon yang tetap
 * harus diklik ganda, dan hanya bekerja pada Excel desktop di Windows.
 * Sebagai gantinya nama berkas PDF ditulis sebagai TAUTAN ke endpoint admin
 * yang membukanya di peramban.
 *
 * ## Kenapa `FromCollection`, bukan `FromQuery`
 *
 * Gambar ditempelkan pada koordinat sel (mis. "F7"), jadi nomor baris setiap
 * pengaduan harus sudah diketahui SEBELUM lembar ditulis. `FromQuery` menunda
 * pembacaan sampai baris dipetakan satu per satu, sehingga posisi itu tidak
 * pernah tersedia pada saat `drawings()` dipanggil. Koleksinya dibaca sekali
 * lalu dipakai bersama oleh keduanya.
 */
class RekapPengaduanExport extends StringValueBinder implements FromCollection, WithCustomValueBinder, WithDrawings, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use Exportable;

    /** Ukuran maksimum thumbnail dalam piksel. */
    private const THUMB_LEBAR = 120;

    private const THUMB_TINGGI = 90;

    /** Jarak mendatar antar-thumbnail pada satu sel. */
    private const THUMB_JEDA = 8;

    private ?Collection $baris = null;

    public function __construct(
        private readonly int $villageId,
        private readonly ?string $status = null,
        private readonly ?string $kategori = null,
    ) {}

    /**
     * Dibaca SEKALI lalu dipakai bersama `collection()`, `drawings()`, dan
     * `registerEvents()`. Ketiganya harus melihat urutan baris yang persis
     * sama, kalau tidak gambar akan tertempel pada pengaduan orang lain.
     */
    public function collection(): Collection
    {
        return $this->baris ??= Complaint::query()
            ->where('village_id', $this->villageId)
            ->with('attachments:id,complaint_id,nama_asli,path,mime_type')
            ->when($this->status, fn ($q, string $s) => $q->where('status', $s))
            ->when($this->kategori, fn ($q, string $k) => $q->where('kategori_pengaduan', $k))
            ->orderByDesc('created_at')
            ->get();
    }

    public function title(): string
    {
        return 'Rekap Pengaduan';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'No Tiket Aduan',
            'Nama',
            'No Telepon',
            'Kategori Aduan',
            'Isi Aduan',
            'Lampiran',
            'Status Aduan',
            'Tanggapan untuk Pelapor',
        ];
    }

    /**
     * @param  Complaint  $pengaduan
     * @return list<string>
     */
    public function map($pengaduan): array
    {
        return [
            $pengaduan->nomor_tiket,
            $pengaduan->nama,
            $pengaduan->no_telepon_wa,
            $pengaduan->kategori_pengaduan,
            $pengaduan->isi_pengaduan,
            $this->teksLampiran($pengaduan),
            $this->status($pengaduan),
            $this->tanggapan($pengaduan),
        ];
    }

    /**
     * Thumbnail setiap lampiran bergambar, ditempel pada sel Lampiran barisnya.
     *
     * @return list<MemoryDrawing>
     */
    public function drawings(): array
    {
        $gambar = [];

        foreach ($this->collection() as $i => $pengaduan) {
            $barisExcel = $i + 2;   // baris 1 adalah judul kolom
            $kolom = 0;

            foreach ($this->gambarSaja($pengaduan) as $lampiran) {
                $thumb = $this->thumbnail($lampiran);

                // Berkas rusak atau format yang tidak dapat dibaca GD: barisnya
                // sudah memuat nama berkas sebagai teks, jadi rekap tetap
                // lengkap meski gambarnya tidak ikut.
                if ($thumb === null) {
                    continue;
                }

                $drawing = new MemoryDrawing();
                $drawing->setName($lampiran->nama_asli);
                $drawing->setDescription($lampiran->nama_asli);
                $drawing->setImageResource($thumb);
                $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                $drawing->setCoordinates('F'.$barisExcel);
                $drawing->setOffsetX($kolom * (self::THUMB_LEBAR + self::THUMB_JEDA) + 4);
                $drawing->setOffsetY(4);

                $gambar[] = $drawing;
                $kolom++;
            }
        }

        return $gambar;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $peristiwa) {
                $sheet = $peristiwa->sheet->getDelegate();

                $this->aturKolom($sheet);
                $this->aturBaris($sheet);
            },
        ];
    }

    // -----------------------------------------------------------------
    // Tata letak
    // -----------------------------------------------------------------

    private function aturKolom(Worksheet $sheet): void
    {
        $lebar = [
            'A' => 20,   // nomor tiket
            'B' => 22,   // nama
            'C' => 16,   // telepon
            'D' => 14,   // kategori
            'E' => 45,   // isi aduan
            'F' => 56,   // lampiran — muat tiga thumbnail bersebelahan
            'G' => 12,   // status
            'H' => 45,   // tanggapan
        ];

        foreach ($lebar as $kolom => $ukuran) {
            $sheet->getColumnDimension($kolom)->setAutoSize(false);
            $sheet->getColumnDimension($kolom)->setWidth($ukuran);
        }

        // Isi aduan & tanggapan kerap berparagraf; tanpa pembungkusan baris,
        // satu aduan panjang membuat kolomnya melebar sampai berlembar-lembar.
        foreach (['E', 'F', 'H'] as $kolom) {
            $sheet->getStyle($kolom)->getAlignment()->setWrapText(true);
        }

        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function aturBaris(Worksheet $sheet): void
    {
        foreach ($this->collection() as $i => $pengaduan) {
            $barisExcel = $i + 2;

            $jumlahGambar = $this->gambarSaja($pengaduan)->count();
            $pdf = $this->pdfSaja($pengaduan);

            if ($jumlahGambar > 0) {
                // Tinggi baris dihitung dalam POIN, sedangkan thumbnail dalam
                // piksel: 1 px = 0,75 pt pada 96 dpi. Tanpa penyesuaian ini
                // gambar menjulur menutupi baris di bawahnya.
                $sheet->getRowDimension($barisExcel)
                    ->setRowHeight((self::THUMB_TINGGI + 12) * 0.75 + ($pdf->count() * 12));

                // Teks nama PDF didorong ke dasar sel agar tidak tertutup
                // thumbnail yang mengambang di bagian atasnya.
                $sheet->getStyle('F'.$barisExcel)->getAlignment()
                    ->setVertical(Alignment::VERTICAL_BOTTOM);
            }

            /*
             * Excel hanya mengenal SATU hyperlink per sel. Bila sebuah
             * pengaduan membawa lebih dari satu PDF, tautannya dipasang pada
             * berkas pertama dan sisanya tetap tertulis sebagai nama — dapat
             * dibuka lewat layar Pengaduan. Batas unggah tiga berkas membuat
             * keadaan itu jarang, dan menaruh URL penuh di dalam sel justru
             * membuat kolomnya tidak terbaca.
             */
            $pertama = $pdf->first();

            if ($pertama !== null) {
                $sheet->getCell('F'.$barisExcel)->getHyperlink()->setUrl(
                    route('admin.pengaduan.lampiran', [
                        'pengaduan' => $pengaduan->id,
                        'lampiran' => $pertama->id,
                    ])
                );
                $sheet->getStyle('F'.$barisExcel)->getFont()
                    ->setUnderline(true)
                    ->getColor()->setARGB('FF0F6CBD');
            }
        }
    }

    // -----------------------------------------------------------------
    // Lampiran
    // -----------------------------------------------------------------

    /** @return Collection<int, ComplaintAttachment> */
    private function gambarSaja(Complaint $pengaduan): Collection
    {
        return $pengaduan->attachments->filter(
            fn (ComplaintAttachment $l) => str_starts_with((string) $l->mime_type, 'image/')
        )->values();
    }

    /** @return Collection<int, ComplaintAttachment> */
    private function pdfSaja(Complaint $pengaduan): Collection
    {
        return $pengaduan->attachments->filter(
            fn (ComplaintAttachment $l) => $l->mime_type === 'application/pdf'
        )->values();
    }

    /**
     * Teks pada sel Lampiran.
     *
     * Gambar tidak menyumbang teks apa pun — ia hadir sebagai gambar. Yang
     * ditulis hanya nama berkas PDF, karena PDF-lah satu-satunya yang tidak
     * dapat diperlihatkan isinya di dalam lembar kerja.
     */
    private function teksLampiran(Complaint $pengaduan): string
    {
        if ($pengaduan->attachments->isEmpty()) {
            return 'Tidak ada';
        }

        $pdf = $this->pdfSaja($pengaduan);

        if ($pdf->isEmpty()) {
            return '';
        }

        return $pdf->map(fn (ComplaintAttachment $l) => $l->nama_asli)->implode("\n");
    }

    /**
     * Thumbnail GD dari sebuah lampiran bergambar.
     *
     * Dikecilkan lebih dulu, bukan ditanam apa adanya: satu lampiran boleh
     * mencapai 5 MB, dan tiga di antaranya per pengaduan akan membuat rekap
     * berukuran ratusan megabyte — berkas yang tidak dapat dikirim lewat surel
     * maupun dibuka di komputer kantor desa.
     */
    private function thumbnail(ComplaintAttachment $lampiran): ?GdImage
    {
        $disk = Storage::disk(LampiranPengaduanService::DISK);

        if (! $disk->exists($lampiran->path)) {
            return null;
        }

        $sumber = match ($lampiran->mime_type) {
            'image/jpeg' => @imagecreatefromjpeg($disk->path($lampiran->path)),
            'image/png' => @imagecreatefrompng($disk->path($lampiran->path)),
            'image/webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($disk->path($lampiran->path))
                : false,
            default => false,
        };

        if (! $sumber instanceof GdImage) {
            return null;
        }

        $lebar = imagesx($sumber);
        $tinggi = imagesy($sumber);

        if ($lebar < 1 || $tinggi < 1) {
            imagedestroy($sumber);

            return null;
        }

        // Skala mengikuti sisi yang paling menonjol, sehingga foto tegak
        // maupun mendatar sama-sama muat tanpa gepeng.
        $skala = min(self::THUMB_LEBAR / $lebar, self::THUMB_TINGGI / $tinggi, 1);

        $thumb = imagescale($sumber, max(1, (int) round($lebar * $skala)), max(1, (int) round($tinggi * $skala)));

        // Sumbernya dibebaskan SEGERA: berkas 5 MB dapat menjadi puluhan MB
        // sebagai bitmap GD, dan menahannya sampai seluruh baris selesai
        // adalah cara termudah kehabisan memori pada desa yang aktif.
        imagedestroy($sumber);

        return $thumb instanceof GdImage ? $thumb : null;
    }

    // -----------------------------------------------------------------
    // Kolom teks lain
    // -----------------------------------------------------------------

    private function status(Complaint $pengaduan): string
    {
        return match ($pengaduan->status) {
            'baru' => 'Baru',
            'diproses' => 'Diproses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            default => (string) $pengaduan->status,
        };
    }

    /**
     * Apa yang benar-benar dibaca pelapor pada halaman lacak.
     *
     * Halaman itu menampilkan DUA hal: "Tanggapan Petugas" dan, bila ada,
     * "Alasan Penolakan". Rekap ini menggabungkan keduanya supaya isinya sama
     * dengan yang dilihat warga — mengambil `tanggapan_admin` saja akan
     * membuat seluruh pengaduan yang ditolak tampak tidak pernah dijawab.
     */
    private function tanggapan(Complaint $pengaduan): string
    {
        $bagian = array_filter([
            $pengaduan->tanggapan_admin,
            filled($pengaduan->alasan_penolakan)
                ? 'Alasan penolakan: '.$pengaduan->alasan_penolakan
                : null,
        ]);

        return $bagian === [] ? 'Belum ditanggapi' : implode("\n\n", $bagian);
    }
}
