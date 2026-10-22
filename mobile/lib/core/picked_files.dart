import 'dart:io';

import 'package:image_picker/image_picker.dart';

/// Audit M16: image_picker menyalin foto kamera/galeri ke folder cache aplikasi. Setelah foto terkirim (atau sudah dibaca ke
/// memori), salinan itu dihapus agar foto pengaduan dan bukti transfer tidak tertinggal di HP. Hanya berkas di folder cache
/// yang disentuh; foto asli di galeri tidak pernah dihapus.
Future<void> deletePickedCopies(Iterable<XFile> files) async {
  for (final file in files) {
    final path = file.path.replaceAll(r'\', '/');
    if (!path.contains('/cache/')) continue;
    try {
      await File(file.path).delete();
    } on FileSystemException {
      // Sudah terhapus atau tidak dapat diakses: tidak mengubah hasil pengiriman.
    }
  }
}
