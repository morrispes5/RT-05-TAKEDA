<?php

namespace App\Support;

class SiteContent
{
    public static function photos(): array
    {
        return json_decode(file_get_contents(resource_path('data/dokumentasi.json')), true, flags: JSON_THROW_ON_ERROR)['foto'];
    }

    public static function articles(): array
    {
        $articles = json_decode(file_get_contents(resource_path('data/artikel.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($articles as &$article) {
            $text = $article['intro'].' '.implode(' ', array_column($article['sections'], 'body'));
            $article['minutes'] = max(1, (int) ceil(count(preg_split('/\s+/u', trim($text))) / 180));
        }

        return $articles;
    }

    public static function modules(): array
    {
        return [
            'warga' => ['title' => 'Data warga', 'icon' => 'warga', 'description' => 'Pendataan warga, keluarga, rumah, dan riwayat penghuni dalam satu tempat.', 'columns' => ['Rumah', 'Keluarga', 'Penghuni', 'Status verifikasi'], 'flow' => ['Data rumah', 'Keluarga', 'Penghuni'], 'action' => 'Tambah data warga', 'note' => 'Data kependudukan hanya boleh diakses pengurus berwenang setelah autentikasi dan pembatasan hak akses tersedia.'],
            'pengaduan' => ['title' => 'Pengaduan', 'icon' => 'pengaduan', 'description' => 'Tinjau laporan lingkungan dan catat tindak lanjutnya hingga selesai.', 'columns' => ['Laporan', 'Kategori', 'Diajukan', 'Status'], 'flow' => ['Diajukan', 'Diproses', 'Selesai'], 'action' => 'Tinjau laporan', 'note' => 'Identitas pelapor dan lampiran tidak ditampilkan pada pratinjau publik.'],
            'aspirasi' => ['title' => 'Aspirasi', 'icon' => 'aspirasi', 'description' => 'Ruang untuk meninjau usulan warga dan merencanakan tindak lanjut bersama.', 'columns' => ['Usulan', 'Topik', 'Diterima', 'Status'], 'flow' => ['Terkirim', 'Ditinjau', 'Ditindaklanjuti', 'Selesai'], 'action' => 'Tinjau aspirasi', 'note' => 'Usulan dan tanggapan warga akan tersedia setelah layanan operasional diaktifkan.'],
            'agenda' => ['title' => 'Agenda', 'icon' => 'agenda', 'description' => 'Susun jadwal, informasi kegiatan, dan daftar partisipasi warga.', 'columns' => ['Kegiatan', 'Waktu', 'Tempat', 'Publikasi'], 'flow' => ['Draf kegiatan', 'Peninjauan', 'Publikasi'], 'action' => 'Buat agenda', 'note' => 'Belum ada jadwal kegiatan yang dipublikasikan melalui antarmuka ini.'],
            'pengumuman' => ['title' => 'Pengumuman', 'icon' => 'pengumuman', 'description' => 'Siapkan informasi resmi agar warga mendapatkan kabar yang jelas.', 'columns' => ['Judul', 'Penerima', 'Tanggal', 'Publikasi'], 'flow' => ['Draf', 'Peninjauan', 'Terbit'], 'action' => 'Tulis pengumuman', 'note' => 'Penerbitan dan pengiriman notifikasi belum aktif.'],
            'iuran' => ['title' => 'Pencatatan iuran', 'icon' => 'iuran', 'description' => 'Catat pembayaran yang diterima langsung oleh pengurus per rumah dan periode.', 'columns' => ['Rumah', 'Periode', 'Pembayaran offline', 'Pencatatan'], 'flow' => ['Pembayaran offline', 'Pencatatan pengurus', 'Riwayat'], 'action' => 'Catat pembayaran', 'note' => 'Rancangan tarif Rp75.000 per rumah per bulan; dapat diubah menurut periode berlaku. Tidak ada pembayaran online atau status lunas aktual pada pratinjau.'],
            'keuangan' => ['title' => 'Laporan keuangan', 'icon' => 'iuran', 'description' => 'Tinjau pemasukan, pengeluaran, dan laporan kas berdasarkan periode.', 'columns' => ['Keterangan', 'Periode', 'Jenis transaksi', 'Nominal'], 'flow' => ['Pencatatan', 'Pemeriksaan', 'Laporan & audit'], 'action' => 'Tambah pencatatan', 'note' => 'Saldo, nilai transaksi, dan laporan keuangan asli tidak tersedia di sini.'],
            'konten' => ['title' => 'Konten website', 'icon' => 'foto', 'description' => 'Siapkan profil, fasilitas, dokumentasi, dan artikel untuk website publik.', 'columns' => ['Judul konten', 'Jenis', 'Peninjauan', 'Publikasi'], 'flow' => ['Draf konten', 'Verifikasi & izin', 'Publikasi'], 'action' => 'Buat konten', 'note' => 'Foto dan informasi perlu ditinjau sebelum publikasi. Penyuntingan dan unggah belum aktif.'],
            'pengaturan' => ['title' => 'Pengaturan', 'icon' => 'kunci', 'description' => 'Rancangan pengelolaan akun, peran pengurus, dan token registrasi warga.', 'columns' => ['Pengaturan', 'Cakupan', 'Hak akses', 'Status'], 'flow' => ['Autentikasi', 'Hak akses', 'Catatan audit'], 'action' => 'Simpan pengaturan', 'note' => 'Tidak ada akun operasional atau token rahasia pada pratinjau ini. Pengaturan hanya akan aktif dengan otorisasi server.'],
        ];
    }

    public static function paths(): array
    {
        return array_merge(['/', '/profil', '/fasilitas', '/dokumentasi', '/artikel', '/kontak', '/pengurus'], array_map(fn ($a) => '/artikel/'.$a['slug'], self::articles()), array_map(fn ($key) => '/pengurus/'.$key, array_keys(self::modules())));
    }
}
