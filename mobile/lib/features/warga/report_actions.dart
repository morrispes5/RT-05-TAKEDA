/// Aturan murni form laporan (pengaduan/aspirasi) yang diuji langsung. Server tetap pemilik
/// validasi, status, dan kepemilikan; fungsi ini hanya membentuk body dan pesan.
library;

Map<String, dynamic> reportUpdateBody({required bool aspirasi, required String judul, required String isi, required bool anonim, required int versi}) => {
  'versi': versi,
  'judul': judul.trim(),
  aspirasi ? 'isi' : 'deskripsi': isi.trim(),
  'sembunyikan_identitas': anonim,
};

/// Laporan tidak lagi dapat diubah warga (sudah diproses pengurus, diarsipkan, atau bukan miliknya).
bool reportLocked(String code) => code == 'COMPLAINT_LOCKED' || code == 'ASPIRATION_LOCKED';

const reportLockedTail = 'Teks yang Anda ketik masih ada di layar bila perlu disalin.';

/// Pesan saat image_picker menolak karena izin (kode PlatformException plugin).
String? photoPickMessage(String code, {required bool camera}) => switch (code) {
  'camera_access_denied' =>
    'Izin kamera ditolak. Anda tetap dapat memilih dari galeri atau mengirim tanpa foto. '
        'Untuk memakai kamera, izinkan di Pengaturan > Aplikasi > RT05 TAKEDA > Izin.',
  'photo_access_denied' => 'Izin galeri ditolak. Anda tetap dapat memakai kamera atau mengirim tanpa foto.',
  'no_available_camera' => 'Kamera tidak tersedia di perangkat ini. Pilih foto dari galeri.',
  _ => null,
};

String photoStatusLabel(String status) => switch (status) {
  'diproses' => 'Sedang diproses',
  'gagal' => 'Gagal diproses',
  _ => 'Siap',
};
