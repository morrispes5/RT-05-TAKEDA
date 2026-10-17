import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/dues_admin.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// M11 pengurus: rekap bulanan dan tarif. Data sintetis mengikuti DuesRecapEnvelope/DuesRateEnvelope.
Map<String, dynamic> _recap(String p) => {
  'periode': p,
  'tarif': 75000,
  'jumlah_rumah': 3,
  'lunas': {'rumah': 1, 'nominal': 75000},
  'belum_bayar': {'rumah': 2, 'nominal': 150000},
  'tunggakan_sebelumnya': {'rumah': 1, 'nominal': 75000},
  'rumah': [
    {
      'rumah_id': 'r-1',
      'kode_rumah': 'A-01',
      'nominal': 75000,
      'status': 'lunas',
      'status_label': 'Lunas',
      'tanggal_bayar': '2026-11-03',
      'bulan_tunggakan_sebelumnya': 0,
    },
    {
      'rumah_id': 'r-3',
      'kode_rumah': 'A-03',
      'nominal': 75000,
      'status': 'belum_bayar',
      'status_label': 'Belum dibayar',
      'tanggal_bayar': null,
      'bulan_tunggakan_sebelumnya': 1,
    },
  ],
};

final _rates = [
  {'id': 't-2', 'nominal': 80000, 'mulai_periode': '2027-01', 'alasan': 'Kenaikan kas', 'created_at': '2026-10-17T03:00:00Z'},
  {'id': 't-1', 'nominal': 75000, 'mulai_periode': '2026-10', 'alasan': 'Tarif awal', 'created_at': '2026-10-11T03:00:00Z'},
];

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

// 1 November 2026 01.00 WIB = 31 Oktober 18.00 UTC: bulan berjalan menurut Jakarta.
DateTime _now() => DateTime.utc(2026, 10, 31, 18);

