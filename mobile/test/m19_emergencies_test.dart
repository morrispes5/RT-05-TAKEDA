import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/emergency_platform.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/push.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/auth/privacy_policy.dart';
import 'package:rt05_takeda/features/warga/emergencies.dart';
import 'package:rt05_takeda/main.dart';

import 'support/fakes.dart';
import 'support/app_harness.dart';

class _Session extends SessionController {
  @override
  SessionState build() => SessionState(loading: false, token: 'synthetic', akun: Akun(akunJson()));
  void replace(SessionState next) => state = next;
}

class _AdminSession extends _Session {
  @override
  SessionState build() => SessionState(
    loading: false,
    token: 'synthetic',
    akun: Akun(akunJson(peran: 'pengurus')),
  );
}

class _Shortcut extends NoEmergencyPlatform {
  @override
  Future<Map<String, dynamic>?> initialLaunch() async => {'type': 'shortcut'};
}

class _PinShortcut extends NoEmergencyPlatform {
  int requests = 0;
  @override
  Future<bool> pinShortcut() async {
    requests++;
    return true;
  }
}

class _NotReady extends NoEmergencyPlatform {
  int fullScreenRequests = 0;
  int settingsRequests = 0;
  @override
  Future<AlarmReadiness> readiness() async => const AlarmReadiness(notifications: false, fullScreen: false);
  @override
  Future<void> fullScreenSettings() async => fullScreenRequests++;
  @override
  Future<void> settings() async => settingsRequests++;
}

const _id = '00000000-0000-4000-8000-000000000019';
final _report = {'id': _id, 'jenis': 'kebakaran', 'status': 'aktif', 'versi': 1, 'pelapor': 'Pelapor sintetis', 'rumah': 'Rumah sintetis'};
final _status = {'tersedia': true, 'aktif': true, 'versi': 1, 'penyalahgunaan': 0, 'versi_ketentuan': emergencyTermsVersion, 'uji_terbatas': true};

Future<ProviderContainer> _pump(
  WidgetTester tester,
  FakeAdapter adapter, {
  Widget page = const EmergencyPage(),
  bool admin = false,
  EmergencyPlatform platform = const NoEmergencyPlatform(),
  bool settle = true,
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        httpAdapterProvider.overrideWithValue(adapter),
        emergencyPlatformProvider.overrideWithValue(platform),
        sessionProvider.overrideWith(admin ? _AdminSession.new : _Session.new),
      ],
      child: MaterialApp(home: page),
    ),
  );
  if (settle) {
    await tester.pumpAndSettle();
  } else {
    for (var i = 0; i < 6; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
  }
  return ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
}

Future<void> _tap(WidgetTester tester, String label) async {
  final f = find.text(label);
  for (var i = 0; i < 24 && f.hitTestable().evaluate().isEmpty; i++) {
    final down = f.evaluate().isEmpty || tester.getCenter(f).dy > 400;
    await tester.drag(find.byType(Scrollable).first, Offset(0, down ? -180 : 180));
    await tester.pump(const Duration(milliseconds: 100));
  }
  await tester.tap(f.hitTestable());
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _unlock(WidgetTester tester) async {
  for (var i = 0; i < 24 && find.text('Geser untuk menyalakan alarm').evaluate().isEmpty; i++) {
    await tester.drag(find.byType(Scrollable).first, const Offset(0, -240));
    await tester.pump(const Duration(milliseconds: 80));
  }
  await tester.pump();
}

Future<void> _arm(WidgetTester tester) async {
  await _unlock(tester);
  final slider = find.byKey(const ValueKey('geser-aktifkan'));
  expect(slider, findsOneWidget);
  final size = tester.getSize(slider);
  await tester.drag(slider, Offset(size.width * 0.95, 0));
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 50));
  }
}

