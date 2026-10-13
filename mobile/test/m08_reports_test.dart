import 'dart:convert';

import 'package:dio/dio.dart' show ResponseBody;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/household/family_actions.dart' show conflictKeptTail;
import 'package:rt05_takeda/features/warga/report_actions.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// Laporan sintetis milik akun uji (bentuk mengikuti ComplaintEnvelope di docs/openapi.yaml).
Map<String, dynamic> laporanJson({int versi = 1, String status = 'diajukan', bool milikSaya = true, String? archivedAt}) => {
  'id': 'l-1',
  'kategori': {'id': 'k-1', 'nama': 'Kebersihan'},
  'judul': 'Saluran air tersumbat di gang uji',
  'deskripsi': 'Air menggenang setelah hujan.',
  'status': status,
  'versi': versi,
  'sembunyikan_identitas': true,
  'milik_saya': milikSaya,
  'pelapor': {'id': 'a-1', 'nama': 'Warga Uji'},
  'foto': [
    {'id': 'f-1', 'status': 'diproses', 'lebar': 2400, 'tinggi': 1200, 'url': null},
    {'id': 'f-2', 'status': 'gagal', 'lebar': 40, 'tinggi': 40, 'url': null},
  ],
  'created_at': '2026-10-13T03:00:00Z',
  'updated_at': '2026-10-13T03:00:00Z',
  'archived_at': archivedAt,
};

final _history = [
  {
    'status_dari': null,
    'status_ke': 'diajukan',
    'catatan': null,
    'aktor': {'nama': 'Warga Uji', 'peran': 'warga'},
    'created_at': '2026-10-13T03:00:00Z',
  },
];

class _DeniedCameraPicker extends Fake implements ImagePicker {
  final sources = <ImageSource>[];

  @override
  Future<XFile?> pickImage({
    required ImageSource source,
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    CameraDevice preferredCameraDevice = CameraDevice.rear,
    bool requestFullMetadata = true,
  }) async {
    sources.add(source);
    if (source == ImageSource.camera) throw PlatformException(code: 'camera_access_denied', message: 'The user did not allow camera access.');
    return XFile('galeri-uji.jpg');
  }
}

