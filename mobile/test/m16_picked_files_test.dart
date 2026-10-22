import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rt05_takeda/core/picked_files.dart';

/// Audit M16: salinan foto di folder cache aplikasi dihapus setelah terkirim; berkas di luar cache tidak disentuh.
void main() {
  test('hanya salinan di folder cache yang dihapus', () async {
    final root = await Directory.systemTemp.createTemp('rt05-foto');
    addTearDown(() => root.delete(recursive: true));
    final cache = await Directory('${root.path}/cache/image_picker').create(recursive: true);
    final copy = await File('${cache.path}/foto.jpg').writeAsBytes([1, 2, 3]);
    final gallery = await File('${root.path}/DCIM-foto.jpg').writeAsBytes([4, 5, 6]);

    await deletePickedCopies([XFile(copy.path), XFile(gallery.path), XFile('${cache.path}/sudah-tidak-ada.jpg')]);

    expect(copy.existsSync(), isFalse);
    expect(gallery.existsSync(), isTrue);
  });
}
