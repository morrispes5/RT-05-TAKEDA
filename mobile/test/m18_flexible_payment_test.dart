import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/flexible_payment.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

const _house = {'id': 'h1', 'kode_rumah': 'Z-01', 'alamat': 'Alamat sintetis'};
Json _body(Object? data) => Json.from(data is String ? jsonDecode(data) as Map : data as Map);

Future<void> _open(WidgetTester tester, FakeHandler handler, {int amount = 50000, String? confirmation}) async {
  final container = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
      httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
    ],
  );
  addTearDown(container.dispose);
  await container.read(sessionProvider.notifier).signIn('synthetic', Akun({...akunJson(peran: 'pengurus'), 'versi_keuangan': 2}));
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(
        theme: buildTheme(),
        home: Builder(
          builder: (context) => Scaffold(
            body: FilledButton(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => FlexiblePaymentPage(house: _house, initialPeriods: const ['2026-10'], initialAmount: amount, confirmationId: confirmation),
                ),
              ),
              child: const Text('Buka'),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Buka'));
  await tester.pumpAndSettle();
}

FakeHandler _handler(List<RequestOptions> writes, {bool timeout = false, int amount = 50000}) => (o) {
  if (o.method == 'GET' && o.path.endsWith('/dues')) {
    return jsonBody(200, {
      'data': {
        'saldo_titipan': 0,
        'tagihan': [
          {'id': 't1', 'periode': '2026-10', 'nominal': 75000, 'sisa': 75000, 'status_label': 'Belum dibayar'},
        ],
      },
    });
  }
  if (o.path.endsWith('/preview')) {
    return jsonBody(200, {
      'data': {
        'fingerprint': 'a' * 64,
        'sisa': 75000,
        'sisa_setelah_pembalikan': 75000,
        'titipan': 0,
        'kas_diterima': amount,
        'kurang': amount < 75000 ? 75000 - amount : 0,
        'lebih': amount > 75000 ? amount - 75000 : 0,
        'alokasi': [
          {'periode': '2026-10', 'alokasi': amount < 75000 ? amount : 75000, 'sisa_sesudah': amount < 75000 ? 75000 - amount : 0},
        ],
      },
    });
  }
  if (o.method == 'POST') {
    writes.add(o);
    if (timeout && writes.length == 1) throw DioException(requestOptions: o, type: DioExceptionType.receiveTimeout);
    return jsonBody(201, {
      'data': {'id': 'p1'},
    });
  }
  return jsonBody(200, {'data': []});
};

Future<void> _tap(WidgetTester tester, String text) async {
  if (find.byTooltip('Tutup').evaluate().isNotEmpty) {
    await tester.tap(find.byTooltip('Tutup').last);
    await tester.pump(const Duration(milliseconds: 300));
  }
  if (find.text(text).evaluate().isEmpty) {
    await tester.scrollUntilVisible(find.text(text), 250, scrollable: find.byType(Scrollable).first);
  }
  await Scrollable.ensureVisible(tester.element(find.text(text).last), alignment: 0.5);
  await tester.pump(const Duration(milliseconds: 300));
  await tester.tap(find.text(text).last);
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

void main() {
  testWidgets('short receipt requires choice and reason before approval', (tester) async {
    final writes = <RequestOptions>[];
    await _open(tester, _handler(writes), confirmation: 'proof1');
    await _tap(tester, 'Periksa rincian');
    await _tap(tester, 'Simpan pencatatan');
    expect(writes, isEmpty);
    await _tap(tester, 'Lunas dengan keringanan (alasan wajib)');
    await _tap(tester, 'Simpan pencatatan');
    expect(writes, isEmpty);
    await tester.enterText(find.byKey(const ValueKey('finance-reason')), 'Nominal sesuai bukti terverifikasi');
    await _tap(tester, 'Simpan pencatatan');
    await _tap(tester, 'Simpan');
    expect(writes, hasLength(1));
    expect(writes.single.path, endsWith('/dues-confirmations/proof1/approve'));
    expect(_body(writes.single.data), containsPair('perlakuan_kurang', 'keringanan'));
    expect(_body(writes.single.data), containsPair('nominal', 50000));
    expect(_body(writes.single.data), containsPair('versi_keuangan', 2));
    expect(find.text('Pencatatan berhasil'), findsOneWidget);
  });

  for (final choice in ['Sumbangan ke kas', 'Titipan rumah untuk bulan berikutnya']) {
    testWidgets('excess receipt explicitly chooses $choice', (tester) async {
      final writes = <RequestOptions>[];
      await _open(tester, _handler(writes, amount: 100000), amount: 100000);
      await _tap(tester, 'Periksa rincian');
      await _tap(tester, choice);
      await _tap(tester, 'Simpan pencatatan');
      await _tap(tester, 'Simpan');
      expect(_body(writes.single.data)['perlakuan_lebih'], choice.startsWith('Sumbangan') ? 'sumbangan' : 'titipan');
    });
  }

  testWidgets('timeout locks inputs and retries same body key without a new preview', (tester) async {
    final writes = <RequestOptions>[];
    var previews = 0;
    final base = _handler(writes, timeout: true);
    await _open(tester, (o) {
      if (o.path.endsWith('/preview')) previews++;
      return base(o);
    });
    await _tap(tester, 'Periksa rincian');
    await _tap(tester, 'Kurang bayar, sisanya tetap tunggakan');
    await _tap(tester, 'Simpan pencatatan');
    await _tap(tester, 'Simpan');
    expect(writes, hasLength(1));
    expect(tester.widget<TextField>(find.byKey(const ValueKey('finance-amount'))).enabled, isFalse);
    await _tap(tester, 'Coba simpan lagi');
    expect(writes, hasLength(2));
    expect(writes[0].headers['Idempotency-Key'], writes[1].headers['Idempotency-Key']);
    expect(_body(writes[0].data), _body(writes[1].data));
    expect(previews, 1);
  });

  testWidgets('payment preview fits compact screen at large text scale', (tester) async {
    useCompactScreen(tester, width: 320, height: 760, textScale: 2);
    await _open(tester, _handler([]));
    await _tap(tester, 'Periksa rincian');
    expect(tester.takeException(), isNull);
  });
}
