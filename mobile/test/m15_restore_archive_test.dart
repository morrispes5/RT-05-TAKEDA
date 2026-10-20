import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/household/admin_census.dart';
import 'package:rt05_takeda/features/household/admin_links.dart';
import 'package:rt05_takeda/features/household/restore_page.dart';

import 'support/fakes.dart';

/// UAT M15 (20 Okt): arsip tidak boleh satu arah. Rumah, keluarga, dan akun arsip punya tombol Pulihkan
/// dengan alasan wajib; yang aktif tetap menawarkan Arsipkan.
Map<String, dynamic> _house({bool archived = false}) => {
  'id': 'h-1',
  'kode_rumah': 'A-01',
  'alamat': 'Blok A No. 1',
  'tagihan_aktif': false,
  'mulai_tagih': null,
  'versi': 3,
  'archived_at': archived ? '2026-10-20T03:00:00Z' : null,
  'keluarga': null,
  'riwayat_penghunian': [],
  'pemilik': null,
};

Map<String, dynamic> _family({bool archived = false}) => {
  'id': 'k-1',
  'versi': 4,
  'status': 'terverifikasi',
  'archived_at': archived ? '2026-10-20T03:00:00Z' : null,
  'anggota': [
    {'id': 'w-1', 'nama': 'Kepala Uji', 'is_kepala': true, 'hubungan_keluarga': 'Kepala', 'status': 'aktif'},
  ],
  'penghunian': [],
  'rumah': null,
  'akun': [],
};

Map<String, dynamic> _akun(String status) => {
  'id': 'a-9',
  'nama': 'Warga Arsip',
  'email': 'arsip@example.test',
  'status': status,
  'peran': 'warga',
  'jenis': 'utama',
};

Object? _body(RequestOptions options) {
  final data = options.data;
  if (data is Map) return Map<String, dynamic>.from(data);
  if (data is String && data.isNotEmpty) return jsonDecode(data);
  return data;
}

Future<void> _pump(WidgetTester tester, Widget home, FakeHandler handler) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(theme: buildTheme(), home: home),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _open(WidgetTester tester, Widget page, FakeHandler handler) async {
  await _pump(
    tester,
    Scaffold(
      body: Builder(
        builder: (context) => TextButton(
          onPressed: () => Navigator.push<void>(context, MaterialPageRoute(builder: (_) => page)),
          child: const Text('Buka'),
        ),
      ),
    ),
    handler,
  );
  await tester.tap(find.text('Buka'));
  await tester.pumpAndSettle();
}

