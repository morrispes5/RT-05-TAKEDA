import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// Keputusan pemilik 17 Oktober 2026 (ADR26): tab Iuran warga menampilkan iuran sendiri lebih dulu,
/// transparansi rumah lain tersembunyi secara bawaan, dan kas RT tidak tampil untuk warga.
Future<FakeAdapter> _pumpDues(WidgetTester tester) async {
  final adapter = FakeAdapter(apiHandler(me: akunJson));
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(adapter),
      ],
      child: MaterialApp(
        theme: buildTheme(),
        home: const Scaffold(body: DuesPage()),
      ),
    ),
  );
  await _settle(tester);
  return adapter;
}

Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

int _hits(FakeAdapter a, String path) => a.requests.where((r) => r.uri.path == path).length;

void main() {
  testWidgets('warga: iuran sendiri dulu, tanpa kas RT, transparansi tidak dimuat sebelum diminta', (tester) async {
    useCompactScreen(tester, height: 1600);
    final adapter = await _pumpDues(tester);

    expect(find.text('Rumah A-01'), findsOneWidget);
    expect(find.text('Riwayat tagihan'), findsOneWidget);
    expect(find.text('Kas RT'), findsNothing);
    expect(find.text('Saldo kas RT'), findsNothing);
    expect(_hits(adapter, '/api/v1/admin/cash/summary'), 0);
    expect(_hits(adapter, '/api/v1/dues/transparency'), 0, reason: 'privasi: data rumah lain tidak diminta sebelum warga memilih');
    expect(find.text('Lihat transparansi iuran'), findsOneWidget);
    expect(find.text('A-01', skipOffstage: false), findsNothing);
    expect(tester.getTopLeft(find.text('Riwayat tagihan')).dy, lessThan(tester.getTopLeft(find.text('Transparansi iuran warga')).dy));
  });

  testWidgets('warga: membuka transparansi butuh konfirmasi, bisa batal dan disembunyikan lagi', (tester) async {
    useCompactScreen(tester, height: 1600);
    final adapter = await _pumpDues(tester);

    await tester.tap(find.text('Lihat transparansi iuran'));
    await _settle(tester);
    expect(find.text('Lihat status iuran rumah lain?'), findsOneWidget);
    await tester.tap(find.text('Batal'));
    await _settle(tester);
    expect(_hits(adapter, '/api/v1/dues/transparency'), 0);
    expect(find.text('Lihat transparansi iuran'), findsOneWidget);

    await tester.tap(find.text('Lihat transparansi iuran'));
    await _settle(tester);
    await tester.tap(find.text('Tampilkan'));
    await _settle(tester);
    expect(_hits(adapter, '/api/v1/dues/transparency'), 1);
    expect(find.text('A-01'), findsOneWidget);
    expect(find.text('1 rumah'), findsOneWidget);
    expect(find.text('Ditampilkan per kode rumah tanpa nama atau kontak warga.'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('sembunyikan-transparansi')));
    await _settle(tester);
    expect(find.text('A-01'), findsNothing);
    expect(find.text('Lihat transparansi iuran'), findsOneWidget);
    expect(_hits(adapter, '/api/v1/admin/cash/summary'), 0);
  });

  for (final width in [360.0, 320.0]) {
    testWidgets('tab iuran warga skala teks 2.0 lebar $width tanpa overflow', (tester) async {
      useCompactScreen(tester, width: width, height: 3200, textScale: 2.0);
      await _pumpDues(tester);
      expect(tester.takeException(), isNull);
      await tester.tap(find.text('Lihat transparansi iuran'));
      await _settle(tester);
      await tester.tap(find.text('Tampilkan'));
      await _settle(tester);
      expect(tester.takeException(), isNull);
      expect(find.text('A-01'), findsOneWidget);
    });
  }
}
