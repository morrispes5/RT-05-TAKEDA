import 'dart:convert';

import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/finance_actions.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// M12 pengurus: pembatalan pembayaran yang terlihat, pembalikan kas manual, konfirmasi saldo negatif.
const _payId = '0199f0a1-2222-7000-8000-000000000001';

final _payment = {
  'id': _payId,
  'nomor_bukti': 'IUR-2026-000007',
  'rumah': {'kode_rumah': 'A-01'},
  'periode': ['2026-10', '2026-11'],
  'total_bayar': 150000,
  'tanggal_bayar': '2026-10-17',
  'status': 'tercatat',
};

Map<String, dynamic> _body(Object? data) => Map<String, dynamic>.from(data is String ? jsonDecode(data) as Map : data as Map);

Future<FakeAdapter> _pump(WidgetTester tester, Widget home, FakeHandler handler) async {
  final adapter = FakeAdapter(handler);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(adapter),
      ],
      child: MaterialApp(theme: buildTheme(), home: home),
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

Future<void> _reason(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField).last, text);
  await tester.tap(find.text('Simpan').last);
  await _settle(tester);
}

void main() {
  test('alasan pembalikan minimal 5 karakter dan label jenis kas', () {
    expect(reasonProblem(null), isNotNull);
    expect(reasonProblem(' abc '), isNotNull);
    expect(reasonProblem('Salah rumah'), isNull);
    expect(cashJenisLabel('pemasukan'), 'Pemasukan lain');
    expect(cashJenisLabel('saldo_awal'), 'Saldo awal');
  });

  testWidgets('pembayaran: tombol Batalkan terlihat, alasan wajib, konfirmasi bahaya, satu kunci, dialog hasil', (tester) async {
    useCompactScreen(tester, height: 1400);
    final reverses = <RequestOptions>[];
    await _pump(tester, const Scaffold(body: FinanceAdminPage()), (o) {
      if (o.method == 'POST') {
        reverses.add(o);
        return jsonBody(200, {
          'data': {..._payment, 'status': 'dibatalkan'},
        });
      }
      return jsonBody(200, {
        'data': [_payment],
        'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
      });
    });

    expect(find.byKey(const ValueKey('batalkan-$_payId')), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('batalkan-$_payId')));
    await _settle(tester);
    await _reason(tester, 'abc');
    expect(find.byKey(const ValueKey('alert-warning')), findsOneWidget);
    expect(reverses, isEmpty);

    await tester.tap(find.byKey(const ValueKey('batalkan-$_payId')));
    await _settle(tester);
    await _reason(tester, 'Salah rumah');
    expect(find.text('Batalkan pembayaran IUR-2026-000007?'), findsOneWidget);
    expect(find.textContaining('Tagihan Oktober 2026, November 2026 kembali belum dibayar'), findsOneWidget);
    await tester.tap(find.text('Balik'));
    await _settle(tester);

    expect(reverses.single.path, endsWith('/admin/dues-payments/$_payId/reverse'));
    expect(_body(reverses.single.data), {'alasan': 'Salah rumah'});
    expect(reverses.single.headers['Idempotency-Key'], matches(RegExp(r'^[0-9a-f-]{36}$')));
    expect(find.text('Pembayaran dibatalkan'), findsOneWidget);
  });

  testWidgets('pembayaran: konflik server tampil sebagai peringatan kuning', (tester) async {
    useCompactScreen(tester, height: 1400);
    await _pump(tester, const Scaffold(body: FinanceAdminPage()), (o) {
      if (o.method == 'POST') return errorBody(409, 'PAYMENT_ALREADY_REVERSED', 'Pembayaran ini sudah dibatalkan.');
      return jsonBody(200, {
        'data': [_payment],
        'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
      });
    });
    await tester.tap(find.byKey(const ValueKey('batalkan-$_payId')));
    await _settle(tester);
    await _reason(tester, 'Salah rumah');
    await tester.tap(find.text('Balik'));
    await _settle(tester);
    expect(find.byKey(const ValueKey('alert-warning')), findsOneWidget);
    expect(find.textContaining('Pembayaran ini sudah dibatalkan.'), findsOneWidget);
  });

  testWidgets('kas: tombol Balik hanya untuk kas manual yang belum dibalik', (tester) async {
    useCompactScreen(tester, height: 1400);
    final reversed = <Map>[];
    await _pump(
      tester,
      Scaffold(body: CashView(onReverse: (k) async => reversed.add(k))),
      (o) => jsonBody(200, {
        'data': {
          'saldo': 90000,
          'bulan_ini': {'pemasukan': 100000, 'pengeluaran': 10000},
          'mutasi_terakhir': [
            {
              'id': 'k-1',
              'jenis': 'pemasukan',
              'sumber': 'iuran',
              'nominal': 75000,
              'tanggal': '2026-10-17',
              'keterangan': 'Iuran A-01',
              'dapat_dibalik': false,
            },
            {
              'id': 'k-2',
              'jenis': 'pengeluaran',
              'sumber': 'lainnya',
              'kategori': 'Kebersihan',
              'nominal': -10000,
              'tanggal': '2026-10-17',
              'keterangan': 'Beli sapu',
              'dapat_dibalik': true,
            },
          ],
        },
      }),
    );
    expect(find.byKey(const ValueKey('balik-k-1')), findsNothing);
    await tester.tap(find.byKey(const ValueKey('balik-k-2')));
    await _settle(tester);
    expect(reversed.single['id'], 'k-2');
  });

  testWidgets('catat kas: saldo akan negatif → konfirmasi → kirim ulang dengan kunci sama dan flag', (tester) async {
    useCompactScreen(tester, height: 1400);
    final posts = <RequestOptions>[];
    await _pump(tester, const CashEntryPage(), (o) {
      posts.add(o);
      if (posts.length == 1) {
        return errorBody(422, 'NEGATIVE_BALANCE_CONFIRMATION_REQUIRED', 'Pencatatan ini membuat saldo kas menjadi -Rp30.000.');
      }
      return jsonBody(201, {
        'data': {'id': 'k-9', 'jenis': 'pengeluaran', 'nominal': -80000, 'tanggal': '2026-10-17'},
        'meta': {'peringatan': 'Saldo kas menjadi negatif setelah pencatatan ini.'},
      });
    });
    final fields = find.byType(TextField);
    await tester.enterText(fields.at(0), '80000');
    await tester.enterText(fields.at(1), 'Perbaikan');
    await tester.enterText(fields.at(2), 'Perbaikan pos ronda darurat');
    await tester.tap(find.text('Simpan'));
    await _settle(tester);
    await tester.tap(find.text('Ya, lanjutkan'));
    await _settle(tester);

    expect(find.text('Saldo kas akan negatif'), findsOneWidget);
    expect(find.textContaining('-Rp30.000'), findsOneWidget);
    await tester.tap(find.text('Tetap catat'));
    await _settle(tester);

    expect(posts, hasLength(2));
    expect(_body(posts.first.data).containsKey('konfirmasi_saldo_negatif'), isFalse);
    expect(_body(posts.last.data)['konfirmasi_saldo_negatif'], isTrue);
    expect(posts.last.headers['Idempotency-Key'], posts.first.headers['Idempotency-Key']);
    expect(find.text('Transaksi kas tercatat'), findsOneWidget);
  });

  testWidgets('catat kas: batal saat peringatan saldo negatif tidak mengirim ulang', (tester) async {
    useCompactScreen(tester, height: 1400);
    var posts = 0;
    await _pump(tester, const CashEntryPage(), (o) {
      posts++;
      return errorBody(422, 'NEGATIVE_BALANCE_CONFIRMATION_REQUIRED', 'Pencatatan ini membuat saldo kas menjadi -Rp30.000.');
    });
    final fields = find.byType(TextField);
    await tester.enterText(fields.at(0), '80000');
    await tester.enterText(fields.at(1), 'Perbaikan');
    await tester.enterText(fields.at(2), 'Perbaikan pos ronda');
    await tester.tap(find.text('Simpan'));
    await _settle(tester);
    await tester.tap(find.text('Ya, lanjutkan'));
    await _settle(tester);
    await tester.tap(find.text('Batal'));
    await _settle(tester);
    expect(posts, 1);
    expect(find.text('Catat kas'), findsOneWidget, reason: 'tetap di form, isian tidak hilang');
  });

  for (final width in [360.0, 320.0]) {
    testWidgets('daftar pembayaran skala teks 2.0 lebar $width', (tester) async {
      useCompactScreen(tester, width: width, height: 3000, textScale: 2.0);
      await _pump(
        tester,
        const Scaffold(body: FinanceAdminPage()),
        (o) => jsonBody(200, {
          'data': [_payment],
          'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
        }),
      );
      expect(tester.takeException(), isNull);
      expect(find.byKey(const ValueKey('batalkan-$_payId')), findsOneWidget);
    });
  }
}
