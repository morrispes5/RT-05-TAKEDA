import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart' show Headers, RequestOptions, ResponseBody;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/month_field.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/auth/auth_pages.dart';
import 'package:rt05_takeda/features/pengurus/finance_actions.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// UAT M15 putaran 2 (20 Okt 2026): ekspor CSV pembayaran/kas/rekap dan pemilih bulan mulai tagih.
ResponseBody _csv(String text) => ResponseBody.fromBytes(
  utf8.encode(text),
  200,
  headers: {
    Headers.contentTypeHeader: ['text/csv; charset=utf-8'],
  },
);

void main() {
  testWidgets('ekspor CSV kas dan rekap bulan menyimpan berkas dari server', (tester) async {
    useCompactScreen(tester, height: 1200);
    final requests = <String>[];
    final saved = <String, Uint8List>{};
    final base = apiHandler(
      me: () => akunJson(peran: 'pengurus'),
      fixtures: {
        '/api/v1/admin/cash/summary': {
          'data': {
            'saldo': 0,
            'bulan_ini': {'pemasukan': 0, 'pengeluaran': 0},
            'mutasi_terakhir': [],
          },
        },
        '/api/v1/admin/dues-payments': {'data': [], 'meta': {}},
        '/api/v1/admin/dues-recap': {
          'data': {
            'periode': '2026-10',
            'tarif': 75000,
            'jumlah_rumah': 0,
            'lunas': {'rumah': 0, 'nominal': 0},
            'belum_bayar': {'rumah': 0, 'nominal': 0},
            'tunggakan_sebelumnya': {'rumah': 0, 'nominal': 0},
            'rumah': [],
          },
        },
      },
    );
    FutureOr<ResponseBody> handler(RequestOptions o) {
      if (o.uri.path.startsWith('/api/v1/admin/exports/')) {
        requests.add(o.uri.toString().replaceFirst('https://api.example.test', ''));
        return _csv('﻿tanggal,jenis\n2026-10-19,pengeluaran\n');
      }
      return base(o);
    }

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
          httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
          saveBytesProvider.overrideWithValue((name, bytes) async {
            saved[name] = bytes;
            return true;
          }),
        ],
        child: MaterialApp(
          theme: buildTheme(),
          home: const Scaffold(body: FinanceAdminPage()),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Kas'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('ekspor-kas')));
    await tester.pumpAndSettle();
    expect(requests.last, '/api/v1/admin/exports/kas');
    expect(saved.keys, contains('rt05-kas.csv'));
    expect(utf8.decode(saved['rt05-kas.csv']!), contains('2026-10-19,pengeluaran'));
    expect(find.textContaining('Ekspor berhasil'), findsOneWidget);

    await tester.tap(find.text('Rekap'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('ekspor-iuran')));
    await tester.pumpAndSettle();
    expect(requests.last, startsWith('/api/v1/admin/exports/iuran?periode='));
    expect(tester.takeException(), isNull);
  });

  testWidgets('pemilih bulan mengisi YYYY-MM dan menampilkan nama bulan', (tester) async {
    final c = TextEditingController();
    addTearDown(c.dispose);
    await tester.pumpWidget(
      MaterialApp(
        theme: buildTheme(),
        home: Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(16),
            child: MonthField(controller: c, label: 'Mulai tagih'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Mulai tagih'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Okt'));
    await tester.pumpAndSettle();
    expect(c.text, '${DateTime.now().year}-10');
    expect(find.text('Oktober ${DateTime.now().year}'), findsOneWidget);
    await tester.tap(find.byTooltip('Kosongkan bulan'));
    await tester.pumpAndSettle();
    expect(c.text, '');
  });

  testWidgets('pemohon memperbaiki permohonan akun tambahan dan mengirim ulang', (tester) async {
    useCompactScreen(tester, height: 1200);
    Object? sent;
    final base = apiHandler(
      me: () => akunJson(status: 'menunggu', jenis: 'tambahan'),
    );
    FutureOr<ResponseBody> handler(RequestOptions o) {
      if (o.uri.path == '/api/v1/auth/additional-account-request' && o.method == 'PATCH') {
        sent = o.data;
        return jsonBody(200, {
          'data': {'status': 'diajukan'},
        });
      }
      return base(o);
    }

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
          httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
        ],
        child: MaterialApp(
          theme: buildTheme(),
          home: const RevisionRequestPage(
            permohonan: {'status': 'perlu_revisi', 'catatan': 'Nomor rumah salah.', 'nama': 'Sri Uji', 'hubungan_keluarga': 'Anak'},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('Nomor rumah salah.'), findsOneWidget);
    await tester.tap(find.text('Kirim perbaikan'));
    await tester.pumpAndSettle();
    expect(find.text('Jalan wajib diisi.'), findsOneWidget);
    expect(sent, isNull);

    await tester.enterText(find.byKey(const ValueKey('revisi-blok')), 'D5');
    await tester.enterText(find.byKey(const ValueKey('revisi-jalan')), 'Jl. Melati');
    await tester.enterText(find.byKey(const ValueKey('revisi-nomor')), '23');
    await tester.tap(find.text('Kirim perbaikan'));
    // BusyButton berputar selama dialog hasil terbuka: pump bertahap, bukan pumpAndSettle.
    for (var i = 0; i < 8; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    expect(sent, {'nama': 'Sri Uji', 'hubungan_keluarga': 'Anak', 'blok': 'D5', 'jalan': 'Jl. Melati', 'nomor': '23'});
    expect(find.text('Perbaikan terkirim'), findsOneWidget);
  });
}
