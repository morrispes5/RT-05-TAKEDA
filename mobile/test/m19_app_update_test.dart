import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/app_update_platform.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/app_about.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// ADR46: pembaruan APK dari dalam aplikasi — unduh dari /unduh situs resmi, Android mencocokkan SHA-256 dan versi,
/// layar Install Android terbuka. Izin "sumber ini" dan kegagalan dijelaskan; pop-up sekali per aplikasi dibuka.
const _armSha = 'dfccc84a732aa3aff20c316e04043120e976190187a8da34291376f39898615a';
const _v7Sha = '1efa826ba707953b706e58ee281624de2402d051a5975ab00069796c668f231d';

Map<String, Object?> _release({int build = 1026, List<Object?>? berkas}) => {
  'versi': '1.0.0',
  'build': build,
  'tanggal': '2026-10-28T03:00:00.000Z',
  'catatan': ['Perbarui aplikasi langsung dari aplikasi', 'Perbaikan hasil uji HP'],
  'berkas':
      berkas ??
      [
        {'abi': 'arm64-v8a', 'url': '/unduh/rt05-takeda-staging-$build-arm64-v8a.apk', 'ukuran': 23006585, 'sha256': _armSha},
        {'abi': 'armeabi-v7a', 'url': '/unduh/rt05-takeda-staging-$build-armeabi-v7a.apk', 'ukuran': 20857669, 'sha256': _v7Sha},
      ],
};

class FakeUpdatePlatform implements AppUpdatePlatform {
  FakeUpdatePlatform({this.abiList = const ['arm64-v8a', 'armeabi-v7a']});
  final List<String> abiList;
  final installs = <(String, String)>[];

  /// Hasil install berurutan: [InstallStart] atau [PlatformException]; habis = started.
  final results = <Object>[];
  int permissionOpened = 0;
  final _statuses = StreamController<String>.broadcast();
  void emit(String status) => _statuses.add(status);

  @override
  bool get supported => true;
  @override
  Future<List<String>> abis() async => abiList;
  @override
  Future<String> directory() async => '/cache/pembaruan';
  @override
  Future<InstallStart> install(String path, String sha256) async {
    installs.add((path, sha256));
    if (results.isEmpty) return InstallStart.started;
    final r = results.removeAt(0);
    if (r is PlatformException) throw r;
    return r as InstallStart;
  }

  @override
  Future<void> openInstallPermission() async => permissionOpened++;
  @override
  Stream<String> get statuses => _statuses.stream;
}

class FakeDownloader {
  final calls = <(String, String)>[];
  int failures = 0;
  Future<void> call(String url, String savePath, void Function(int, int) onProgress, CancelToken cancel) async {
    calls.add((url, savePath));
    if (failures > 0) {
      failures--;
      throw DioException.connectionError(
        requestOptions: RequestOptions(path: url),
        reason: 'offline (tes)',
      );
    }
    onProgress(50, 100);
    onProgress(100, 100);
  }
}

Future<void> _pumpCard(WidgetTester tester, {required FakeUpdatePlatform platform, required FakeDownloader downloader, Object? release}) async {
  final container = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'prod', apiBaseUrl: 'https://rt05.example')),
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage(null)),
      httpAdapterProvider.overrideWithValue(FakeAdapter((o) => jsonBody(200, release ?? _release()))),
      installedBuildProvider.overrideWithValue(1025),
      appUpdatePlatformProvider.overrideWithValue(platform),
      apkDownloaderProvider.overrideWithValue(downloader.call),
    ],
  );
  addTearDown(container.dispose);
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
}

Future<void> _startUpdate(WidgetTester tester) async {
  await tester.tap(find.byKey(const ValueKey('perbarui-aplikasi')));
  await tester.pumpAndSettle();
  await tester.tap(find.byKey(const ValueKey('pembaruan-mulai')));
  await tester.pumpAndSettle();
}

