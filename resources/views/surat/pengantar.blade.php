{{--
  Blangko Surat Pengantar RT/Dusun — Desa Mpanau.

  Tata letaknya mengikuti blangko cetak yang dipakai desa: kop tiga baris di
  atas garis tebal, judul bergaris bawah, daftar keterangan berlabel, lalu dua
  blok tanda tangan (Ketua Dusun di kiri, Ketua RT di kanan).

  Dua hal sengaja dipertahankan dari blangko aslinya, meski tampak ganjil bila
  dibaca sebagai halaman web:

  - Pilihan Agama, Status Pernikahan, dan Warga Negara dicetak LENGKAP dengan
    pilihan yang tidak dipakai DICORET, bukan hanya menampilkan yang terpilih.
    Begitulah blangko ini dibaca dan diarsipkan petugas; menggantinya dengan
    satu nilai tunggal membuat surat tampak berbeda dari yang biasa diterima.
  - Catatan kaki "*Coret yang tidak perlu" ikut dicetak, karena tanda bintang
    pada baris-baris di atas merujuk padanya.

  Tata letak memakai <table>, bukan flex/grid: dompdf merender keduanya secara
  tidak lengkap, dan tabel adalah satu-satunya cara yang keluarannya dapat
  diandalkan sama di semua versi.
--}}
@php
    use App\Models\LetterRequest;

    $namaDesa = $desa['nama_desa'] ?? 'Desa Mpanau';
    $kecamatan = $desa['wilayah']['kecamatan'] ?? 'Sigi Biromaru';
    $kabupaten = $desa['wilayah']['kabupaten'] ?? 'Sigi';

    $nomorRt = $p->rt?->nomor ?? '—';

    // Tanpa awalan "Dusun": kalimat blangko sudah memuat kata itu sendiri,
    // sehingga nama "Dusun 4" akan terbaca "Ketua Dusun Dusun 4".
    $namaDusun = $p->dusun?->sebutanRingkas() ?? '—';

    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    $tglLahir = $p->tanggal_lahir
        ? $p->tanggal_lahir->day
            .' - '.str_pad((string) $p->tanggal_lahir->month, 2, '0', STR_PAD_LEFT)
            .' - '.$p->tanggal_lahir->year
        : '—';

    // Tanggal surat = saat terbit (disetujui Kepala Dusun). Untuk draft
    // dipakai tanggal pengajuan agar kolomnya tidak kosong.
    $tglSurat = $p->approved_kadus_at ?? $p->created_at;

    // Mencetak sederet pilihan dengan yang tidak terpilih dicoret.
    $pilihan = function (array $opsi, ?string $terpilih) {
        return collect($opsi)->map(function (string $o) use ($terpilih) {
            $cocok = mb_strtolower($o) === mb_strtolower((string) $terpilih);

            return $cocok
                ? '<span class="dipilih">'.e($o).'</span>'
                : '<span class="dicoret">'.e($o).'</span>';
        })->implode(' / ');
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Pengantar {{ $p->ticket_number }}</title>
    <style>
        @page { margin: 22mm 20mm 18mm 20mm; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.45;
            color: #000;
        }

        .kop { text-align: center; }
        .kop div { font-weight: bold; font-size: 13pt; letter-spacing: .3px; }

        /* Garis tebal pemisah kop, persis blangko cetak. */
        .garis-kop { border-bottom: 3px solid #000; margin: 6px 0 14px 0; }

        .judul { text-align: center; margin-bottom: 2px; }
        .judul .teks {
            font-weight: bold;
            font-size: 13pt;
            text-decoration: underline;
            letter-spacing: .5px;
        }
        .nomor { text-align: center; margin-bottom: 16px; }

        .pembuka { text-align: justify; margin-bottom: 12px; text-indent: 28px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data td { vertical-align: top; padding: 2.5px 0; }
        table.data td.label { width: 44%; padding-left: 22px; }
        table.data td.pemisah { width: 12px; }
        table.data td.isi { width: 54%; }

        /* Isian yang pada blangko ditulis tangan: tetap bergaris titik-titik
           agar terbaca sebagai isian, bukan bagian dari format. */
        .isian { border-bottom: 1px dotted #000; display: block; min-height: 15pt; }

        .dicoret { text-decoration: line-through; color: #333; }
        .dipilih { font-weight: bold; }

        .penutup { margin-top: 16px; text-align: justify; }

        table.ttd { width: 100%; margin-top: 26px; border-collapse: collapse; }
        table.ttd td { vertical-align: top; width: 50%; text-align: center; }

        .ruang-ttd { height: 74px; }
        .ruang-ttd img { max-height: 70px; max-width: 190px; }

        .nama-pejabat {
            font-weight: bold;
            text-decoration: underline;
            display: inline-block;
            min-width: 170px;
        }
        .garis-nama {
            border-bottom: 1px dotted #000;
            display: inline-block;
            min-width: 170px;
            height: 14pt;
        }

        .kaki { margin-top: 34px; font-size: 10pt; }

        /* Cap DRAFT hanya pada berkas yang belum disetujui seluruhnya, supaya
           berkas yang terlanjur beredar tidak disangka surat sah. */
        .cap-draft {
            position: fixed;
            top: 38%;
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 62pt;
            font-weight: bold;
            color: #999;
            transform: rotate(-24deg);
            letter-spacing: 10px;
        }

        .tiket {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            font-size: 8pt;
            color: #444;
        }
    </style>
</head>
<body>

@unless($final)
    <div class="cap-draft">DRAFT</div>
@endunless

<div class="kop">
    <div>RUKUN TETANGGA {{ $nomorRt }} DUSUN/RW {{ strtoupper($namaDusun) }}</div>
    <div>{{ strtoupper($namaDesa) }}, KECAMATAN {{ strtoupper($kecamatan) }}</div>
    <div>KABUPATEN {{ strtoupper($kabupaten) }}</div>
</div>
<div class="garis-kop"></div>

<div class="judul"><span class="teks">SURAT PENGANTAR</span></div>
<div class="nomor">
    Nomor : {{ $p->nomor_surat ?: '….. /RT.'.$nomorRt.'/DUSUN '.$namaDusun }}
</div>

<p class="pembuka">
    Saya yang bertanda tangan dibawah ini Ketua RT {{ $nomorRt }} {{ $namaDesa }}
    Kecamatan {{ $kecamatan }}, menerangkan bahwa :
</p>

<table class="data">
    <tr>
        <td class="label">Nama</td>
        <td class="pemisah">:</td>
        <td class="isi"><span class="isian">{{ $p->nama }}</span></td>
    </tr>
    <tr>
        <td class="label">NIK</td>
        <td class="pemisah">:</td>
        <td class="isi"><span class="isian">{{ $p->nik }}</span></td>
    </tr>
    <tr>
        <td class="label">Tempat dan Tanggal Lahir</td>
        <td class="pemisah">:</td>
        <td class="isi"><span class="isian">{{ $p->tempat_lahir }}, {{ $tglLahir }}</span></td>
    </tr>
    <tr>
        <td class="label">Pekerjaan</td>
        <td class="pemisah">:</td>
        <td class="isi"><span class="isian">{{ $p->pekerjaan }}</span></td>
    </tr>
    <tr>
        <td class="label">Agama</td>
        <td class="pemisah">:</td>
        <td class="isi">{!! $pilihan(LetterRequest::AGAMA, $p->agama) !!} *</td>
    </tr>
    <tr>
        <td class="label">Status Pernikahan</td>
        <td class="pemisah">:</td>
        <td class="isi">{!! $pilihan(LetterRequest::STATUS_PERKAWINAN, $p->status_perkawinan) !!} *</td>
    </tr>
    <tr>
        <td class="label">Warga Negara</td>
        <td class="pemisah">:</td>
        <td class="isi">{!! $pilihan(LetterRequest::WARGA_NEGARA, $p->warga_negara) !!} *</td>
    </tr>
    <tr>
        <td class="label">Alamat</td>
        <td class="pemisah">:</td>
        <td class="isi"><span class="isian">{{ $p->alamat }}</span></td>
    </tr>
    <tr>
        <td class="label">Maksud dan Keperluan</td>
        <td class="pemisah">:</td>
        <td class="isi"><span class="isian">{{ $p->maksud_keperluan }}</span></td>
    </tr>
</table>

<p class="penutup">
    Demikian Surat Pengantar ini kami berikan guna tindak lanjut ke proses selanjutnya.
</p>

<table class="ttd">
    <tr>
        <td>
            {{-- Blok kiri baru terisi setelah Kepala Dusun menyetujui. --}}
            <div>Ketua Dusun {{ $namaDusun }}</div>
            <div class="ruang-ttd">
                @if($ttdKadus)
                    <img src="{{ $ttdKadus }}" alt="">
                @endif
            </div>
            @if($final && $p->penyetujuKadus)
                <span class="nama-pejabat">{{ $p->penyetujuKadus->nama }}</span>
            @else
                <span class="garis-nama"></span>
            @endif
        </td>

        <td>
            <div>
                {{ $namaDesa }}, {{ $tglSurat->day }} - {{ $bulan[$tglSurat->month] }} - {{ $tglSurat->year }}
            </div>
            <div>Hormat Kami</div>
            <div>Ketua RT {{ $nomorRt }}</div>
            <div class="ruang-ttd">
                @if($ttdRt)
                    <img src="{{ $ttdRt }}" alt="">
                @endif
            </div>
            @if($final && $p->penyetujuRt)
                <span class="nama-pejabat">{{ $p->penyetujuRt->nama }}</span>
            @else
                <span class="garis-nama"></span>
            @endif
        </td>
    </tr>
</table>

<p class="kaki">*Coret yang tidak perlu</p>

<div class="tiket">
    Nomor Tiket: {{ $p->ticket_number }}
    @unless($final)
        &nbsp;·&nbsp; Dokumen ini BELUM disetujui dan tidak berlaku sebagai surat resmi.
    @endunless
</div>

</body>
</html>
