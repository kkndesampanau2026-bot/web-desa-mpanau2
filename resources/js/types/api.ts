/** Bentuk data dari API — cermin dari Resource & controller di backend. */

export interface Profil {
  sambutan: string | null
  nama_kepala_desa: string | null
  foto_kepala_desa: string | null
  sejarah: string | null
  visi: string | null
  misi: string[]
  bagan_pemerintahan: string | null
  bagan_bpd: string | null
  geografis: {
    luas_desa_m2: number | null
    luas_desa_hektar: number | null
    jumlah_penduduk: number | null
    batas: {
      utara: string | null
      timur: string | null
      selatan: string | null
      barat: string | null
    }
  }
  peta: {
    latitude: string | number | null
    longitude: string | number | null
  }
}

export interface Official {
  id: number
  nama: string
  jabatan: string
  foto: string | null
  periode_mulai: string | null
  periode_selesai: string | null
  urutan_tampil: number
  /** Tingkat pada bagan struktur: 0 = puncak (Kepala Desa), membesar ke bawah. */
  tingkat: number
}

export interface BpdMember {
  id: number
  nama: string
  jabatan: string
  foto: string | null
  dapil: string | null
  periode_mulai: string | null
  periode_selesai: string | null
}

export interface BeritaRingkas {
  id: number
  judul: string
  slug: string
  ringkasan: string | null
  gambar_utama: string | null
  tanggal_publish: string | null
  jumlah_dilihat: number
  kategori?: { id: number; nama: string; slug: string }
  penulis?: string | null
}

export interface BeritaDetail extends BeritaRingkas {
  konten: string
  galeri_gambar: string[]
  tags?: { nama: string; slug: string }[]
  meta: {
    title: string | null
    description: string | null
    og_image: string | null
  }
}

export interface KategoriBerita {
  id: number
  nama: string
  slug: string
  jumlah_berita: number
}

export interface AlbumRingkas {
  id: number
  nama_album: string
  slug: string
  tanggal_kegiatan: string | null
  deskripsi: string | null
  cover_image: string | null
  jumlah_foto: number
}

export interface AlbumDetail extends Omit<AlbumRingkas, 'cover_image' | 'jumlah_foto'> {
  foto: { id: number; url: string; caption: string | null; alt_text: string }[]
}

export interface Pengaturan {
  nama_desa: string | null
  kode_wilayah: string | null
  logo: string | null
  wilayah: {
    kelurahan: string | null
    kecamatan: string | null
    kabupaten: string | null
    provinsi: string | null
    kode_pos: string | null
  }
  alamat_kantor: string | null
  jam_kerja: Record<string, { buka?: string; tutup?: string; libur?: boolean }>
  kontak: { telepon: string | null; email: string | null; whatsapp: string | null }
  nomor_telepon_penting: { nama_layanan: string; nomor: string }[]
  sosial_media: { platform: string; url: string }[]
}

/** Tujuh kategori persis seperti PRD 6.16. */
export interface StatistikKunjungan {
  hari_ini: number
  kemarin: number
  minggu_ini: number
  minggu_lalu: number
  bulan_ini: number
  bulan_lalu: number
  total: number
}

