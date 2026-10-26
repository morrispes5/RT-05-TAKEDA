import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/format.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/resident_cash.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

void main() {
  for (final scale in [1.0, 2.0]) {
    testWidgets('resident cash summary at 320 / scale $scale uses aggregate endpoint and month navigation', (tester) async {
      useCompactScreen(tester, width: 320, height: 760, textScale: scale);
      final requests = <RequestOptions>[];
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
            tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
            httpAdapterProvider.overrideWithValue(
              FakeAdapter((o) {
                requests.add(o);
                return jsonBody(200, {
                  'data': {
                    'periode': o.queryParameters['periode'],
                    'saldo_awal': 500000,
                    'modal_awal': 0,
                    'pemasukan': 700000,
                    'pengeluaran': 50000,
                    'koreksi': -25000,
                    'saldo_akhir': 1125000,
                  },
                });
              }),
            ),
          ],
          child: MaterialApp(theme: buildTheme(), home: const ResidentCashPage()),
        ),
      );
      await tester.pumpAndSettle();
      expect(requests.single.uri.path, '/api/v1/cash/summary');
      expect(requests.single.path, isNot(contains('/admin/')));
      final firstPeriod = '${requests.single.queryParameters['periode']}';
      await tester.tap(find.byTooltip('Bulan sebelumnya'));
      await tester.pumpAndSettle();
      expect(requests.last.queryParameters['periode'], isNot(firstPeriod));
      await tester.scrollUntilVisible(find.text('Saldo akhir bulan'), 200, scrollable: find.byType(Scrollable).first);
      expect(tester.takeException(), isNull);
      expect(find.text(rupiah(1125000)), findsOneWidget);
      expect(find.text('Saldo akhir bulan'), findsOneWidget);
    });
  }
}
