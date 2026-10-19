import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// UAT 19 Okt 2026: tab Keuangan → Kas pengurus dilaporkan layar putih. Respons di bawah salinan
/// bentuk respons staging (data sintetis, tanpa nama/email).
final _summary = {
  'saldo': 705000,
  'bulan_ini': {'pemasukan': 825000, 'pengeluaran': 620000},
  'mutasi_terakhir': [
    {
      'dapat_dibalik': true,
      'id': 'k-1',
      'jenis': 'pengeluaran',
      'sumber': 'lainnya',
      'kategori': 'Berbagi',
      'nominal': -200000,
      'tanggal': '2026-10-19',
      'keterangan': 'Berbagi nasi berkah',
      'created_at': '2026-10-19T13:31:58Z',
    },
    {
      'dapat_dibalik': false,
      'id': 'k-2',
      'jenis': 'pemasukan',
      'sumber': 'iuran',
      'kategori': 'Iuran warga',
      'nominal': 225000,
      'tanggal': '2026-10-19',
      'keterangan': 'Iuran A-2 2026-08, 2026-09, 2026-10 (IUR-2026-000005)',
      'pembayaran_id': 'p-1',
      'created_at': '2026-10-19T13:22:50Z',
    },
  ],
};

Future<void> _pumpFinance(WidgetTester tester) async {
  final handler = apiHandler(
    me: () => akunJson(peran: 'pengurus'),
    fixtures: {
      '/api/v1/admin/cash/summary': {'data': _summary},
      '/api/v1/admin/dues-payments': {'data': [], 'meta': {}},
    },
  );
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(
        theme: buildTheme(),
        home: const Scaffold(body: FinanceAdminPage()),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  for (final (w, scale) in [(320.0, 1.0), (360.0, 1.3), (360.0, 2.0)]) {
    testWidgets('Kas dan Catat kas tanpa exception pada lebar $w skala $scale', (tester) async {
      useCompactScreen(tester, width: w, height: 760, textScale: scale);
      await _pumpFinance(tester);
      await tester.tap(find.text('Kas'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Saldo kas RT'), findsOneWidget);
      await tester.tap(find.text('Catat kas'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.text('Nominal (Rp)'), findsOneWidget);
    });
  }

  testWidgets('tab Kas pengurus menampilkan saldo dan mutasi dari respons staging', (tester) async {
    useCompactScreen(tester, height: 900);
    final handler = apiHandler(
      me: () => akunJson(peran: 'pengurus'),
      fixtures: {
        '/api/v1/admin/cash/summary': {'data': _summary},
        '/api/v1/admin/dues-payments': {'data': [], 'meta': {}},
        '/api/v1/admin/dues-recap': {
          'data': {
            'periode': '2026-10',
            'tarif': 75000,
            'jumlah_rumah': 0,
            'lunas': {'rumah': 0, 'nominal': 0},
            'belum_bayar': {'rumah': 0, 'nominal': 0},
            'tunggakan_sebelumnya': {'rumah': 0, 'nominal': 0},
            'rumah': [],
          },
        },
      },
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
          httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
        ],
        child: MaterialApp(
          theme: buildTheme(),
          home: const Scaffold(body: FinanceAdminPage()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Kas'));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(find.text('Saldo kas RT'), findsOneWidget);
    expect(find.text('Berbagi nasi berkah'), findsOneWidget);
    expect(find.text('Catat kas'), findsOneWidget);
  });
}
