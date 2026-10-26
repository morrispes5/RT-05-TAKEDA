import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/api.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/cash_correction.dart';
import 'package:rt05_takeda/features/pengurus/house_finance.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

Json body(Object? value) => Json.from(value is String ? jsonDecode(value) as Map : value as Map);

Future<ProviderContainer> container(FakeHandler handler) async {
  final c = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
      httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
    ],
  );
  addTearDown(c.dispose);
  await c.read(sessionProvider.notifier).signIn('synthetic', Akun({...akunJson(peran: 'pengurus'), 'versi_keuangan': 2}));
  return c;
}

Future<void> mount(WidgetTester tester, Widget page, FakeHandler handler) async {
  final c = await container(handler);
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: c,
      child: MaterialApp(theme: buildTheme(), home: page),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> tap(WidgetTester tester, String text) async {
  if (find.text(text).evaluate().isEmpty) {
    await tester.scrollUntilVisible(find.text(text), 250, scrollable: find.byType(Scrollable).first);
  }
  await Scrollable.ensureVisible(tester.element(find.text(text).last), alignment: .5);
  await tester.pump(const Duration(milliseconds: 250));
  await tester.tap(find.text(text).last);
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

void main() {
  test('finance version is attached only to finance requests from v2 session', () async {
    final requests = <RequestOptions>[];
    final c = await container((o) {
      requests.add(o);
      return jsonBody(200, {'data': {}});
    });
    final api = c.read(apiProvider);
    await api.post('/admin/dues/generate', data: {}, idempotencyKey: 'synthetic');
    await api.post('/admin/cash-transactions/x/reverse', data: {'alasan': 'Salah catat'});
    await api.post('/admin/agenda', data: {'judul': 'Uji sintetis'});
    expect(body(requests[0].data)['versi_keuangan'], 2);
    expect(body(requests[1].data)['versi_keuangan'], 2);
    expect(body(requests[2].data).containsKey('versi_keuangan'), isFalse);
  });

  testWidgets('house rates deposits and relief history fit compact large text', (tester) async {
    useCompactScreen(tester, width: 320, height: 760, textScale: 2);
    await mount(tester, const HouseFinancePage(house: {'id': 'h1', 'kode_rumah': 'Z-01'}), (o) {
      if (o.path.endsWith('/dues')) {
        return jsonBody(200, {
          'data': {
            'tagihan': [
              {'id': 't1', 'periode': '2026-10', 'nominal_efektif': 75000, 'terbayar': 50000, 'keringanan': 0, 'sisa': 25000, 'status_label': 'Kurang bayar'},
            ],
            'riwayat_keringanan': [
              {'id': 'r1', 'periode': '2026-09-01', 'nominal': 25000, 'alasan': 'Keringanan uji beralasan lengkap', 'dibatalkan_at': '2026-10-01'},
            ],
            'riwayat_penyesuaian': [],
          },
        });
      }
      if (o.path.endsWith('/deposits')) {
        return jsonBody(200, {
          'data': {
            'saldo': 25000,
            'lot': [
              {'saldo': '25000', 'pembayaran_id': 'p1'},
            ],
            'penggunaan': [
              {
                'id': 'u1',
                'nominal': 10000,
                'tanggal': '2026-10-26',
                'periode': ['2026-10'],
                'dibatalkan_at': null,
              },
            ],
          },
        });
      }
      return jsonBody(200, {
        'data': [
          {'id': 'r1', 'mode': 'khusus', 'nominal': 50000, 'mulai_periode': '2026-11-01', 'alasan': 'Tarif keputusan rapat uji', 'dibatalkan_at': null},
        ],
      });
    });
    for (var i = 0; i < 12; i++) {
      await tester.drag(find.byType(ListView).first, const Offset(0, -200));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    }
    await tester.scrollUntilVisible(find.text('Riwayat tarif rumah'), 200, scrollable: find.byType(Scrollable).first, maxScrolls: 40);
    expect(find.text('Riwayat tarif rumah'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('cash correction asks confirmation for negative balance and keeps key', (tester) async {
    final writes = <RequestOptions>[];
    await mount(
      tester,
      const CashCorrectionPage(
        original: {'id': 'k1', 'jenis': 'pengeluaran', 'nominal': -10000, 'kategori': 'Uji', 'keterangan': 'Pengeluaran sintetis', 'tanggal': '2026-10-26'},
      ),
      (o) {
        writes.add(o);
        return writes.length == 1
            ? errorBody(422, 'NEGATIVE_BALANCE_CONFIRMATION_REQUIRED', 'Saldo akan negatif')
            : jsonBody(201, {
                'data': {'id': 'k2'},
              });
      },
    );
    await tester.enterText(find.byKey(const ValueKey('cash-correction-reason')), 'Koreksi nominal pengeluaran');
    await tap(tester, 'Simpan koreksi');
    await tap(tester, 'Simpan');
    expect(writes, hasLength(1));
    await tap(tester, 'Tetap simpan');
    expect(writes, hasLength(2));
    expect(body(writes[1].data)['konfirmasi_saldo_negatif'], true);
    expect(body(writes[1].data)['alasan'], 'Koreksi nominal pengeluaran');
    expect(writes[0].headers['Idempotency-Key'], writes[1].headers['Idempotency-Key']);
    expect(find.text('Koreksi kas tercatat'), findsOneWidget);
  });
}
