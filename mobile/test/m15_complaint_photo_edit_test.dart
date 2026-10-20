import 'dart:async';
import 'dart:typed_data';

import 'package:dio/dio.dart' show FormData, RequestOptions, ResponseBody;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// UAT M15: pelapor menambah/menghapus foto pada form perbaikan pengaduan (langsung ke server).
Map<String, dynamic> _laporan(int versi, List<Map<String, dynamic>> foto) => {
  'id': 'l-1',
  'kategori': {'id': 'k-1', 'nama': 'Kebersihan'},
  'judul': 'Saluran air tersumbat di gang uji',
  'deskripsi': 'Air menggenang setelah hujan.',
  'status': 'diajukan',
  'versi': versi,
  'sembunyikan_identitas': false,
  'milik_saya': true,
  'pelapor': {'id': 'a-1', 'nama': 'Warga Uji'},
  'foto': foto,
  'created_at': '2026-10-19T03:00:00Z',
  'updated_at': '2026-10-19T03:00:00Z',
  'archived_at': null,
};

class _GalleryPicker extends Fake implements ImagePicker {
  @override
  Future<XFile?> pickImage({
    required ImageSource source,
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    CameraDevice preferredCameraDevice = CameraDevice.rear,
    bool requestFullMetadata = true,
  }) async => XFile.fromData(Uint8List.fromList([1, 2, 3]), name: 'baru.jpg', path: 'baru.jpg');
}

void main() {
  testWidgets('tambah lalu hapus foto memperbarui versi dan daftar foto', (tester) async {
    useCompactScreen(tester, height: 1400);
    final calls = <String>[];
    final base = apiHandler(me: akunJson);
    FutureOr<ResponseBody> handler(RequestOptions o) {
      final path = o.uri.path;
      if (path == '/api/v1/complaints/l-1/photos' && o.method == 'POST') {
        final fields = Map.fromEntries((o.data as FormData).fields);
        calls.add('POST versi=${fields['versi']}');
        return jsonBody(200, {
          'data': _laporan(2, [
            {'id': 'f-1', 'status': 'siap', 'url': null},
            {'id': 'f-2', 'status': 'diproses', 'url': null},
          ]),
        });
      }
      if (path == '/api/v1/complaints/l-1/photos/f-1' && o.method == 'DELETE') {
        calls.add('DELETE versi=${o.uri.queryParameters['versi']}');
        return jsonBody(200, {
          'data': _laporan(3, [
            {'id': 'f-2', 'status': 'diproses', 'url': null},
          ]),
        });
      }
      return base(o);
    }

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
          httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
        ],
        child: MaterialApp(
          theme: buildTheme(),
          home: ReportFormPage(
            aspirasi: false,
            existing: Laporan(
              _laporan(1, [
                {'id': 'f-1', 'status': 'siap', 'url': null},
              ]),
              aspirasi: false,
            ),
            picker: _GalleryPicker(),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Foto (1 dari 5)'), findsOneWidget);

    await tester.tap(find.text('Galeri'));
    await tester.pumpAndSettle();
    expect(find.text('Foto (2 dari 5)'), findsOneWidget);
    expect(find.textContaining('Foto ditambahkan'), findsOneWidget);

    await tester.tap(find.byTooltip('Hapus foto 1'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Hapus'));
    await tester.pumpAndSettle();
    expect(find.text('Foto (1 dari 5)'), findsOneWidget);
    expect(calls, ['POST versi=1', 'DELETE versi=2']);
    expect(tester.takeException(), isNull);
  });
}
