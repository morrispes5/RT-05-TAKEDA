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

void main() {
  testWidgets('admin cash shows capital and signed correction separately at text scale 2', (tester) async {
    useCompactScreen(tester, width: 320, height: 760, textScale: 2);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
          httpAdapterProvider.overrideWithValue(
            FakeAdapter(
              (_) => jsonBody(200, {
                'data': {
                  'saldo': 500000,
                  'bulan_ini': {'modal_awal': 500000, 'pemasukan': 100000, 'pengeluaran': 20000, 'koreksi': -80000},
                  'mutasi_terakhir': [],
                },
              }),
            ),
          ),
        ],
        child: MaterialApp(
          theme: buildTheme(),
          home: const Scaffold(body: CashView()),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Modal awal bulan ini:'), findsOneWidget);
    expect(find.textContaining('Koreksi bulan ini:'), findsOneWidget);
    expect(find.textContaining('Masuk bulan ini'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
