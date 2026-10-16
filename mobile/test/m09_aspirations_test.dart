import 'dart:convert';

import 'package:dio/dio.dart' show ResponseBody;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart' show RenderParagraph;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/format.dart' show statusLabel;
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/household/family_actions.dart' show conflictKeptTail;
import 'package:rt05_takeda/features/warga/report_actions.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// Aspirasi sintetis (bentuk mengikuti AspirationEnvelope di docs/openapi.yaml): tanpa kategori/foto.
Map<String, dynamic> aspirasiJson({
  int versi = 1,
  String status = 'diajukan',
  bool milikSaya = true,
  bool anonim = true,
  String? archivedAt,
  bool pengirimTampil = true,
}) => {
  'id': 'a-1',
  'judul': 'Usul taman bermain anak',
  'isi': 'Mohon disediakan taman bermain di lahan kosong.',
  'status': status,
  'versi': versi,
  'sembunyikan_identitas': anonim,
  'milik_saya': milikSaya,
  'pengirim': pengirimTampil ? {'id': 'u-1', 'nama': 'Warga Uji'} : null,
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

Map<String, dynamic> _body(Object? data) => Map<String, dynamic>.from(data is String ? jsonDecode(data) as Map : data as Map);

/// ListView vertikal halaman detail.
final _detailScroll = find.descendant(of: find.byType(RefreshIndicator), matching: find.byType(Scrollable)).first;

Future<void> _pumpForm(WidgetTester tester, {required FakeHandler handler, Map<String, dynamic>? existing, Widget Function(Widget form)? wrap}) async {
  final form = ReportFormPage(aspirasi: true, existing: existing == null ? null : Laporan(existing, aspirasi: true));
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(theme: buildTheme(), home: wrap?.call(form) ?? form),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  test('model aspirasi memakai field aspirasi, bukan pengaduan', () {
    final l = Laporan(aspirasiJson(pengirimTampil: false), aspirasi: true);
    expect(l.isi, 'Mohon disediakan taman bermain di lahan kosong.');
    expect(l.namaPelapor, isNull);
    expect(l.kategori, isNull);
    expect(l.foto, isEmpty);
    expect(l.diarsipkan, isNull);
    expect(Laporan(aspirasiJson(archivedAt: '2026-10-13T04:00:00Z'), aspirasi: true).diarsipkan, isNotNull);
    expect(reportLocked('ASPIRATION_LOCKED'), isTrue);
    expect(statusLabel('ditinjau'), 'Ditinjau');
  });

  testWidgets('form aspirasi hanya judul, isi, anonim; konfirmasi hanya setelah server menerima', (tester) async {
    useCompactScreen(tester, height: 1000);
    final posts = <Map<String, dynamic>>[];
    var reject = true;
    final base = apiHandler(me: akunJson);
    await _pumpForm(
      tester,
      handler: (o) {
        if (o.uri.path == '/api/v1/aspirations' && o.method == 'POST') {
          posts.add(_body(o.data));
          if (reject) return errorBody(422, 'VALIDATION_FAILED', 'Isi aspirasi terlalu panjang.');
          return jsonBody(201, {'data': aspirasiJson(), 'meta': {}});
        }
        return base(o);
      },
      wrap: (form) => Scaffold(
        body: Builder(
          builder: (context) => Center(
            child: FilledButton(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => form)),
              child: const Text('Buka form'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Buka form'));
    await tester.pumpAndSettle();

    expect(find.text('Kirim aspirasi'), findsOneWidget);
    expect(find.text('Kategori'), findsNothing);
    expect(find.text('Kamera'), findsNothing);
    expect(find.text('Galeri'), findsNothing);
    expect(find.textContaining('kritik, saran, atau usulan'), findsOneWidget);
    expect(find.textContaining('pengirim'), findsOneWidget);
    expect(find.textContaining('Foto'), findsNothing);

    // Isian kosong tidak dikirim.
    await tester.tap(find.text('Kirim'));
    await tester.pumpAndSettle();
    expect(find.text('Lengkapi judul dan isi.'), findsOneWidget);
    expect(posts, isEmpty);

    await tester.enterText(find.widgetWithText(TextField, 'Judul'), '  Usul taman bermain  ');
    await tester.enterText(find.widgetWithText(TextField, 'Isi kritik, saran, atau usulan'), 'Mohon disediakan taman bermain.');
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();

    // Server menolak: tidak ada konfirmasi, form tetap terbuka, teks tetap ada.
    await tester.tap(find.text('Kirim'));
    await tester.pumpAndSettle();
    expect(posts.single, {'judul': 'Usul taman bermain', 'isi': 'Mohon disediakan taman bermain.', 'sembunyikan_identitas': true});
    expect(find.text('Aspirasi terkirim.'), findsNothing);
    expect(find.text('Kirim aspirasi'), findsOneWidget);
    expect(find.text('Mohon disediakan taman bermain.'), findsOneWidget);

    // Server menerima: konfirmasi muncul dan form tertutup.
    reject = false;
    await tester.tap(find.text('Kirim'));
    await tester.pumpAndSettle();
    expect(posts, hasLength(2));
    expect(find.text('Aspirasi terkirim.'), findsOneWidget);
    expect(find.text('Buka form'), findsOneWidget);
  });

  testWidgets('perbaikan aspirasi: 409 mempertahankan teks lalu kirim ulang dengan versi terbaru', (tester) async {
    useCompactScreen(tester, height: 1000);
    final patches = <Map<String, dynamic>>[];
    final base = apiHandler(me: akunJson);
    await _pumpForm(
      tester,
      existing: aspirasiJson(),
      handler: (o) {
        if (o.uri.path == '/api/v1/aspirations/a-1' && o.method == 'GET') return jsonBody(200, {'data': aspirasiJson(versi: 2), 'meta': {}});
        if (o.uri.path == '/api/v1/aspirations/a-1' && o.method == 'PATCH') {
          patches.add(_body(o.data));
          if (patches.length == 1) return errorBody(409, 'VERSION_CONFLICT', 'Data sudah berubah. Muat ulang lalu coba lagi.');
          return jsonBody(200, {'data': aspirasiJson(versi: 3), 'meta': {}});
        }
        return base(o);
      },
    );
    expect(find.text('Perbaiki aspirasi'), findsOneWidget);
    expect(find.textContaining('selama aspirasi masih Diajukan'), findsOneWidget);

    await tester.enterText(find.widgetWithText(TextField, 'Judul'), 'Judul diketik warga');
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    expect(find.textContaining(conflictKeptTail), findsOneWidget);
    expect(find.text('Judul diketik warga'), findsOneWidget);
    expect(patches.single['versi'], 1);

    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    expect(patches.last, {'versi': 2, 'judul': 'Judul diketik warga', 'isi': 'Mohon disediakan taman bermain di lahan kosong.', 'sembunyikan_identitas': true});
  });

  testWidgets('aspirasi yang sudah ditinjau terkunci tanpa menghapus isian dan menyebut aspirasi', (tester) async {
    useCompactScreen(tester, height: 1000);
    final base = apiHandler(me: akunJson);
    await _pumpForm(
      tester,
      existing: aspirasiJson(),
      handler: (o) {
        if (o.uri.path == '/api/v1/aspirations/a-1' && o.method == 'PATCH') {
          return errorBody(403, 'ASPIRATION_LOCKED', 'Aspirasi hanya dapat diubah pengirimnya selama masih diajukan.');
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

  testWidgets('warga lain melihat aspirasi anonim tanpa identitas dan tanpa aksi perbaikan', (tester) async {
    useCompactScreen(tester);
    final base = apiHandler(me: akunJson);
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: (o) {
        final path = o.uri.path;
        if (path == '/api/v1/aspirations') {
          return jsonBody(200, {
            'data': [aspirasiJson(milikSaya: false, pengirimTampil: false)],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        if (path == '/api/v1/complaints') {
          return jsonBody(200, {
            'data': [],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 0},
          });
        }
        if (path == '/api/v1/aspirations/a-1') return jsonBody(200, {'data': aspirasiJson(milikSaya: false, pengirimTampil: false), 'meta': {}});
        if (path == '/api/v1/aspirations/a-1/history') {
          return jsonBody(200, {
            'data': [
              {
                'status_dari': null,
                'status_ke': 'diajukan',
                'catatan': null,
                'aktor': {'nama': 'Warga', 'peran': 'warga'},
                'created_at': '2026-10-13T03:00:00Z',
              },
            ],
            'meta': {},
          });
        }
        return base(o);
      },
    );
    await tester.tap(find.widgetWithText(NavigationDestination, 'Layanan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Aspirasi').first);
    await tester.pumpAndSettle();
    expect(find.textContaining('Identitas disembunyikan'), findsWidgets);
    expect(find.text('Warga Uji'), findsNothing);
    await tester.tap(find.text('Usul taman bermain anak').first);
    await tester.pumpAndSettle();
    expect(find.text('Detail aspirasi'), findsOneWidget);
    expect(find.text('Warga Uji'), findsNothing);
    expect(find.textContaining('Warga'), findsWidgets);
    expect(find.text('Perbaiki aspirasi'), findsNothing);
    expect(find.textContaining('Ubah ke'), findsNothing);
  });

  testWidgets('judul aspirasi tidak terpecah di samping chip status pada skala teks 2,0', (tester) async {
    useCompactScreen(tester, textScale: 2.0, height: 900);
    final base = apiHandler(me: akunJson);
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: (o) {
        final path = o.uri.path;
        if (path == '/api/v1/aspirations') {
          return jsonBody(200, {
            'data': [aspirasiJson(status: 'ditinjau')],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        if (path == '/api/v1/complaints') {
          return jsonBody(200, {
            'data': [],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 0},
          });
        }
        if (path == '/api/v1/aspirations/a-1') return jsonBody(200, {'data': aspirasiJson(status: 'ditinjau'), 'meta': {}});
        if (path == '/api/v1/aspirations/a-1/history') return jsonBody(200, {'data': _history, 'meta': {}});
        return base(o);
      },
    );
    await tester.tap(find.byType(NavigationDestination).at(1));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Aspirasi').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Aspirasi').first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Usul taman bermain anak').first);
    await tester.pumpAndSettle();

    final title = tester.getRect(find.text('Usul taman bermain anak'));
    final chip = tester.getRect(find.text('Ditinjau').first);
    expect(chip.top, greaterThanOrEqualTo(title.bottom - 1), reason: 'chip di bawah judul, bukan di sampingnya');
    final paragraph = tester.renderObject<RenderParagraph>(find.text('Usul taman bermain anak'));
    // Judul memakai lebar penuh kartu: tiap kata utuh (tidak ada baris yang berakhir di tengah kata).
    final lines = paragraph.getBoxesForSelection(const TextSelection(baseOffset: 0, extentOffset: 23)).length;
    expect(lines, lessThanOrEqualTo(4));
  });

  testWidgets('pengurus: alur Diajukan, Ditinjau, Selesai, buka ulang beralasan, lalu arsip dengan alasan', (tester) async {
    useCompactScreen(tester, height: 900);
    var status = 'diajukan';
    var versi = 1;
    String? archivedAt;
    final statusBodies = <Map<String, dynamic>>[];
    Object? archiveBody;
    final base = apiHandler(me: () => akunJson(peran: 'pengurus'));
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: (o) {
        final path = o.uri.path;
        if (path == '/api/v1/aspirations') {
          return jsonBody(200, {
            'data': [aspirasiJson(milikSaya: false, status: status, versi: versi, archivedAt: archivedAt)],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        if (path == '/api/v1/complaints') {
          return jsonBody(200, {
            'data': [],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 0},
          });
        }
        if (path == '/api/v1/aspirations/a-1') {
          return jsonBody(200, {'data': aspirasiJson(milikSaya: false, status: status, versi: versi, archivedAt: archivedAt), 'meta': {}});
        }
        if (path == '/api/v1/aspirations/a-1/history') return jsonBody(200, {'data': _history, 'meta': {}});
        if (path == '/api/v1/admin/aspirations/a-1/status') {
          final body = _body(o.data);
          statusBodies.add(body);
          status = body['status'] as String;
          versi += 1;
          return jsonBody(200, {'data': aspirasiJson(milikSaya: false, status: status, versi: versi), 'meta': {}});
        }
        if (path == '/api/v1/admin/aspirations/a-1/archive') {
          archiveBody = o.data;
          archivedAt = '2026-10-13T04:00:00Z';
          return ResponseBody.fromString('', 204);
        }
        return base(o);
      },
    );
    await tester.tap(find.widgetWithText(NavigationDestination, 'Layanan'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Aspirasi').first);
    await tester.pumpAndSettle();
    expect(find.text('Termasuk arsip'), findsOneWidget);
    await tester.tap(find.text('Usul taman bermain anak').first);
    await tester.pumpAndSettle();

    expect(find.textContaining('Warga Uji'), findsWidgets, reason: 'pengurus melihat pengirim walau anonim');
    expect(find.text('Perbaiki aspirasi'), findsNothing);

    // Tombol status memakai BusyButton (spinner berputar saat dialog terbuka), jadi pumpAndSettle tidak selesai.
    Future<void> settle() async {
      for (var i = 0; i < 6; i++) {
        await tester.pump(const Duration(milliseconds: 250));
      }
    }

    Future<void> change(String label, {String? note}) async {
      await tester.scrollUntilVisible(find.text(label), 200, scrollable: _detailScroll);
      await tester.tap(find.text(label));
      await settle();
      if (note != null) await tester.enterText(find.byType(TextField).last, note);
      await tester.tap(find.text('Simpan'));
      await settle();
    }

    await change('Ubah ke Ditinjau', note: 'Dibahas rapat RT');
    await change('Ubah ke Selesai');
    expect(statusBodies.map((b) => b['status']).toList(), ['ditinjau', 'selesai']);
    expect(statusBodies.map((b) => b['expected_version']).toList(), [1, 2]);

    // Buka ulang tanpa alasan tidak dikirim; dengan alasan dikirim.
    await change('Buka ulang (Ditinjau)');
    expect(statusBodies, hasLength(2), reason: 'alasan buka ulang wajib');
    await change('Buka ulang (Ditinjau)', note: 'Usulan lanjutan');
    expect(statusBodies.last, {'status': 'ditinjau', 'catatan': 'Usulan lanjutan', 'expected_version': 3});

    await tester.scrollUntilVisible(find.text('Arsipkan aspirasi'), 200, scrollable: _detailScroll);
    await tester.tap(find.text('Arsipkan aspirasi'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();
    expect(archiveBody, isNull, reason: 'alasan arsip wajib');
    await tester.tap(find.text('Arsipkan aspirasi'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField).last, 'Duplikat usulan lain');
    await tester.tap(find.text('Simpan'));
    await tester.pumpAndSettle();

    expect(jsonDecode(jsonEncode(archiveBody)), {'alasan': 'Duplikat usulan lain'});
    expect(find.textContaining('Aspirasi diarsipkan'), findsWidgets);
    expect(find.text('Arsipkan aspirasi'), findsNothing);
    expect(find.textContaining('Ubah ke'), findsNothing);
    expect(find.textContaining('Buka ulang'), findsNothing);
  });
}
