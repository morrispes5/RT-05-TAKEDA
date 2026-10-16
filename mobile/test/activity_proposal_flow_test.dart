import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart' show FormData;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/core/widgets.dart';
import 'package:rt05_takeda/features/warga/activity_proposals.dart';

import 'support/fakes.dart';

/// Alur usulan kegiatan dan standar alert aksi (docs/USULAN_KEGIATAN.md §7–8).
final _now = DateTime(2026, 10, 16, 10, 0);

Map<String, dynamic> usulanJson({String status = 'diajukan', int versi = 1, bool mine = false, List<Map<String, dynamic>> bentrok = const []}) => {
  'id': 'u-1',
  'jenis': 'olahraga',
  'nama': 'Badminton bersama di lapangan RT 05',
  'deskripsi': 'Badminton warga, terbuka untuk semua umur.',
  'lokasi': 'Lapangan RT 05',
  'mulai_at': '2026-10-23T09:00:00Z',
  'selesai_at': '2026-10-23T11:00:00Z',
  'perkiraan_peserta': 20,
  'kebutuhan': 'Izin memakai lapangan.',
  'status': status,
  'versi': versi,
  'agenda_id': status == 'disetujui' ? 'ag-1' : null,
  'milik_saya': mine,
  'pengusul': {'id': 'a-1', 'nama': 'Sri Uji', 'rumah': 'D5-23'},
  'dokumen': [
    {'id': 'd-1', 'mime': 'text/plain', 'bytes': 2048, 'urutan': 1, 'url': '/api/v1/activity-proposals/u-1/documents/d-1'},
  ],
  'agenda_bentrok': bentrok,
  'created_at': '2026-10-16T03:00:00Z',
};

Future<void> _pump(WidgetTester tester, Widget page, FakeHandler handler) async {
  tester.view.devicePixelRatio = 3;
  tester.view.physicalSize = const Size(360 * 3, 1400 * 3);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(
        theme: buildTheme(),
        home: Scaffold(
          body: Builder(
            builder: (context) => Center(
              child: FilledButton(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => page)),
                child: const Text('Buka'),
              ),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
  await tester.tap(find.text('Buka'));
  await tester.pumpAndSettle();
}

/// BusyButton memutar spinner selama dialog terbuka, jadi pumpAndSettle tidak selesai.
Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 200));
  }
}