void main() {
  test('body perbaikan memakai field domain yang benar dan versi', () {
    expect(reportUpdateBody(aspirasi: false, judul: ' A ', isi: ' B ', anonim: true, versi: 3), {
      'versi': 3,
      'judul': 'A',
      'deskripsi': 'B',
      'sembunyikan_identitas': true,
    });
    expect(reportUpdateBody(aspirasi: true, judul: 'A', isi: 'B', anonim: false, versi: 1)['isi'], 'B');
    expect(reportLocked('COMPLAINT_LOCKED'), isTrue);
    expect(reportLocked('VERSION_CONFLICT'), isFalse);
    expect(photoPickMessage('camera_access_denied', camera: true), contains('Pengaturan'));
    expect(photoPickMessage('photo_access_denied', camera: false), contains('galeri'));
    expect(photoPickMessage('lain', camera: true), isNull);
  });

  testWidgets('pemilik memperbaiki laporan: 409 mempertahankan teks lalu kirim ulang dengan versi terbaru', (tester) async {
    useCompactScreen(tester, height: 1400);
    var detailGets = 0;
    final patches = <Map<String, dynamic>>[];
    final base = apiHandler(me: akunJson);
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: (o) {
        final path = o.uri.path;
        if (path == '/api/v1/complaints' || path == '/api/v1/aspirations') {
          return jsonBody(200, {
            'data': [if (path == '/api/v1/complaints') laporanJson()],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        if (path == '/api/v1/complaints/l-1' && o.method == 'GET') {
          detailGets += 1;
          return jsonBody(200, {'data': laporanJson(versi: detailGets >= 2 ? 2 : 1), 'meta': {}});
        }
        if (path == '/api/v1/complaints/l-1/history') return jsonBody(200, {'data': _history, 'meta': {}});
        if (path == '/api/v1/complaints/l-1' && o.method == 'PATCH') {
          patches.add(Map<String, dynamic>.from(o.data is String ? jsonDecode(o.data as String) as Map : o.data as Map));
          if (patches.length == 1) return errorBody(409, 'VERSION_CONFLICT', 'Data sudah berubah. Muat ulang lalu coba lagi.');
          return jsonBody(200, {'data': laporanJson(versi: 3), 'meta': {}});
        }
        return base(o);
      },
    );

    await tester.tap(find.widgetWithText(NavigationDestination, 'Layanan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Saluran air tersumbat di gang uji').first);
    await tester.pumpAndSettle();
    expect(find.text('Sedang diproses'), findsOneWidget);
    expect(find.text('Gagal diproses'), findsOneWidget);
    expect(find.textContaining('Tarik ke bawah'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('Perbaiki laporan'), 200, scrollable: _detailScroll);
    await tester.tap(find.text('Perbaiki laporan'));
    await tester.pumpAndSettle();
    await tester.enterText(find.widgetWithText(TextField, 'Judul'), 'Judul yang diketik warga');
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();

    expect(find.textContaining(conflictKeptTail), findsOneWidget);
    expect(find.text('Judul yang diketik warga'), findsOneWidget);
    expect(patches.single['versi'], 1);

    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    expect(patches, hasLength(2));
    expect(patches.last, {'versi': 2, 'judul': 'Judul yang diketik warga', 'deskripsi': 'Air menggenang setelah hujan.', 'sembunyikan_identitas': true});
    expect(find.text('Perubahan tersimpan.'), findsOneWidget);
    expect(find.text('Detail pengaduan'), findsOneWidget);
  });

  testWidgets('laporan yang sudah diproses pengurus terkunci tanpa menghapus isian', (tester) async {
    useCompactScreen(tester, height: 1400);
    final base = apiHandler(me: akunJson);
    await _pumpForm(
      tester,
      existing: laporanJson(),
      handler: (o) {
        if (o.uri.path == '/api/v1/complaints/l-1' && o.method == 'PATCH') {
          return errorBody(403, 'COMPLAINT_LOCKED', 'Pengaduan hanya dapat diubah pemiliknya selama masih diajukan.');
        }
        return base(o);
      },
    );
    await tester.enterText(find.widgetWithText(TextField, 'Judul'), 'Isian tetap ada');
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    expect(find.textContaining(reportLockedTail), findsOneWidget);
    expect(find.text('Isian tetap ada'), findsOneWidget);
    final button = tester.widget<FilledButton>(find.ancestor(of: find.text('Simpan perubahan'), matching: find.byType(FilledButton)));
    expect(button.onPressed, isNull);
  });

  testWidgets('izin kamera ditolak memberi panduan; galeri tetap menambah foto', (tester) async {
    useCompactScreen(tester, height: 1400);
    final picker = _DeniedCameraPicker();
    final base = apiHandler(
      me: akunJson,
      fixtures: {
        '/api/v1/complaint-categories': {
          'data': [
            {'id': 'k-1', 'nama': 'Kebersihan'},
          ],
        },
      },
    );
    await _pumpForm(tester, picker: picker, handler: base);

    await tester.tap(find.text('Kamera'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Izin kamera ditolak'), findsOneWidget);
    expect(find.text('Foto 1'), findsNothing);

    await tester.tap(find.text('Galeri'));
    await tester.pumpAndSettle();
    expect(find.text('Foto 1'), findsOneWidget);
    expect(picker.sources, [ImageSource.camera, ImageSource.gallery]);
  });

  testWidgets('pengurus mengarsipkan dengan alasan wajib; arsip menyembunyikan aksi status', (tester) async {
    useCompactScreen(tester);
    var archived = false;
    Object? archiveBody;
    final base = apiHandler(me: () => akunJson(peran: 'pengurus'));
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: (o) {
        final path = o.uri.path;
        if (path == '/api/v1/complaints' || path == '/api/v1/aspirations') {
          return jsonBody(200, {
            'data': [if (path == '/api/v1/complaints') laporanJson(milikSaya: false)],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        if (path == '/api/v1/complaints/l-1') {
          return jsonBody(200, {'data': laporanJson(milikSaya: false, archivedAt: archived ? '2026-10-13T04:00:00Z' : null), 'meta': {}});
        }
        if (path == '/api/v1/complaints/l-1/history') return jsonBody(200, {'data': _history, 'meta': {}});
        if (path == '/api/v1/admin/complaints/l-1/archive') {
          archived = true;
          archiveBody = o.data;
          return ResponseBody.fromString('', 204);
        }
        return base(o);
      },
    );
    await tester.tap(find.widgetWithText(NavigationDestination, 'Layanan'));
    await tester.pumpAndSettle();
    expect(find.text('Termasuk arsip'), findsOneWidget);
    await tester.tap(find.text('Saluran air tersumbat di gang uji').first);
    await tester.pumpAndSettle();
    expect(find.text('Perbaiki laporan'), findsNothing);

    await tester.scrollUntilVisible(find.text('Arsipkan laporan'), 200, scrollable: _detailScroll);
    await tester.tap(find.text('Arsipkan laporan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(archiveBody, isNull, reason: 'alasan kosong tidak dikirim');

    await tester.tap(find.text('Arsipkan laporan'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last, 'Duplikat laporan lain');
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(jsonDecode(jsonEncode(archiveBody)), {'alasan': 'Duplikat laporan lain'});
    expect(find.textContaining('Laporan diarsipkan'), findsWidgets);
    expect(find.text('Arsipkan laporan'), findsNothing);
    expect(find.textContaining('Ubah ke'), findsNothing);
  });
}

/// ListView vertikal halaman detail (bukan galeri foto horizontal di dalamnya).
final _detailScroll = find.descendant(of: find.byType(RefreshIndicator), matching: find.byType(Scrollable)).first;

Future<void> _pumpForm(WidgetTester tester, {required FakeHandler handler, Map<String, dynamic>? existing, ImagePicker? picker}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(
        theme: buildTheme(),
        home: ReportFormPage(aspirasi: false, existing: existing == null ? null : Laporan(existing, aspirasi: false), picker: picker),
      ),
    ),
  );
  await tester.pumpAndSettle();
}