void main() {
  test('navigasi bulan, bulan berjalan WIB, dan pilihan bulan tarif', () {
    expect(shiftMonth('2026-12', 1), '2027-01');
    expect(shiftMonth('2026-01', -1), '2025-12');
    expect(currentPeriod(_now()), '2026-11');
    final periods = ratePeriods('2026-11');
    expect([periods.first, periods.last, periods.length], ['2026-12', '2028-11', 24]);
  });

  test('validasi tarif: nominal, bulan, alasan, bulan yang sudah punya tarif', () {
    expect(validateRate(nominal: '500', mulai: null, alasan: 'x', taken: {}).keys, containsAll(['nominal', 'mulai_periode', 'alasan']));
    expect(validateRate(nominal: '80.000', mulai: '2027-01', alasan: 'Kenaikan', taken: {'2027-01'}).keys, ['mulai_periode']);
    expect(validateRate(nominal: '80000', mulai: '2027-02', alasan: 'Kenaikan', taken: {'2027-01'}), isEmpty);
  });

  testWidgets('rekap: angka dari server, tunggakan sebelumnya, ganti bulan, buat tagihan dengan alert', (tester) async {
    useCompactScreen(tester, height: 1400);
    final adapter = await _pump(tester, Scaffold(body: DuesRecapView(now: _now)), (o) {
      if (o.method == 'POST') {
        return jsonBody(200, {
          'data': {'tagihan_baru': 2},
        });
      }
      return jsonBody(200, {'data': _recap('${o.uri.queryParameters['periode']}')});
    });

    expect(find.text('November 2026'), findsOneWidget);
    expect(adapter.requests.first.uri.queryParameters['periode'], '2026-11');
    expect(find.text('1 rumah'), findsWidgets);
    expect(find.text('2 rumah'), findsOneWidget);
    expect(find.text('Tunggakan bulan sebelumnya'), findsOneWidget);
    expect(find.textContaining('dibayar 2026-11-03'), findsOneWidget);
    expect(find.textContaining('tunggakan 1 bulan sebelumnya'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('rekap-sebelumnya')));
    await _settle(tester);
    expect(find.text('Oktober 2026'), findsOneWidget);
    expect(adapter.requests.last.uri.queryParameters['periode'], '2026-10');

    await tester.tap(find.text('Buat tagihan yang belum ada'));
    await _settle(tester);
    expect(find.text('Buat tagihan yang belum ada?'), findsOneWidget);
    await tester.tap(find.text('Buat tagihan'));
    await _settle(tester);
    expect(adapter.requests.where((r) => r.method == 'POST' && r.uri.path.endsWith('/admin/dues/generate')).length, 1);
    expect(find.text('2 tagihan baru dibuat.'), findsOneWidget);
  });

  testWidgets('tarif: riwayat, form menolak isian salah, bulan terpakai tidak bisa dipilih, simpan dengan konfirmasi', (tester) async {
    useCompactScreen(tester, height: 1400);
    final posts = <Map<String, dynamic>>[];
    await _pump(tester, DuesRatesPage(now: _now), (o) {
      if (o.method == 'POST') {
        posts.add(_body(o.data));
        return jsonBody(201, {'data': _rates.first});
      }
      return jsonBody(200, {'data': _rates});
    });

    expect(find.text('Tarif berlaku November 2026'), findsOneWidget);
    expect(find.text('Rp75.000 per rumah per bulan'), findsOneWidget);
    expect(find.textContaining('mulai Januari 2027'), findsOneWidget);

    await tester.tap(find.text('Tetapkan tarif baru'));
    await _settle(tester);
    await tester.enterText(find.byKey(const ValueKey('tarif-nominal')), '500');
    await tester.tap(find.text('Simpan tarif'));
    await _settle(tester);
    expect(find.byKey(const ValueKey('alert-warning')), findsOneWidget);
    expect(find.textContaining('Nominal Rp1.000'), findsOneWidget);
    expect(posts, isEmpty);

    await tester.tap(find.byKey(const ValueKey('tarif-mulai')));
    await _settle(tester);
    expect(find.text('Januari 2027 (sudah ada)'), findsWidgets);
    await tester.tap(find.text('Februari 2027').last);
    await _settle(tester);
    await tester.enterText(find.byKey(const ValueKey('tarif-nominal')), '80000');
    await tester.enterText(find.byKey(const ValueKey('tarif-alasan')), 'Kesepakatan rapat warga');
    await tester.tap(find.text('Simpan tarif'));
    await _settle(tester);
    expect(find.text('Tetapkan tarif baru?'), findsOneWidget);
    await tester.tap(find.text('Tetapkan'));
    await _settle(tester);
    expect(posts, [
      {'nominal': 80000, 'mulai_periode': '2027-02', 'alasan': 'Kesepakatan rapat warga'},
    ]);
    expect(find.text('Tarif ditetapkan'), findsOneWidget);
  });

  testWidgets('tarif: konflik server ditampilkan di field bulan', (tester) async {
    useCompactScreen(tester, height: 1400);
    await _pump(
      tester,
      const DuesRateFormPage(current: '2026-11', taken: {}),
      (o) => errorBody(409, 'RATE_PERIOD_EXISTS', 'Tarif untuk periode itu sudah ditetapkan. Pilih periode lain.'),
    );
    await tester.tap(find.byKey(const ValueKey('tarif-mulai')));
    await _settle(tester);
    await tester.tap(find.text('Desember 2026').last);
    await _settle(tester);
    await tester.enterText(find.byKey(const ValueKey('tarif-alasan')), 'Kesepakatan rapat');
    await tester.tap(find.text('Simpan tarif'));
    await _settle(tester);
    await tester.tap(find.text('Tetapkan'));
    await _settle(tester);
    expect(find.text('Tarif untuk periode itu sudah ditetapkan. Pilih periode lain.'), findsWidgets);
  });

  for (final width in [360.0, 320.0]) {
    testWidgets('rekap dan form tarif skala teks 2.0 lebar $width', (tester) async {
      useCompactScreen(tester, width: width, height: 3000, textScale: 2.0);
      await _pump(tester, Scaffold(body: DuesRecapView(now: _now)), (o) => jsonBody(200, {'data': _recap('2026-11')}));
      expect(tester.takeException(), isNull);
      await _pump(tester, const DuesRateFormPage(current: '2026-11', taken: {'2027-01'}), (o) => jsonBody(200, {'data': {}}));
      expect(tester.takeException(), isNull);
    });
  }
}
