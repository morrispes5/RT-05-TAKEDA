import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/features/warga/dues_year.dart';

import 'support/app_harness.dart';

Tagihan _t(String periode, {int terbayar = 75000, int sisa = 0, String label = 'Lunas', Map<String, Object?> extra = const {}}) => Tagihan({
  'id': periode,
  'periode': periode,
  'nominal': 75000,
  'terbayar': terbayar,
  'keringanan': 75000 - terbayar - sisa,
  'sisa': sisa,
  'status': sisa == 0 ? 'lunas' : 'belum_bayar',
  'status_label': label,
  ...extra,
});

Future<void> _pump(WidgetTester tester, List<Tagihan> list) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: buildTheme(),
      home: Scaffold(
        body: SingleChildScrollView(child: DuesYearView(tagihan: list)),
      ),
    ),
  );
  await tester.pump();
}

void main() {
  /// ADR53: tampilan per tahun seperti rekap bendahara; pemutihan dan bayar sesuai kemampuan bukan tunggakan.
  final list = [
    _t('2025-12', terbayar: 0, sisa: 75000, label: 'Belum dibayar'),
    _t('2026-01', label: 'Lunas (buku lama)', extra: {'buku_lama_metode': 'transfer'}),
    _t('2026-02', terbayar: 0, label: 'Diputihkan', extra: {'keringanan_pemutihan': 75000}),
    _t('2026-03', terbayar: 50000, label: 'Lunas', extra: {'keringanan_sukarela': 25000}),
    _t('2026-04', terbayar: 0, sisa: 75000, label: 'Belum dibayar'),
  ];

  testWidgets('menampilkan 12 bulan tahun berjalan dengan status ringkas', (tester) async {
    useCompactScreen(tester, height: 1600);
    await _pump(tester, list);

    expect(find.text('Iuran 2026'), findsOneWidget);
    expect(find.text('Lunas • TF'), findsOneWidget);
    expect(find.text('Diputihkan'), findsWidgets);
    expect(find.text('Belum'), findsOneWidget);
    expect(find.text('–'), findsNWidgets(8), reason: 'Mei–Des belum ada tagihan');
    expect(find.text('Dibayar Rp125.000 • Tunggakan Rp75.000'), findsOneWidget);
    expect(find.text('Tidak ditagih (pemutihan RT)'), findsOneWidget);
    expect(find.textContaining('Keringanan'), findsNothing, reason: 'Bayar sesuai kemampuan tampil lunas, bukan keringanan');
    expect(tester.takeException(), isNull);
  });

  testWidgets('pindah tahun memakai pilihan tahun', (tester) async {
    useCompactScreen(tester, height: 1600);
    await _pump(tester, list);

    await tester.tap(find.text('2025'));
    await tester.pump();
    expect(find.text('Iuran 2025'), findsOneWidget);
    expect(find.text('Desember 2025'), findsOneWidget);
    expect(find.text('Dibayar Rp0 • Tunggakan Rp75.000'), findsOneWidget);
  });

  testWidgets('muat di layar sempit dengan teks besar', (tester) async {
    useCompactScreen(tester, width: 320, height: 1600, textScale: 2);
    await _pump(tester, list);
    expect(tester.takeException(), isNull);
  });
}
