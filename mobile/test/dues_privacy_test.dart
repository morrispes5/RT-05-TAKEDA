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

/// Keputusan pemilik (ADR26, diperbarui): tab Iuran warga hanya menampilkan iuran sendiri dan jalur
/// bukti pembayaran. Transparansi rumah lain dihapus dari warga, dan kas RT tidak tampil untuk warga.
Future<FakeAdapter> _pumpDues(WidgetTester tester, {Map<String, Object?>? fixtures}) async {
  final adapter = FakeAdapter(apiHandler(me: akunJson, fixtures: fixtures));
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

Finder _tombolWa() => find.ancestor(of: find.text('Chat bendahara lewat WhatsApp'), matching: find.byWidgetPredicate((w) => w is OutlinedButton));

bool _waAktif(WidgetTester tester) => tester.widget<OutlinedButton>(_tombolWa().first).onPressed != null;

Map<String, Object?> _kontak(String? nomor) => {
  ...shellFixtures(),
  '/api/v1/settings/contacts': {
    'data': {'bendahara_whatsapp': nomor},
  },
};

void main() {
  testWidgets('warga: iuran sendiri dulu, tanpa kas RT dan tanpa transparansi rumah lain', (tester) async {
    useCompactScreen(tester, height: 1600);
    final adapter = await _pumpDues(tester);

    expect(find.text('Rumah A-01'), findsOneWidget);
    expect(find.text('Riwayat tagihan'), findsOneWidget);
    expect(find.text('Kas RT'), findsNothing);
    expect(find.text('Saldo kas RT'), findsNothing);
    expect(_hits(adapter, '/api/v1/admin/cash/summary'), 0);
    expect(_hits(adapter, '/api/v1/dues/transparency'), 0, reason: 'privasi: status rumah lain tidak boleh diminta warga');
    expect(find.text('Transparansi iuran warga', skipOffstage: false), findsNothing);
    expect(find.text('Lihat transparansi iuran', skipOffstage: false), findsNothing);
    expect(find.text('Sudah bayar iuran?'), findsOneWidget);
    expect(find.text('Kirim bukti transfer'), findsOneWidget);
    expect(tester.getTopLeft(find.text('Riwayat tagihan')).dy, lessThan(tester.getTopLeft(find.text('Sudah bayar iuran?')).dy));
  });

  testWidgets('warga: tombol WhatsApp bendahara aktif bila nomor sudah diatur', (tester) async {
    useCompactScreen(tester, height: 1600);
    await _pumpDues(tester);

    expect(find.text('Chat bendahara lewat WhatsApp'), findsOneWidget);
    expect(_waAktif(tester), isTrue);
    expect(find.text('Nomor WhatsApp bendahara belum diatur pengurus.'), findsNothing);
  });

  testWidgets('warga: nomor bendahara kosong membuat tombol nonaktif dengan petunjuk', (tester) async {
    useCompactScreen(tester, height: 1600);
    await _pumpDues(tester, fixtures: _kontak(null));

    expect(_waAktif(tester), isFalse);
    expect(find.text('Nomor WhatsApp bendahara belum diatur pengurus.'), findsOneWidget);
  });

  for (final width in [360.0, 320.0]) {
    testWidgets('tab iuran warga skala teks 2.0 lebar $width tanpa overflow', (tester) async {
      useCompactScreen(tester, width: width, height: 3200, textScale: 2.0);
      await _pumpDues(tester);
      expect(tester.takeException(), isNull);
      expect(find.text('Sudah bayar iuran?'), findsOneWidget);
      expect(find.text('Chat bendahara lewat WhatsApp'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