/** Metadata paginasi dari ApiResponse::paginated(). */
export interface MetaPaginasi {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

// --- Fase 3: Infografis ---

export interface Kategori {
  label: string
  jumlah: number
}

export interface InfografisPenduduk {
  periode: string
  ringkasan: {
    total_penduduk: number
    total_kk: number
    total_laki: number
    total_perempuan: number
    total_wajib_pilih: number
  }
  breakdown: {
    kelompok_umur: Kategori[]
    dusun: Kategori[]
    pendidikan: Kategori[]
    pekerjaan: Kategori[]
    perkawinan: Kategori[]
    agama: Kategori[]
  }
  riwayat: { periode: string; total_penduduk: number; total_kk: number }[]
}

export interface InfografisApbdes {
  tahun: number
  /** Tahun anggaran yang dipublikasikan, terbaru lebih dulu — isi dropdown. */
  tahun_tersedia: number[]
  ringkasan: {
    total_pendapatan: number
    total_belanja: number
    surplus_defisit: number
  }
  kelompok: {
    kelompok: string
    total_anggaran: number
    total_realisasi: number
    kategori: {
      nama: string
      total_anggaran: number
      total_realisasi: number
      items: { nama_item: string; jumlah_anggaran: number; jumlah_realisasi: number | null }[]
    }[]
  }[]
  tren: { tahun: number; pendapatan: number; belanja: number }[]
}

export interface InfografisStunting {
  periode: string
  ringkasan: {
    jumlah_balita_diukur: number
    jumlah_kasus_stunting: number
    persentase_prevalensi: number | null
  }
  per_dusun: {
    dusun: string
    jumlah_balita_diukur: number
    jumlah_kasus_stunting: number
    persentase_prevalensi: number | null
  }[]
  riwayat: { periode: string; jumlah_balita_diukur: number; jumlah_kasus_stunting: number }[]
}

export interface InfografisIdm {
  tahun: number
  skor: { iks: number | null; ike: number | null; ikl: number | null; idm: number | null }
  status_idm: string | null
  target: {
    status: string | null
    skor_minimal: number | null
    penambahan_dibutuhkan: number | null
  }
  indikator: {
    no: number
    nama_indikator: string
    skor: number | null
    keterangan: string | null
    kegiatan_rekomendasi: string | null
    nilai_tambah: number | null
    pihak_pelaksana: string[]
  }[]
  tahun_tersedia: number[]
}

export interface InfografisSdgs {
  tahun: number
  goals: {
    goal_number: number
    nama_goal: string
    skor: number | null
    deskripsi_capaian: string | null
  }[]
  skor_rata_rata: number
}

// --- Fase 4: Bansos & PPID ---

export interface InfografisBansos {
  tahun_anggaran: number
  total_penerima: number
  per_jenis: {
    jenis_bantuan: string
    deskripsi: string | null
    sumber_dana: string | null
    jumlah_penerima: number
  }[]
  tahun_tersedia: number[]
}

export interface HasilCekBansos {
  ditemukan: boolean
  pesan: string
  hasil: {
    nama: string
    jenis_bantuan: string | null
    tahun_anggaran: number
    status: string
    nominal: number | null
  }[]
}

export interface DasarHukumPpid {
  judul_regulasi: string
  nomor_regulasi: string | null
  tahun: number | null
  file: string | null
}

export type JenisInformasiPpid = 'berkala' | 'serta-merta' | 'setiap-saat'

export interface InformasiPpid {
  judul: string
  deskripsi: string | null
  kategori: string | null
  periode: string | null
  tingkat_urgensi: 'rendah' | 'sedang' | 'tinggi' | null
  tanggal_publish: string | null
  file: string | null
}

export interface PermohonanPpid {
  nomor_registrasi: string
  status: 'diajukan' | 'diverifikasi' | 'diproses' | 'selesai' | 'ditolak'
  tanggal_pengajuan: string
}

export interface StatusPermohonanPpid extends PermohonanPpid {
  nama_pemohon: string
  informasi_diminta: string
  tanggapan_admin: string | null
  alasan_penolakan: string | null
  dokumen_balasan: string | null
  tanggal_tanggapan: string | null
}

// --- Fase 5: Potensi, Wisata & UMKM ---

export interface Koordinat {
  latitude: string | number
  longitude: string | number
}

export interface PotensiItem {
  id: number
  kategori: string
  judul: string
  slug: string
  deskripsi: string | null
  foto: string | null
  koordinat: Koordinat | null
}

export interface DaftarPotensi {
  items: PotensiItem[]
  kategori_tersedia: string[]
}

export type PotensiDetail = PotensiItem

export interface WisataRingkas {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  alamat: string | null
  harga_tiket: string | null
  foto_utama: string | null
  jumlah_foto: number
}

export interface WisataDetail {
  id: number
  nama: string
  slug: string
  deskripsi: string | null
  alamat: string | null
  koordinat: Koordinat | null
  jam_operasional: Record<string, { buka?: string; tutup?: string; libur?: boolean }>
  harga_tiket: string | null
  kontak_pengelola: string | null
  fasilitas: string[]
  foto: { url: string; caption: string | null; alt_text: string }[]
}

export interface Penjual {
  nama: string
  whatsapp: string | null
  whatsapp_link: string | null
}

export interface ProdukRingkas {
  id: number
  nama_produk: string
  slug: string
  kategori: string | null
  harga: number | null
  satuan: string | null
  tersedia: boolean
  foto_utama: string | null
  penjual: Penjual
}

export interface ProdukDetail extends ProdukRingkas {
  deskripsi: string | null
  alamat_penjual: string | null
  foto: { url: string; alt_text: string }[]
}

export interface KategoriProduk {
  kategori: string
  jumlah: number
}

// --- Fase 6: Peta & Pengaduan ---

export interface TitikLokasi {
  id: number
  nama: string
  kategori: string
  deskripsi: string | null
  alamat: string | null
  dusun: string | null
  latitude: number
  longitude: number
  foto: string | null
  icon_marker: string | null
  /** Tautan internal bila titik ini destinasi wisata / produk terdaftar. */
  tautan: string | null
}

export interface DataPeta {
  titik: TitikLokasi[]
  kategori_tersedia: string[]
  pusat_peta: { latitude: number; longitude: number }
}

export type KategoriPengaduan =
  | 'Umum'
  | 'Sosial'
  | 'Keamanan'
  | 'Kesehatan'
  | 'Kebersihan'
  | 'Permintaan'

export interface TandaTerimaPengaduan {
  nomor_tiket: string
  status: 'baru' | 'diproses' | 'selesai' | 'ditolak'
  tanggal_pengaduan: string
  jumlah_lampiran: number
}

export interface StatusPengaduan {
  nomor_tiket: string
  nama: string
  kategori_pengaduan: string
  isi_pengaduan: string
  status: 'baru' | 'diproses' | 'selesai' | 'ditolak'
  tanggapan_admin: string | null
  alasan_penolakan: string | null
  tanggal_pengaduan: string
  tanggal_tanggapan: string | null
}

// --- Surat Pengantar RT/Dusun ---

export type StatusSurat =
  | 'MENUNGGU_APPROVAL_RT'
  | 'MENUNGGU_APPROVAL_KADUS'
  | 'DISETUJUI'
  | 'DITOLAK'

export interface PilihanRt {
  id: number
  nomor: string
  dusun: string | null
}

export interface TandaTerimaSurat {
  ticket_number: string
  nama: string
  status: StatusSurat
  tanggal_pengajuan: string
}

export type AksiRiwayatSurat =
  | 'diajukan'
  | 'disetujui_rt'
  | 'ditolak_rt'
  | 'disetujui_kadus'
  | 'ditolak_kadus'
  | 'surat_terbit'

export interface RiwayatSurat {
  action: AksiRiwayatSurat
  waktu: string | null
}

export interface StatusPengajuanSurat {
  ticket_number: string
  nama: string
  /** Selalu tersamar: 727103********13. NIK utuh hanya ada di PDF. */
  nik_tersamar: string
  rt: string | null
  dusun: string | null
  jenis_surat: string
  maksud_keperluan: string
  status: StatusSurat
  nomor_surat: string | null
  tanggal_pengajuan: string
  disetujui_rt_pada: string | null
  disetujui_kadus_pada: string | null
  ditolak_pada: string | null
  ditolak_oleh: 'RT' | 'KADUS' | null
  alasan_penolakan: string | null
  pdf_tersedia: boolean
  riwayat: RiwayatSurat[]
}
