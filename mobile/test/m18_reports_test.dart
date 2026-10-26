import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/finance_actions.dart';
import 'package:rt05_takeda/features/pengurus/finance_reports.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

void main() {
  testWidgets('year report exports selected year XLSX bytes and fits large text', (tester) async {
    useCompactScreen(tester, width: 320, height: 760, textScale: 2);
    final saved = <String, Uint8List>{};
    final exports = <RequestOptions>[];
    final year = DateTime.now().year;
    final cash = {'saldo_awal': 500000, 'modal_awal': 0, 'pemasukan': 700000, 'pengeluaran': 50000, 'koreksi': -25000, 'saldo_akhir': 1125000};
    final settlement = {'nominal_efektif': 750000, 'terbayar': 700000, 'keringanan': 50000, 'sisa': 0};
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
          saveBytesProvider.overrideWithValue((name, bytes) async {
            saved[name] = bytes;
            return true;
          }),
          httpAdapterProvider.overrideWithValue(
            FakeAdapter((o) {
              if (o.path.endsWith('.xlsx')) {
                exports.add(o);
                return ResponseBody.fromBytes(
                  [80, 75, 3, 4],
                  200,
                  headers: {
                    Headers.contentTypeHeader: ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                  },
                );
              }
              return jsonBody(200, {
                'data': {
                  'tahun': year,
                  'posisi_pelunasan': '2026-10-26T00:00:00Z',
                  'catatan': 'Arus kas tanggal posting, pelunasan posisi saat ini.',
                  'total': cash,
                  'tagihan': settlement,
                  'bulan': [
                    {
                      'periode': '$year-01',
                      'kas': cash,
                      'iuran_diterima': 650000,
                      'sumbangan_diterima': 25000,
                      'titipan_diterima': 25000,
                      'titipan_dipakai': 10000,
                      'tagihan': settlement,
                      'mutasi_kas': [],
                      'rumah': [],
                    },
                  ],
                },
              });
            }),
          ),
        ],
        child: MaterialApp(theme: buildTheme(), home: const FinanceReportsPage()),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Unduh Excel'));
    await tester.pumpAndSettle();
    expect(exports.single.queryParameters['tahun'], year);
    expect(saved['rt05-keuangan-$year.xlsx'], [80, 75, 3, 4]);
    if (find.byTooltip('Tutup').evaluate().isNotEmpty) {
      await tester.tap(find.byTooltip('Tutup').last);
      await tester.pumpAndSettle();
    }
    await tester.scrollUntilVisible(find.text('Januari $year'), 200, scrollable: find.byType(Scrollable).first);
    await Scrollable.ensureVisible(tester.element(find.text('Januari $year')), alignment: .5);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Januari $year'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}