Future<void> _fillValidForm(WidgetTester tester) async {
  await tester.tap(find.text('Jenis kegiatan'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('Olahraga').last);
  await tester.pumpAndSettle();
  await tester.enterText(find.widgetWithText(TextField, 'Nama kegiatan'), 'Badminton bersama di lapangan RT 05');
  await tester.tap(find.text('Tanggal dan jam mulai'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();
  await tester.enterText(find.widgetWithText(TextField, 'Lokasi'), 'Lapangan RT 05');
  await tester.enterText(find.widgetWithText(TextField, 'Deskripsi dan susunan acara'), 'Badminton warga, terbuka untuk semua umur.');
  await tester.enterText(find.widgetWithText(TextField, 'Perkiraan peserta (opsional)'), '20');
  await tester.ensureVisible(find.byType(Checkbox));
  await tester.pumpAndSettle();
  await tester.tap(find.byType(Checkbox));
  await tester.pumpAndSettle();
  expect(tester.widget<Checkbox>(find.byType(Checkbox)).value, isTrue);
}

void main() {
  testWidgets('kirim kosong: tidak ada request, field merah, dan alert gagal', (tester) async {
    var posts = 0;
    await _pump(tester, ActivityProposalFormPage(now: () => _now), (o) {
      if (o.method == 'POST') posts++;
      return jsonBody(200, {'data': {}});
    });
    await tester.scrollUntilVisible(find.text('Kirim usulan'), 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Kirim usulan'));
    await _settle(tester);

    expect(posts, 0);
    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
    expect(find.text('Usulan belum dapat dikirim'), findsOneWidget);
    expect(find.text('Centang pernyataan penanggung jawab.'), findsOneWidget);
    await tester.scrollUntilVisible(find.text('Pilih jenis kegiatan.'), -200, scrollable: find.byType(Scrollable).first);
    expect(find.text('Pilih jenis kegiatan.'), findsOneWidget);
  });

  testWidgets('kirim valid: konfirmasi ringkasan, request multipart, dialog hasil, lalu form tertutup', (tester) async {
    FormData? sent;
    await _pump(tester, ActivityProposalFormPage(now: () => _now), (o) {
      if (o.method == 'POST' && o.uri.path == '/api/v1/activity-proposals') {
        sent = o.data as FormData;
        return jsonBody(201, {'data': usulanJson(mine: true), 'meta': {}});
      }
      return jsonBody(200, {'data': {}});
    });
    await _fillValidForm(tester);
    await tester.scrollUntilVisible(find.text('Kirim usulan'), 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Kirim usulan'));
    await _settle(tester);

    expect(find.text('Kirim usulan ke pengurus?'), findsOneWidget);
    expect(find.textContaining('Kegiatan masuk agenda hanya setelah disetujui'), findsOneWidget);
    expect(sent, isNull, reason: 'belum terkirim sebelum dikonfirmasi');
    await tester.tap(find.widgetWithText(FilledButton, 'Kirim usulan').last);
    await _settle(tester);

    final fields = {for (final f in sent!.fields) f.key: f.value};
    expect(fields['jenis'], 'olahraga');
    expect(fields['pernyataan'], '1');
    expect(fields['perkiraan_peserta'], '20');
    expect(DateTime.parse(fields['selesai_at']!).difference(DateTime.parse(fields['mulai_at']!)), const Duration(hours: 2));
    expect(find.text('Usulan terkirim'), findsOneWidget);
    expect(find.textContaining('semua warga diberi tahu'), findsOneWidget);
    await tester.tap(find.text('Mengerti'));
    await tester.pumpAndSettle();
    expect(find.text('Buka'), findsOneWidget, reason: 'form tertutup setelah sukses');
  });

  testWidgets('server menolak: alert gagal, pesan di field, isian tetap', (tester) async {
    await _pump(tester, ActivityProposalFormPage(now: () => _now), (o) {
      if (o.method == 'POST') {
        return jsonBody(422, {
          'error': {
            'code': 'VALIDATION_FAILED',
            'message': 'Isian tidak valid.',
            'request_id': 'r-1',
            'fields': {
              'mulai_at': ['Kegiatan diajukan paling cepat 24 jam sebelum mulai.'],
            },
          },
        });
      }
      return jsonBody(200, {'data': {}});
    });
    await _fillValidForm(tester);
    await tester.scrollUntilVisible(find.text('Kirim usulan'), 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Kirim usulan'));
    await _settle(tester);
    await tester.tap(find.widgetWithText(FilledButton, 'Kirim usulan').last);
    await _settle(tester);

    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
    expect(find.text('Usulan terkirim'), findsNothing);
    expect(find.text('Kegiatan diajukan paling cepat 24 jam sebelum mulai.'), findsWidgets);
    expect(find.text('Badminton bersama di lapangan RT 05'), findsOneWidget);
  });

  testWidgets('lampiran: DOCX ditolak dengan alert, TXT diterima dengan alert info', (tester) async {
    final dir = Directory.systemTemp.createTempSync('rt05');
    addTearDown(() => dir.deleteSync(recursive: true));
    final docx = File('${dir.path}/proposal.docx')..writeAsStringSync('PK');
    final txt = File('${dir.path}/susunan-acara.txt')..writeAsStringSync('Susunan acara');
    var next = docx;
    await _pump(
      tester,
      ActivityProposalFormPage(now: () => _now, pickDocument: () async => (name: next.uri.pathSegments.last, path: next.path, bytes: next.lengthSync())),
      (o) => jsonBody(200, {'data': {}}),
    );
    final picker = find.text('Pilih file (PDF/TXT)');
    await tester.scrollUntilVisible(picker, 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(picker);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
    expect(find.textContaining('Simpan dokumen Word sebagai PDF'), findsOneWidget);

    next = txt;
    await tester.tap(picker);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.byKey(const ValueKey('alert-info')), findsOneWidget);
    expect(find.widgetWithText(InputChip, 'susunan-acara.txt'), findsOneWidget);
  });

  testWidgets('pengurus menyetujui: konfirmasi dampak ke semua warga lalu dialog hasil', (tester) async {
    final bodies = <Map<String, dynamic>>[];
    var status = 'diajukan';
    await _pump(tester, const ActivityProposalDetailPage(id: 'u-1', admin: true), (o) {
      if (o.uri.path == '/api/v1/activity-proposals/u-1') {
        return jsonBody(200, {
          'data': usulanJson(
            status: status,
            bentrok: status == 'diajukan'
                ? [
                    {'id': 'ag-9', 'nama': 'Kerja bakti', 'lokasi': 'Lapangan RT 05', 'mulai_at': '2026-10-23T09:30:00Z'},
                  ]
                : [],
          ),
          'meta': {},
        });
      }
      if (o.uri.path == '/api/v1/activity-proposals/u-1/history') return jsonBody(200, {'data': [], 'meta': {}});
      if (o.uri.path == '/api/v1/admin/activity-proposals/u-1/status') {
        bodies.add(Map<String, dynamic>.from(o.data is String ? jsonDecode(o.data as String) as Map : o.data as Map));
        status = 'disetujui';
        return jsonBody(200, {'data': usulanJson(status: 'disetujui', versi: 2), 'meta': {}});
      }
      return jsonBody(200, {'data': []});
    });
    expect(find.text('Jadwal bentrok dengan agenda lain'), findsOneWidget);
    expect(find.textContaining('Sri Uji (rumah D5-23)'), findsOneWidget);

    final approve = find.text('Setujui dan masukkan ke agenda');
    await tester.scrollUntilVisible(approve, 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(approve);
    await _settle(tester);
    expect(find.text('Setujui usulan ini?'), findsOneWidget);
    expect(find.textContaining('SEMUA warga aktif menerima notifikasi'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Setujui'));
    await _settle(tester);

    expect(bodies.single, {'status': 'disetujui', 'expected_version': 1});
    expect(find.text('Usulan disetujui'), findsOneWidget);
    await tester.tap(find.text('Mengerti'));
    await _settle(tester);
    expect(find.text('Setujui dan masukkan ke agenda'), findsNothing, reason: 'usulan yang sudah menjadi agenda tidak dapat diputuskan lagi');
  });

  testWidgets('pengurus menolak tanpa alasan: alert peringatan dan tidak ada request; konflik versi: alert kuning', (tester) async {
    var posts = 0;
    await _pump(tester, const ActivityProposalDetailPage(id: 'u-1', admin: true), (o) {
      if (o.uri.path == '/api/v1/activity-proposals/u-1') return jsonBody(200, {'data': usulanJson(), 'meta': {}});
      if (o.uri.path == '/api/v1/activity-proposals/u-1/history') return jsonBody(200, {'data': [], 'meta': {}});
      if (o.uri.path == '/api/v1/admin/activity-proposals/u-1/status') {
        posts++;
        return errorBody(409, 'VERSION_CONFLICT', 'Data sudah berubah. Muat ulang lalu coba lagi.');
      }
      return jsonBody(200, {'data': []});
    });
    final reject = find.text('Tolak usulan');
    await tester.scrollUntilVisible(reject, 200, scrollable: find.byType(Scrollable).first);
    await tester.tap(reject);
    await _settle(tester);
    await tester.tap(find.text('Simpan'));
    await _settle(tester);
    expect(posts, 0);
    expect(find.byKey(const ValueKey('alert-warning')), findsOneWidget);
    expect(find.text('Belum disimpan'), findsOneWidget);

    await tester.tap(find.text('Tandai sedang ditinjau'));
    await _settle(tester);
    expect(posts, 1);
    expect(find.byKey(const ValueKey('alert-warning')), findsOneWidget);
    expect(find.text('Data sudah berubah'), findsOneWidget);
  });

  testWidgets('form dan alert tetap terbaca tanpa meluber pada skala teks 2,0 lebar 320', (tester) async {
    tester.platformDispatcher.textScaleFactorTestValue = 2.0;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await _pump(tester, ActivityProposalFormPage(now: () => _now), (o) => jsonBody(200, {'data': {}}));
    tester.view.physicalSize = const Size(320 * 3, 900 * 3);
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Kirim usulan'), 300, scrollable: find.byType(Scrollable).first);
    await tester.tap(find.text('Kirim usulan'));
    await _settle(tester);
    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
    expect(tester.takeException(), isNull, reason: 'tidak ada overflow');
  });

  testWidgets('alert standar memakai ikon dan judul per jenis serta dapat ditutup', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildTheme(),
        home: Scaffold(
          body: Builder(
            builder: (c) => Column(
              children: [
                TextButton(onPressed: () => showMessage(c, 'Tersimpan.'), child: const Text('ok')),
                TextButton(onPressed: () => showMessage(c, 'Gagal menyimpan.', error: true), child: const Text('gagal')),
              ],
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('ok'));
    await tester.pump();
    expect(find.byKey(const ValueKey('alert-success')), findsOneWidget);
    expect(find.byIcon(Icons.check_circle_rounded), findsOneWidget);
    expect(find.text('Berhasil'), findsOneWidget);
    await tester.tap(find.text('gagal'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
    expect(find.byIcon(Icons.error_rounded), findsOneWidget);
    await tester.tap(find.byTooltip('Tutup'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('alert-error')), findsNothing);
  });
}