/// BusyButton menampilkan indikator yang terus beranimasi selama dialog konfirmasi terbuka,
/// sehingga pumpAndSettle tidak pernah selesai: pump bertahap saja.
Future<void> _step(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

void _tall(WidgetTester tester) {
  tester.view.physicalSize = const Size(1200, 3600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
}

void main() {
  testWidgets('pulihkan wajib beralasan, meminta konfirmasi, lalu mengirim alasan dan menutup layar', (tester) async {
    _tall(tester);
    final posts = <String>[];
    Object? body;
    await _open(
      tester,
      const RestoreArchivedPage(
        title: 'Pulihkan rumah',
        path: '/admin/houses/h-1/restore',
        name: 'A-01',
        note: 'Rumah kembali aktif.',
        doneTitle: 'Rumah dipulihkan',
        doneMessage: 'Catat hunian lagi.',
      ),
      (options) {
        if (options.method == 'POST') {
          posts.add(options.uri.path);
          body = _body(options);
          return jsonBody(204, null);
        }
        return jsonBody(200, {'data': {}});
      },
    );

    // Alasan kosong: ditolak di layar, tidak ada permintaan.
    await tester.tap(find.widgetWithText(FilledButton, 'Pulihkan rumah'));
    await _step(tester);
    expect(posts, isEmpty);
    expect(find.text('Pulihkan rumah A-01?'), findsNothing);

    await tester.enterText(find.widgetWithText(TextField, 'Alasan'), 'Salah arsip');
    await tester.tap(find.widgetWithText(FilledButton, 'Pulihkan rumah'));
    await _step(tester);
    expect(find.text('Pulihkan rumah A-01?'), findsOneWidget);
    await tester.tap(find.text('Pulihkan'));
    await _step(tester);

    expect(posts, ['/api/v1/admin/houses/h-1/restore']);
    expect(body, {'alasan': 'Salah arsip'});
    expect(find.text('Rumah dipulihkan'), findsOneWidget);
    await tester.tap(find.text('Mengerti'));
    await _step(tester);
    expect(find.byType(RestoreArchivedPage), findsNothing, reason: 'layar tertutup setelah berhasil');
  });

  testWidgets('kegagalan server tampil di bawah kolom alasan dan layar tetap terbuka', (tester) async {
    _tall(tester);
    await _open(
      tester,
      const RestoreArchivedPage(
        title: 'Pulihkan akun',
        path: '/admin/accounts/a-9/restore',
        name: 'Warga Arsip',
        note: 'Akun kembali Nonaktif.',
        doneTitle: 'Akun dipulihkan',
        doneMessage: 'Aktifkan lagi.',
      ),
      (options) {
        if (options.method == 'POST') {
          return jsonBody(409, {
            'error': {'code': 'NOT_ARCHIVED', 'message': 'Akun tidak sedang diarsipkan.'},
          });
        }
        return jsonBody(200, {'data': {}});
      },
    );
    await tester.enterText(find.widgetWithText(TextField, 'Alasan'), 'Salah arsip');
    await tester.tap(find.widgetWithText(FilledButton, 'Pulihkan akun'));
    await _step(tester);
    await tester.tap(find.text('Pulihkan'));
    await _step(tester);
    expect(find.text('Akun tidak sedang diarsipkan.'), findsOneWidget);
    expect(find.byType(RestoreArchivedPage), findsOneWidget);
  });

  for (final archived in [true, false]) {
    testWidgets('detail rumah ${archived ? 'arsip menawarkan Pulihkan' : 'aktif menawarkan Arsipkan'}', (tester) async {
      _tall(tester);
      await _pump(tester, HouseAdminPage(summary: const {'id': 'h-1', 'kode_rumah': 'A-01'}, onRecordPayment: (_) async {}), (options) {
        if (options.uri.path == '/api/v1/admin/houses/h-1') return jsonBody(200, {'data': _house(archived: archived)});
        if (options.uri.path == '/api/v1/admin/houses/h-1/dues') {
          return jsonBody(200, {
            'data': {'tagihan': []},
          });
        }
        return jsonBody(200, {'data': {}});
      });
      expect(find.byKey(const ValueKey('pulihkan-rumah')), archived ? findsOneWidget : findsNothing);
      expect(find.text('Arsipkan rumah'), archived ? findsNothing : findsOneWidget);
    });

    testWidgets('detail keluarga ${archived ? 'arsip menawarkan Pulihkan' : 'aktif menawarkan Arsipkan'}', (tester) async {
      _tall(tester);
      await _pump(tester, const FamilyAdminPage(id: 'k-1'), (options) {
        if (options.uri.path == '/api/v1/admin/families/k-1') return jsonBody(200, {'data': _family(archived: archived)});
        return jsonBody(200, {'data': {}});
      });
      expect(find.byKey(const ValueKey('pulihkan-keluarga')), archived ? findsOneWidget : findsNothing);
      expect(find.text('Arsipkan keluarga'), archived ? findsNothing : findsOneWidget);
    });
  }

  testWidgets('daftar akun: hanya akun arsip yang punya Pulihkan, akun aktif tetap Arsipkan', (tester) async {
    _tall(tester);
    await _pump(tester, const Scaffold(body: AccountsAdminView()), (options) {
      if (options.uri.path == '/api/v1/admin/accounts') {
        return jsonBody(200, {
          'data': [
            _akun('diarsipkan'),
            {..._akun('aktif'), 'id': 'a-8', 'nama': 'Warga Aktif', 'email': 'aktif@example.test'},
          ],
          'meta': {'current_page': 1, 'per_page': 20, 'total': 2},
        });
      }
      return jsonBody(200, {'data': {}});
    });
    expect(find.byKey(const ValueKey('pulihkan-akun')), findsOneWidget);
    expect(find.text('Arsipkan akun'), findsOneWidget);
    expect(find.text('Akun ini sudah diarsipkan.'), findsOneWidget);
  });
}