void main() {
  testWidgets('panic access on pengurus Home remains available when the finance dashboard fails', (tester) async {
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.uri.path.endsWith('/emergencies/status')) return jsonBody(200, {'data': _status});
        return errorBody(503, 'SERVICE_UNAVAILABLE', 'Dashboard sementara tidak tersedia');
      }),
      page: const Scaffold(floatingActionButton: EmergencyFab(), body: SizedBox.expand()),
      admin: true,
      settle: false,
    );
    expect(find.byType(EmergencyFab), findsOneWidget);
    expect(find.byType(FloatingActionButton), findsOneWidget);
    expect(find.text('Tombol panik'), findsNothing);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
  });

  testWidgets('server tanpa gerbang rilis panik: tanpa FAB dan tanpa riwayat di Beranda', (tester) async {
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.uri.path.endsWith('/emergencies/status')) {
          return jsonBody(200, {
            'data': {..._status, 'tersedia': false},
          });
        }
        if (o.uri.path.endsWith('/emergencies')) return errorBody(403, 'PANIC_UNAVAILABLE', 'Tombol panik belum tersedia di server ini.');
        return errorBody(404, 'NOT_FOUND', '-');
      }),
      page: const Scaffold(floatingActionButton: EmergencyFab(), body: EmergencyHomeSection()),
    );
    expect(find.byType(FloatingActionButton), findsNothing);
    expect(find.text('Laporan darurat'), findsNothing);
    expect(find.text('Tombol panik belum tersedia di server ini.'), findsNothing);
  });

  testWidgets('ADR50: Beranda meminta izin notifikasi dan layar penuh agar alarm bisa berdering', (tester) async {
    final platform = _NotReady();
    FakeAdapter adapter() => FakeAdapter((o) {
      if (o.uri.path.endsWith('/emergencies/status')) return jsonBody(200, {'data': _status});
      if (o.uri.path.endsWith('/emergencies')) return jsonBody(200, {'data': [], 'meta': {}});
      return errorBody(404, 'NOT_FOUND', '-');
    });
    await _pump(
      tester,
      adapter(),
      page: const Scaffold(body: SingleChildScrollView(child: EmergencyHomeSection())),
      platform: platform,
    );
    expect(find.text('Izinkan alarm darurat'), findsOneWidget);
    await tester.tap(find.text('Izinkan layar penuh'));
    await tester.tap(find.text('Buka pengaturan notifikasi'));
    expect(platform.fullScreenRequests, 1);
    expect(platform.settingsRequests, 1);

    // ProviderScope baru (izin sudah diberikan): kartu tidak tampil lagi.
    await tester.pumpWidget(const SizedBox());
    await _pump(
      tester,
      adapter(),
      page: const Scaffold(body: SingleChildScrollView(child: EmergencyHomeSection())),
    );
    expect(find.text('Izinkan alarm darurat'), findsNothing);
    expect(find.text('Laporan darurat'), findsOneWidget);
  });

  testWidgets('pengurus has panic shortcut directly on Home and it never submits automatically', (tester) async {
    var posts = 0;
    final base = apiHandler(me: () => akunJson(peran: 'pengurus'));
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') posts++;
        if (o.uri.path.endsWith('/emergencies/status')) return jsonBody(200, {'data': _status});
        return base(o);
      }),
      page: const Scaffold(floatingActionButton: EmergencyFab(), body: SizedBox.expand()),
      admin: true,
    );
    expect(find.byType(EmergencyFab), findsOneWidget);
    await tester.tap(find.byType(EmergencyFab));
    await tester.pumpAndSettle();
    expect(find.text('Baca dulu sebelum menyalakan.'), findsOneWidget);
    expect(posts, 0);
  });

  testWidgets('pin shortcut requests launcher confirmation without sending an emergency', (tester) async {
    final platform = _PinShortcut();
    var posts = 0;
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') posts++;
        return jsonBody(200, {'data': _status});
      }),
      platform: platform,
    );
    await _tap(tester, 'Tambahkan shortcut di layar utama HP');
    expect(platform.requests, 1);
    expect(posts, 0);
    expect(find.textContaining('Konfirmasi penambahan shortcut'), findsOneWidget);
  });

  testWidgets('Android shortcut waits for restored session and new privacy consent, then opens only step one', (tester) async {
    var needsPrivacy = true;
    final posts = <RequestOptions>[];
    final base = apiHandler(me: () => {...akunJson(), 'privasi_versi': panicPrivacyTextVersion, 'privasi_perlu_setuju': needsPrivacy});
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('synthetic')),
          emergencyPlatformProvider.overrideWithValue(_Shortcut()),
          httpAdapterProvider.overrideWithValue(
            FakeAdapter((o) {
              if (o.method == 'POST') posts.add(o);
              if (o.uri.path.endsWith('/emergencies/status')) return jsonBody(200, {'data': _status});
              return base(o);
            }),
          ),
        ],
        child: const Rt05App(),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byType(PrivacyPolicyPage), findsOneWidget);
    expect(find.byType(EmergencyPage), findsNothing);
    final container = ProviderScope.containerOf(tester.element(find.byType(Rt05App)));
    needsPrivacy = false;
    final session = container.read(sessionProvider.notifier);
    session.updateAkun(Akun({...akunJson(), 'privasi_versi': panicPrivacyTextVersion, 'privasi_perlu_setuju': false}), epoch: session.epoch);
    await tester.pumpAndSettle();
    expect(find.byType(EmergencyPage), findsOneWidget);
    expect(find.text('Baca dulu sebelum menyalakan.'), findsOneWidget);
    expect(posts, isEmpty);
  });

  testWidgets('admin false report requires an explicit separate misuse choice', (tester) async {
    final sent = <RequestOptions>[];
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') {
          sent.add(o);
          return jsonBody(200, {
            'data': {..._report, 'status': 'palsu'},
          });
        }
        return jsonBody(200, {'data': _report});
      }),
      page: const EmergencyDetailPage(id: _id),
      admin: true,
    );
    await tester.tap(find.byType(DropdownButtonFormField<String>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Laporan palsu').last);
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Salah ketuk sudah dikonfirmasi');
    await _tap(tester, 'Simpan penanganan');
    expect(sent, hasLength(1));
    expect((sent.single.data as Map)['penyalahgunaan'], false);
    expect((sent.single.data as Map)['status'], 'palsu');
    expect(find.text('Simpan penanganan'), findsNothing);
  });

  testWidgets('admin settings unknown outcome retains the same version and key on retry', (tester) async {
    final sent = <RequestOptions>[];
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') {
          sent.add(o);
          throw DioException.receiveTimeout(timeout: const Duration(seconds: 1), requestOptions: o);
        }
        return jsonBody(200, {'data': _status});
      }),
      page: const Scaffold(body: EmergencyAdminSettings()),
      admin: true,
    );
    await _tap(tester, 'Nonaktifkan tombol panik RT');
    await _tap(tester, 'Nonaktifkan tombol panik RT');
    expect(sent, hasLength(2));
    expect(sent[0].headers['Idempotency-Key'], sent[1].headers['Idempotency-Key']);
    expect(sent[0].data, {'versi': 1, 'aktif': false});
    expect(sent[1].data, sent[0].data);
  });

  test('M19 privacy is explicitly recognized and discloses emergency identity without changing M18 text', () {
    expect(emergencyDeliveryText('gagal'), contains('mengalami kegagalan'));
    expect(emergencyDeliveryText('provider_menerima'), contains('belum membuktikan'));
    expect(emergencyDeliveryText(null), contains('belum diketahui'));
    expect(privacyVersionFor('2026-10-27'), panicPrivacyTextVersion);
    expect(privacyVersionFor('2099-01-01'), isNull);
    final old = privacyContentFor(privacyTextVersion).toString();
    final latest = privacyContentFor(panicPrivacyTextVersion).toString();
    expect(old, isNot(contains('Khusus tombol panik')));
    expect(latest, contains('nama pelapor, rumah, dan jenis darurat'));
    expect(latest, contains('Tidak ada pengambilan lokasi GPS'));
    expect(PushTap.fromData({'ref_type': 'kejadian_darurat', 'ref_id': _id}).refId, _id);
    expect(PushTap.fromData({'ref_type': 'kejadian_darurat', 'ref_id': 'invalid'}).refId, isNull);
  });

  testWidgets('three steps and both cancel paths never send before explicit final confirmation', (tester) async {
    final sent = <RequestOptions>[];
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') {
          sent.add(o);
          return jsonBody(201, {'data': _report});
        }
        return jsonBody(200, {'data': o.uri.path.endsWith('/status') ? _status : _report});
      }),
    );
    expect(sent, isEmpty);
    expect(find.text('Baca dulu sebelum menyalakan.'), findsOneWidget);
    await _tap(tester, 'Batal');
    expect(sent, isEmpty);
    await _arm(tester);
    expect(sent, hasLength(1));
    expect((sent.single.data as Map)['konfirmasi'], true);
    expect((sent.single.data as Map).containsKey('jenis'), isFalse);
    expect(find.text('Alarm darurat menyala'), findsWidgets);
    expect(find.textContaining('Pelapor sintetis'), findsOneWidget);
    expect(find.text('Simpan penanganan'), findsNothing);
  });

  testWidgets('unknown outcome retry retains the exact body and idempotency key', (tester) async {
    final sent = <RequestOptions>[];
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') {
          sent.add(o);
          if (sent.length == 1) throw DioException.receiveTimeout(timeout: const Duration(seconds: 1), requestOptions: o);
          return jsonBody(201, {'data': _report});
        }
        return jsonBody(200, {'data': o.uri.path.endsWith('/status') ? _status : _report});
      }),
    );
    await _arm(tester);
    expect(find.text('Coba kirim ulang'), findsOneWidget);
    await _tap(tester, 'Coba kirim ulang');
    expect(sent, hasLength(2));
    expect(sent[0].headers['Idempotency-Key'], sent[1].headers['Idempotency-Key']);
    expect(sent[0].data, sent[1].data);
  });

  testWidgets('old account delayed submit response never opens emergency detail after session changes', (tester) async {
    final gate = Completer<ResponseBody>();
    final container = await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') return gate.future;
        return jsonBody(200, {'data': _status});
      }),
    );
    await _arm(tester);
    (container.read(sessionProvider.notifier) as _Session).replace(
      SessionState(
        loading: false,
        token: 'new',
        akun: Akun(akunJson(id: 'other')),
      ),
    );
    await tester.pump();
    gate.complete(jsonBody(201, {'data': _report}));
    await tester.pumpAndSettle();
    expect(find.textContaining('Sesi berubah'), findsOneWidget);
    expect(find.text('Laporan darurat'), findsNothing);
  });

  testWidgets('uji emulator 1027: status pengiriman hanya untuk pelapor, bukan penerima alarm', (tester) async {
    for (final mine in [false, true]) {
      await _pump(
        tester,
        FakeAdapter(
          (o) => jsonBody(200, {
            'data': {..._report, 'pemberitahuan': 'provider_menerima', 'milik_saya': mine},
          }),
        ),
        page: const EmergencyDetailPage(id: _id),
      );
      expect(find.textContaining('Layanan notifikasi menerima'), mine ? findsOneWidget : findsNothing);
      await tester.pumpWidget(const SizedBox.shrink());
    }
  });

  testWidgets('uji emulator 1027: detail darurat yang menyala memperbarui status sendiri', (tester) async {
    var status = 'aktif';
    final adapter = FakeAdapter(
      (o) => jsonBody(200, {
        'data': {..._report, 'status': status},
      }),
    );
    await _pump(tester, adapter, page: const EmergencyDetailPage(id: _id));
    expect(find.textContaining('Status: Menyala'), findsOneWidget);
    status = 'selesai';
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();
    expect(find.textContaining('Status: Selesai'), findsOneWidget);
    final gets = adapter.requests.length;
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();
    expect(adapter.requests.length, gets, reason: 'kejadian selesai tidak dimuat ulang terus');
  });

  testWidgets('uji emulator 1027: hitungan penyalahgunaan tampil di menu Tombol Panik', (tester) async {
    await _pump(
      tester,
      FakeAdapter(
        (o) => jsonBody(200, {
          'data': {..._status, 'penyalahgunaan': 1},
        }),
      ),
    );
    expect(find.textContaining('1 dari 3'), findsOneWidget);
  });

  testWidgets('disabled account and disabled feature cannot enter activation', (tester) async {
    await _pump(
      tester,
      FakeAdapter(
        (o) => jsonBody(200, {
          'data': {..._status, 'penyalahgunaan': 3},
        }),
      ),
    );
    expect(find.byKey(const ValueKey('geser-aktifkan')), findsNothing);
    expect(find.textContaining('tiga penyalahgunaan'), findsOneWidget);
  });

  testWidgets('compact screen with large text renders terms and confirmation without overflow', (tester) async {
    useCompactScreen(tester, width: 320, height: 800, textScale: 2);
    await _pump(tester, FakeAdapter((o) => jsonBody(200, {'data': _status})));
    await _unlock(tester);
    expect(tester.takeException(), isNull);
    expect(find.byKey(const ValueKey('geser-aktifkan')), findsOneWidget);
  });

  testWidgets('jenis darurat tidak dikirim saat geser dan dapat dilengkapi di layar alarm', (tester) async {
    final sent = <RequestOptions>[];
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') {
          sent.add(o);
          if (o.uri.path.endsWith('/jenis')) {
            return jsonBody(200, {
              'data': {..._report, 'jenis': 'kemalingan', 'versi': 2},
            });
          }
          return jsonBody(201, {
            'data': {..._report, 'jenis': null},
          });
        }
        return jsonBody(200, {
          'data': o.uri.path.endsWith('/status') ? _status : {..._report, 'jenis': null},
        });
      }),
    );
    await _arm(tester);
    expect(sent, hasLength(1));
    expect((sent.single.data as Map).containsKey('jenis'), isFalse);
    await _tap(tester, 'Kemalingan');
    expect(sent, hasLength(2));
    expect(sent.last.uri.path, endsWith('/emergencies/$_id/jenis'));
    expect((sent.last.data as Map)['jenis'], 'kemalingan');
  });

  testWidgets('pengurus membatalkan penanda penyalahgunaan yang keliru dengan alasan (audit M19)', (tester) async {
    final sent = <RequestOptions>[];
    final misuse = {..._report, 'status': 'palsu', 'penyalahgunaan': true, 'penyalahgunaan_dibatalkan': false, 'catatan_pengurus': 'Sintetis'};
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') {
          sent.add(o);
          return jsonBody(200, {
            'data': {...misuse, 'penyalahgunaan_dibatalkan': true},
          });
        }
        return jsonBody(200, {'data': misuse});
      }),
      page: const EmergencyDetailPage(id: _id),
      admin: true,
    );
    await _tap(tester, 'Batalkan penanda penyalahgunaan');
    expect(sent, isEmpty);
    expect(find.text('Isi alasan pembatalan, minimal lima karakter.'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('alasan-batal-penyalahgunaan')), 'Laporan ternyata benar');
    await _tap(tester, 'Batalkan penanda penyalahgunaan');
    expect(sent, hasLength(1));
    expect(sent.single.uri.path, endsWith('/admin/emergencies/$_id/misuse-correction'));
    expect((sent.single.data as Map)['alasan'], 'Laporan ternyata benar');
    expect(find.text('Penanda penyalahgunaan sudah dibatalkan; tidak dihitung pada akun pelapor.'), findsOneWidget);
    expect(find.text('Batalkan penanda penyalahgunaan'), findsNothing);
  });
}