void main() {
  test('versi.json: catatan dan berkas resmi dibaca, berkas tidak sah diabaikan', () {
    final r = AppRelease.fromJson(
      _release(
        berkas: [
          {'abi': 'arm64-v8a', 'url': 'https://lain.example/x.apk', 'ukuran': 10, 'sha256': _armSha},
          {'abi': 'arm64-v8a', 'url': '/unduh/../rahasia.apk', 'ukuran': 10, 'sha256': _armSha},
          {'abi': 'arm64-v8a', 'url': '/unduh/ok.apk', 'ukuran': 10, 'sha256': 'pendek'},
          {'abi': 'armeabi-v7a', 'url': '/unduh/ok-v7.apk', 'ukuran': 10, 'sha256': _v7Sha},
        ],
      ),
    )!;
    expect(r.catatan, ['Perbarui aplikasi langsung dari aplikasi', 'Perbaikan hasil uji HP']);
    expect(r.berkas.map((f) => f.url), ['/unduh/ok-v7.apk']);
    expect(r.fileFor(['arm64-v8a', 'armeabi-v7a'])?.abi, 'armeabi-v7a');
    expect(r.fileFor(['x86_64']), isNull);
  });

  testWidgets('Perbarui sekarang: unduh APK sesuai HP, SHA-256 resmi diteruskan, layar Install terbuka', (tester) async {
    final platform = FakeUpdatePlatform();
    final downloader = FakeDownloader();
    await _pumpCard(tester, platform: platform, downloader: downloader);
    expect(find.textContaining('Perbarui langsung dari aplikasi'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('perbarui-aplikasi')));
    await tester.pumpAndSettle();
    expect(find.text('Versi baru tersedia'), findsOneWidget);
    expect(find.text('• Perbaikan hasil uji HP'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('pembaruan-mulai')));
    await tester.pumpAndSettle();
    expect(downloader.calls, [('/unduh/rt05-takeda-staging-1026-arm64-v8a.apk', '/cache/pembaruan/rt05-takeda-staging-1026-arm64-v8a.apk')]);
    expect(platform.installs, [('/cache/pembaruan/rt05-takeda-staging-1026-arm64-v8a.apk', _armSha)]);
    expect(find.textContaining('Ikuti layar pemasangan Android'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('pembaruan-nanti')));
    await tester.pumpAndSettle();
    expect(find.text('Versi baru tersedia'), findsNothing);
  });

  testWidgets('HP 32-bit mendapat APK armeabi-v7a', (tester) async {
    final platform = FakeUpdatePlatform(abiList: ['armeabi-v7a', 'armeabi']);
    final downloader = FakeDownloader();
    await _pumpCard(tester, platform: platform, downloader: downloader);
    await _startUpdate(tester);
    expect(downloader.calls.single.$1, '/unduh/rt05-takeda-staging-1026-armeabi-v7a.apk');
    expect(platform.installs.single.$2, _v7Sha);
  });

  testWidgets('izin "sumber ini" belum ada: buka pengaturan lalu Pasang tanpa unduh ulang', (tester) async {
    final platform = FakeUpdatePlatform()..results.add(InstallStart.needsPermission);
    final downloader = FakeDownloader();
    await _pumpCard(tester, platform: platform, downloader: downloader);
    await _startUpdate(tester);
    expect(find.textContaining('izinkan RT05 TAKEDA memasang pembaruan'), findsOneWidget);
    await tester.tap(find.text('Buka pengaturan'));
    await tester.pumpAndSettle();
    expect(platform.permissionOpened, 1);
    await tester.tap(find.byKey(const ValueKey('pembaruan-pasang')));
    await tester.pumpAndSettle();
    expect(platform.installs, hasLength(2));
    expect(downloader.calls, hasLength(1));
    expect(find.textContaining('Ikuti layar pemasangan Android'), findsOneWidget);
  });

  testWidgets('SHA-256 tidak cocok: tidak dipasang, Coba lagi mengunduh ulang', (tester) async {
    final platform = FakeUpdatePlatform()..results.add(PlatformException(code: 'HASH'));
    final downloader = FakeDownloader();
    await _pumpCard(tester, platform: platform, downloader: downloader);
    await _startUpdate(tester);
    expect(find.textContaining('tidak sama dengan versi resmi'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('pembaruan-ulang')));
    await tester.pumpAndSettle();
    expect(downloader.calls, hasLength(2));
    expect(find.textContaining('Ikuti layar pemasangan Android'), findsOneWidget);
  });

  testWidgets('unduhan gagal: pesan koneksi, Coba lagi berhasil', (tester) async {
    final platform = FakeUpdatePlatform();
    final downloader = FakeDownloader()..failures = 1;
    await _pumpCard(tester, platform: platform, downloader: downloader);
    await _startUpdate(tester);
    expect(find.textContaining('Unduhan gagal'), findsOneWidget);
    expect(platform.installs, isEmpty);
    await tester.tap(find.byKey(const ValueKey('pembaruan-ulang')));
    await tester.pumpAndSettle();
    expect(platform.installs, hasLength(1));
  });

  testWidgets('pemasangan dibatalkan di layar Android: dijelaskan dan bisa dicoba lagi', (tester) async {
    final platform = FakeUpdatePlatform();
    final downloader = FakeDownloader();
    await _pumpCard(tester, platform: platform, downloader: downloader);
    await _startUpdate(tester);
    platform.emit('ABORTED');
    await tester.pumpAndSettle();
    expect(find.textContaining('Pemasangan dibatalkan'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('pembaruan-ulang')));
    await tester.pumpAndSettle();
    expect(platform.installs, hasLength(2));
    expect(downloader.calls, hasLength(1));
  });

  testWidgets('versi.json tanpa berkas sah: kembali ke tautan halaman unduh resmi', (tester) async {
    await _pumpCard(
      tester,
      platform: FakeUpdatePlatform(),
      downloader: FakeDownloader(),
      release: _release(berkas: const []),
    );
    expect(find.byKey(const ValueKey('versi-baru')), findsOneWidget);
    expect(find.byKey(const ValueKey('perbarui-aplikasi')), findsNothing);
    expect(find.text('Unduh versi terbaru'), findsOneWidget);
  });

  for (final (label, installed, shown) in [('versi lebih baru', 1025, true), ('sudah terbaru', 1026, false)]) {
    testWidgets('aplikasi utuh: pop-up pembaruan $label', (tester) async {
      await pumpApp(
        tester,
        storage: MemoryTokenStorage('t'),
        handler: apiHandler(me: () => akunJson(), fixtures: {...shellFixtures(), '/unduh/versi.json': _release()}),
        overrides: [
          installedBuildProvider.overrideWithValue(installed),
          appUpdatePlatformProvider.overrideWithValue(FakeUpdatePlatform()),
          apkDownloaderProvider.overrideWithValue(FakeDownloader().call),
        ],
      );
      expect(find.text('Versi baru tersedia'), shown ? findsOneWidget : findsNothing);
      if (!shown) return;
      await tester.tap(find.byKey(const ValueKey('pembaruan-nanti')));
      await tester.pumpAndSettle();
      // Sekali per aplikasi dibuka: berpindah tab tidak memunculkannya lagi.
      for (final tab in ['Layanan', 'Beranda']) {
        await tester.tap(find.widgetWithText(NavigationDestination, tab));
        await tester.pumpAndSettle();
      }
      expect(find.text('Versi baru tersedia'), findsNothing);
    });
  }
}
