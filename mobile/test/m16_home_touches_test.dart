import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/features/warga/home_touches.dart';

import 'support/app_harness.dart';

Tagihan _t(String periode, {String status = 'lunas', int? terbayar}) => Tagihan({
  'id': periode,
  'periode': periode,
  'nominal': 75000,
  'status': status,
  'status_label': status == 'lunas' ? 'Lunas' : 'Belum dibayar',
  'terbayar': ?terbayar,
});

Future<void> _pump(WidgetTester tester, Widget child) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: buildTheme(),
      home: Scaffold(
        body: SingleChildScrollView(padding: const EdgeInsets.all(16), child: child),
      ),
    ),
  );
  await tester.pump();
}

void main() {
  /// Sentuhan kecil 16 Nov 2026: tampilan lama tetap, hanya ditambah salam, ucapan, dan ilustrasi.
  test('salam mengikuti jam WIB', () {
    expect(salamWaktu(4), 'Selamat pagi,');
    expect(salamWaktu(10), 'Selamat pagi,');
    expect(salamWaktu(11), 'Selamat siang,');
    expect(salamWaktu(15), 'Selamat sore,');
    expect(salamWaktu(18), 'Selamat malam,');
    expect(salamWaktu(0), 'Selamat malam,');
    expect(waktuMalam(3), isTrue);
    expect(waktuMalam(4), isFalse);
    expect(waktuMalam(17), isFalse);
    expect(waktuMalam(18), isTrue);
  });

  test('ucapan berganti tiap bulan dan aman untuk periode tak terbaca', () {
    final setahun = {for (var b = 1; b <= 12; b++) ucapanUntuk('2026-${b.toString().padLeft(2, '0')}').gambar};
    expect(setahun.length, ucapanTerimaKasih.length, reason: 'semua ucapan terpakai dalam setahun');
    expect(ucapanUntuk('2026-11').gambar, isNot(ucapanUntuk('2026-12').gambar));
    expect(ucapanUntuk('rusak'), ucapanTerimaKasih.first);
    expect(ucapanUntuk(''), ucapanTerimaKasih.first);
  });

  test('ucapan hanya untuk iuran yang benar-benar dibayar', () {
    expect(layakUcapan(_t('2026-11')), isTrue, reason: 'server lama tanpa field terbayar: lunas berarti dibayar');
    expect(layakUcapan(_t('2026-11', terbayar: 50000)), isTrue, reason: 'bayar sesuai kemampuan tetap dibayar');
    expect(layakUcapan(_t('2026-11', terbayar: 0)), isFalse, reason: 'diputihkan: lunas tanpa uang diterima');
    expect(layakUcapan(_t('2026-11', status: 'belum_bayar')), isFalse);
  });

  test('semua gambar ucapan dan hiasan ada dan aman untuk flutter_svg', () {
    final names = {...ucapanTerimaKasih.map((u) => u.gambar), 'hero_pagi', 'hero_malam', 'kosong_agenda', 'kosong_pengumuman'};
    for (final n in names) {
      final f = File('assets/ilustrasi/$n.svg');
      expect(f.existsSync(), isTrue, reason: n);
      final svg = f.readAsStringSync();
      for (final terlarang in ['<text', '<style', 'class=', '<image', '<use', '<filter', 'Gradient', '<mask']) {
        expect(svg.contains(terlarang), isFalse, reason: '$n memuat $terlarang');
      }
    }
    expect(Directory('assets/ilustrasi').listSync().length, names.length, reason: 'tidak ada berkas yatim');
  });

  testWidgets('strip ucapan menampilkan kalimat; versi rinci menambah periode dan nominal', (tester) async {
    await _pump(tester, ThanksStrip(tagihan: _t('2026-11', terbayar: 75000)));
    final u = ucapanUntuk('2026-11');
    expect(find.text(u.judul), findsOneWidget);
    expect(find.text(u.isi), findsOneWidget);
    expect(find.textContaining('lunas •'), findsNothing);

    await _pump(tester, ThanksStrip(tagihan: _t('2026-11', terbayar: 50000), rinci: true));
    expect(find.text('Iuran November 2026 lunas • Rp50.000'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('muat di layar sempit dengan teks besar', (tester) async {
    useCompactScreen(tester, width: 320, height: 1200, textScale: 2);
    await _pump(
      tester,
      Column(
        children: [
          ThanksStrip(tagihan: _t('2026-12', terbayar: 75000), rinci: true),
          const IllustratedEmpty(gambar: 'kosong_agenda', teks: 'Belum ada agenda.'),
          const IllustratedEmpty(gambar: 'kosong_pengumuman', teks: 'Belum ada pengumuman.'),
          const SizedBox(height: 120, child: HeroDecor(malam: false)),
          const SizedBox(height: 120, child: HeroDecor(malam: true)),
        ],
      ),
    );
    expect(find.text('Belum ada agenda.'), findsOneWidget);
    expect(find.text('Belum ada pengumuman.'), findsOneWidget);
    expect(find.byType(SvgPicture), findsNWidgets(5));
    expect(tester.takeException(), isNull);
  });
}
