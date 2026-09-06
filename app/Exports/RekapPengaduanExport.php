<?php

namespace App\Exports;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rekap pengaduan warga dalam bentuk berkas Excel.
 *
 * Berkas ini memuat DATA PRIBADI pelapor — nama, nomor WhatsApp, dan isi
 * aduannya. Karena itu ia hanya keluar lewat endpoint ber-permission
 * `respond-complaint`, dan setiap pengunduhan dicatat pada audit trail
 * (lihat `EksporPengaduanController`). Jangan pernah menyajikannya lewat URL
 * storage atau tautan yang dapat ditebak.
 *
 * `FromQuery` dipakai, bukan `FromCollection`: rekap ini tumbuh seiring waktu,
 * dan memuat seluruh baris ke memori sekaligus adalah cara termudah membuat
 * ekspor berhenti bekerja justru pada desa yang paling aktif.
 *
 * Kelasnya MEWARISI `StringValueBinder` supaya setiap sel ditulis sebagai
 * teks. Tanpa itu, value binder bawaan menafsirkan "081234567890" sebagai
 * bilangan dan angka nol di depannya lenyap — nomor WhatsApp pelapor menjadi
 * salah pada berkas yang justru dipakai menghubunginya. Menambahkan apostrof
 * di depan nilai TIDAK menyelesaikannya: apostrof itu konvensi antarmuka
 * Excel, bukan format berkasnya, dan akan ikut tersimpan sebagai karakter.
 */
class RekapPengaduanExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  int  $villageId  desa yang sedang dilayani
     * @param  string|null  $status  penyaring opsional, sepadan dengan layar CMS
     * @param  string|null  $kategori  penyaring opsional
     */
    public function __construct(
        private readonly int $villageId,
        private readonly ?string $status = null,
        private readonly ?string $kategori = null,
    ) {}

    public function query(): Builder
    {
        return Complaint::query()
            ->where('village_id', $this->villageId)
            // Lampiran ikut dimuat di muka; tanpa ini setiap baris memicu
            // kuerinya sendiri saat dipetakan.
            ->with('attachments:id,complaint_id,nama_asli')
            ->when($this->status, fn (Builder $q, string $s) => $q->where('status', $s))
            ->when($this->kategori, fn (Builder $q, string $k) => $q->where('kategori_pengaduan', $k))
            ->orderByDesc('created_at');
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
            $this->lampiran($pengaduan),
            $this->status($pengaduan),
            $this->tanggapan($pengaduan),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Isi aduan & tanggapan kerap berparagraf; tanpa pembungkusan baris,
        // satu aduan panjang membuat kolomnya melebar sampai berlembar-lembar.
        foreach (['E', 'F', 'H'] as $kolom) {
            $sheet->getStyle($kolom)->getAlignment()->setWrapText(true);
            $sheet->getColumnDimension($kolom)->setAutoSize(false);
            $sheet->getColumnDimension($kolom)->setWidth(45);
        }

        $sheet->getStyle('A1:H1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function lampiran(Complaint $pengaduan): string
    {
        if ($pengaduan->attachments->isEmpty()) {
            return 'Tidak ada';
        }

        /*
         * Nama berkas ASLI yang diunggah warga, bukan path penyimpanannya.
         *
         * Path di disk bernama UUID dan tidak berarti bagi siapa pun yang
         * membaca rekap ini; berkasnya sendiri hidup di disk privat dan hanya
         * dapat dibuka lewat layar Pengaduan yang ber-permission.
         */
        return $pengaduan->attachments
            ->map(fn ($l) => $l->nama_asli)
            ->implode("\n");
    }

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
