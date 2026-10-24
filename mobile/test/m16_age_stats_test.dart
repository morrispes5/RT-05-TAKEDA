import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/auth/privacy_policy.dart';
import 'package:rt05_takeda/features/household/age_stats.dart';
import 'package:rt05_takeda/features/household/birth_date.dart';
import 'package:rt05_takeda/features/household/family_actions.dart';
import 'package:rt05_takeda/features/household/family_page.dart';

import 'support/fakes.dart';

/// ADR42 (permintaan Pak RT 24 Okt 2026): tanggal lahir anggota keluarga dan statistik kelompok umur untuk pengurus.
Object? _body(RequestOptions options) {
  final data = options.data;
  if (data is Map) return Map<String, dynamic>.from(data);
  if (data is String && data.isNotEmpty) return jsonDecode(data);
  return data;
}

Future<void> _pump(WidgetTester tester, Widget home, FakeHandler handler) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(
        theme: buildTheme(),
        home: home is Scaffold ? home : Scaffold(body: home),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Map<String, dynamic> _stats({List<Map<String, dynamic>> warga = const []}) => {
  'data': {
    'dihitung_pada': '2026-10-24',
    'total_warga': 5,
    'belum_diisi': 1,
    'kelompok': [
      {'kode': 'balita', 'label': 'Balita', 'min': 0, 'max': 5, 'jumlah': 1},
      {'kode': 'anak', 'label': 'Anak-anak', 'min': 6, 'max': 12, 'jumlah': 0},
      {'kode': 'remaja', 'label': 'Remaja', 'min': 13, 'max': 17, 'jumlah': 0},
      {'kode': 'dewasa', 'label': 'Dewasa', 'min': 18, 'max': 59, 'jumlah': 1},
      {'kode': 'lansia', 'label': 'Lansia', 'min': 60, 'max': 69, 'jumlah': 1},
      {'kode': 'lansia_70', 'label': 'Lansia 70+', 'min': 70, 'max': null, 'jumlah': 1},
    ],
    'rumah': [
      {
        'keluarga_id': 'k-1',
        'versi_keluarga': 3,
        'rumah': {'id': 'h-1', 'kode_rumah': 'D5-21', 'alamat': 'Blok D5 No. 21'},
        'jumlah_anggota': 3,
        'belum_diisi': 0,
        'kelompok': {'balita': 1, 'anak': 0, 'remaja': 0, 'dewasa': 0, 'lansia': 1, 'lansia_70': 1},
      },
    ],
    'warga': warga,
  },
};

void main() {
  test('umur dihitung per tanggal ulang tahun, sama dengan server', () {
    final today = DateTime(2026, 10, 24);
    expect(umurPada(DateTime(1966, 10, 24), today), 60);
    expect(umurPada(DateTime(1966, 10, 25), today), 59);
    expect(umurPada(DateTime(2026, 10, 24), today), 0);
    expect(parseTanggalLahir('1985-06-15'), DateTime(1985, 6, 15));
    expect(parseTanggalLahir(null), isNull);
    expect(tanggalLahirBody(DateTime(1985, 6, 5)), '1985-06-05');
    expect(tanggalLahirText(DateTime(1985, 6, 15)), '15 Juni 1985');
    expect(todayJakarta(DateTime.utc(2026, 10, 23, 18)), DateTime(2026, 10, 24), reason: '01.00 WIB sudah tanggal 24');
    expect(memberSubtitle({'hubungan_keluarga': 'Anak', 'umur': 7}), 'Anak • 7 th');
  });

  test('Kebijakan Privasi menyebut tanggal lahir dan siapa yang dapat melihatnya', () {
    final teks = privacySections.expand((s) => s.$2).join(' ');
    expect(teks, contains('tanggal lahir anggota keluarga'));
    expect(teks, contains('nomor WhatsApp, dan tanggal lahir hanya dapat dilihat oleh pengurus RT'));
  });

  testWidgets('anggota: tanggal lahir tampil dengan umur, dapat dikosongkan, dan terkirim ke server', (tester) async {
    Object? sent;
    await _pump(
      tester,
      const MemberPage(
        household: {'versi': 4},
        member: {'id': 'w-2', 'nama': 'Anak Uji', 'hubungan_keluarga': 'Anak', 'is_kepala': false, 'tanggal_lahir': '2019-03-01', 'umur': 7},
      ),
      (o) {
        if (o.method == 'PATCH') {
          sent = _body(o);
          return jsonBody(200, {
            'data': {'id': 'w-2', 'versi_keluarga': 5},
          });
        }
        return jsonBody(200, {'data': {}});
      },
    );
    expect(find.textContaining('1 Maret 2019'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('hapus-tanggal-lahir')));
    await tester.pump();
    expect(find.text('Belum diisi'), findsOneWidget);
    await tester.tap(find.text('Simpan perubahan'));
    await tester.pumpAndSettle();
    expect(sent, containsPair('tanggal_lahir', null));
    expect(sent, containsPair('versi', 4));
  });

  testWidgets('anggota baru tanpa tanggal lahir tidak mengirim kolom itu', (tester) async {
    Object? sent;
    await _pump(tester, const MemberPage(household: {'versi': 2}), (o) {
      if (o.method == 'POST') {
        sent = _body(o);
        return jsonBody(201, {
          'data': {'id': 'w-9', 'versi_keluarga': 3},
        });
      }
      return jsonBody(200, {'data': {}});
    });
    await tester.enterText(find.widgetWithText(TextField, 'Nama lengkap'), 'Nenek Uji');
    await tester.enterText(find.widgetWithText(TextField, 'Hubungan'), 'Orang tua');
    await tester.tap(find.text('Tambah anggota'));
    await tester.pumpAndSettle();
    expect(sent, isA<Map>());
    expect((sent as Map).containsKey('tanggal_lahir'), isFalse);
  });

  testWidgets('statistik: jumlah per kelompok, saringan kelompok dan rentang bebas, tampilan per rumah', (tester) async {
    final queries = <Map<String, dynamic>>[];
    await _pump(tester, const AgeStatsTab(), (o) {
      queries.add(Map<String, dynamic>.from(o.queryParameters));
      final lansia = o.queryParameters['kelompok'] == 'lansia' || o.queryParameters['min'] != null;
      return jsonBody(
        200,
        _stats(
          warga: [
            if (lansia)
              {
                'id': 'w-1',
                'nama': 'Kakek Uji',
                'hubungan_keluarga': 'Kepala keluarga',
                'umur': 64,
                'kelompok_usia': 'lansia',
                'keluarga_id': 'k-1',
                'versi_keluarga': 3,
                'rumah': {'kode_rumah': 'D5-21'},
              },
          ],
        ),
      );
    });
    expect(find.textContaining('Total 5 warga aktif • 1 belum mengisi tanggal lahir'), findsOneWidget);
    expect(find.text('Lansia (60–69 th): 1'), findsOneWidget);
    expect(find.text('Lansia 70+ (≥70 th): 1'), findsOneWidget);
    expect(find.text('Belum diisi: 1'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('kelompok-lansia')));
    await tester.pumpAndSettle();
    expect(queries.last['kelompok'], 'lansia');
    expect(find.text('Kakek Uji'), findsOneWidget);
    expect(find.textContaining('64 th • Lansia • Rumah D5-21'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('umur-min')), '60');
    await tester.enterText(find.byKey(const ValueKey('umur-max')), '65');
    await tester.tap(find.byKey(const ValueKey('terapkan-rentang')));
    await tester.pumpAndSettle();
    expect(queries.last['min'], 60);
    expect(queries.last['max'], 65);
    expect(queries.last.containsKey('kelompok'), isFalse);
    expect(find.textContaining('Umur 60–65 tahun • 1 orang'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('umur-min')), '70');
    await tester.enterText(find.byKey(const ValueKey('umur-max')), '60');
    await tester.tap(find.byKey(const ValueKey('terapkan-rentang')));
    await tester.pump();
    expect(find.text('Umur awal tidak boleh lebih besar dari umur akhir.'), findsOneWidget);

    await tester.tap(find.text('Per rumah'));
    await tester.pumpAndSettle();
    expect(find.text('Rumah D5-21 • 3 orang'), findsOneWidget);
    expect(find.text('Balita 1 • Lansia 1 • Lansia 70+ 1'), findsOneWidget);
  });
}
