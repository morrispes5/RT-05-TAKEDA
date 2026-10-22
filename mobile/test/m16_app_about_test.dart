import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/app_about.dart';

import 'support/fakes.dart';

/// M16: versi aplikasi, pengelola, dan pemberitahuan versi baru dari /unduh/versi.json di situs resmi.
Future<List<RequestOptions>> _pump(WidgetTester tester, {required int installed, required FakeHandler handler}) async {
  final requests = <RequestOptions>[];
  final container = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'prod', apiBaseUrl: 'https://rt05.example')),
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage('rahasia')),
      httpAdapterProvider.overrideWithValue(
        FakeAdapter((o) {
          requests.add(o);
          return handler(o);
        }),
      ),
      fetchMeProvider.overrideWithValue((_) async => Akun(akunJson())),
      installedBuildProvider.overrideWithValue(installed),
    ],
  );
  addTearDown(container.dispose);
  await tester.runAsync(() => container.read(sessionProvider.notifier).restore());
  expect(container.read(sessionProvider).token, 'rahasia');
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(
        theme: buildTheme(),
        home: const Scaffold(body: SingleChildScrollView(child: AppAboutCard())),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return requests;
}

void main() {
  test('versi di AppConfig sama dengan pubspec.yaml', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    expect(RegExp(r'^version: (\S+)\+', multiLine: true).firstMatch(pubspec)?.group(1), AppConfig.appVersion);
  });

  testWidgets('versi baru dari situs resmi ditampilkan tanpa mengirim token', (tester) async {
    final requests = await _pump(tester, installed: 1012, handler: (o) => jsonBody(200, {'versi': '1.0.1', 'build': 1015}));
    expect(find.text('Versi 1.0.0 (build 1012)'), findsOneWidget);
    expect(find.byKey(const ValueKey('versi-baru')), findsOneWidget);
    expect(find.textContaining('Versi baru 1.0.1 (build 1015) tersedia'), findsOneWidget);
    expect(find.text('Unduh versi terbaru'), findsOneWidget);
    final release = requests.singleWhere((o) => o.uri.path == '/unduh/versi.json');
    expect(release.uri.toString(), 'https://rt05.example/unduh/versi.json');
    expect(release.headers.containsKey('Authorization'), isFalse);
  });

  testWidgets('versi terpasang sudah terbaru: tidak ada pemberitahuan', (tester) async {
    await _pump(tester, installed: 1015, handler: (o) => jsonBody(200, {'versi': '1.0.1', 'build': 1015}));
    expect(find.byKey(const ValueKey('versi-baru')), findsNothing);
    expect(find.text('Halaman unduh resmi'), findsOneWidget);
  });

  testWidgets('versi.json belum ada atau rusak: kartu tetap tampil tanpa error', (tester) async {
    await _pump(tester, installed: 1012, handler: (o) => jsonBody(404, {'message': 'Not Found'}));
    expect(find.text('Versi 1.0.0 (build 1012)'), findsOneWidget);
    expect(find.byKey(const ValueKey('versi-baru')), findsNothing);
  });

  testWidgets('build lokal tanpa nomor tidak pernah menyarankan unduh ulang', (tester) async {
    await _pump(tester, installed: 0, handler: (o) => jsonBody(200, {'versi': '1.0.1', 'build': 1015}));
    expect(find.text('Versi 1.0.0'), findsOneWidget);
    expect(find.byKey(const ValueKey('versi-baru')), findsNothing);
  });
}
