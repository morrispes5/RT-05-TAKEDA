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
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';
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

Future<void> _confirm(WidgetTester tester) async {
  await _tap(tester, 'Mulai laporan darurat');
  await _chooseKind(tester, 'Kebakaran');
  await _tap(tester, 'Saya memahami ketentuan dan penyebaran identitas laporan darurat.');
  await _tap(tester, 'Lanjut ke konfirmasi');
}

/// Audit M19: jenis darurat tidak punya pilihan bawaan; warga memilih sendiri.
Future<void> _chooseKind(WidgetTester tester, String label) async {
  final field = find.byKey(const ValueKey('jenis-darurat'));
  await tester.ensureVisible(field);
  await tester.pump();
  await tester.tap(field);
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
  await tester.tap(find.text(label).last);
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
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
      page: Scaffold(body: AdminHomePage(goTab: (_) {})),
      admin: true,
      settle: false,
    );
    expect(find.text('Tombol panik'), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
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
      page: Scaffold(body: AdminHomePage(goTab: (_) {})),
      admin: true,
    );
    expect(find.text('Tombol panik'), findsOneWidget);
    await _tap(tester, 'Tombol panik');
    expect(find.text('Mulai laporan darurat'), findsOneWidget);
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
    expect(find.text('Mulai laporan darurat'), findsOneWidget);
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
    await _tap(tester, 'Mulai laporan darurat');
    expect(sent, isEmpty);
    await _tap(tester, 'Batal');
    await _confirm(tester);
    expect(sent, isEmpty);
    await _tap(tester, 'Batal');
    expect(sent, isEmpty);
    await _confirm(tester);
    await _tap(tester, 'Ya, kirim laporan');
    expect(sent, hasLength(1));
    expect((sent.single.data as Map)['konfirmasi'], true);
    expect(find.text('Laporan darurat'), findsOneWidget);
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
    await _confirm(tester);
    await _tap(tester, 'Ya, kirim laporan');
    expect(find.text('Batal'), findsNothing);
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
    await _confirm(tester);
    await _tap(tester, 'Ya, kirim laporan');
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

  testWidgets('disabled account and disabled feature cannot enter activation', (tester) async {
    await _pump(
      tester,
      FakeAdapter(
        (o) => jsonBody(200, {
          'data': {..._status, 'penyalahgunaan': 3},
        }),
      ),
    );
    expect(find.text('Mulai laporan darurat'), findsNothing);
    expect(find.textContaining('tiga penyalahgunaan'), findsOneWidget);
  });

  testWidgets('compact screen with large text renders terms and confirmation without overflow', (tester) async {
    useCompactScreen(tester, width: 320, height: 800, textScale: 2);
    await _pump(tester, FakeAdapter((o) => jsonBody(200, {'data': _status})));
    await _confirm(tester);
    expect(tester.takeException(), isNull);
    expect(find.text('Kirim laporan darurat ini?'), findsOneWidget);
  });

  testWidgets('jenis darurat wajib dipilih sendiri sebelum lanjut ke konfirmasi (audit M19)', (tester) async {
    final sent = <RequestOptions>[];
    await _pump(
      tester,
      FakeAdapter((o) {
        if (o.method == 'POST') sent.add(o);
        return jsonBody(200, {'data': o.uri.path.endsWith('/status') ? _status : _report});
      }),
    );
    await _tap(tester, 'Mulai laporan darurat');
    await _tap(tester, 'Saya memahami ketentuan dan penyebaran identitas laporan darurat.');
    final next = find.widgetWithText(FilledButton, 'Lanjut ke konfirmasi');
    expect(tester.widget<FilledButton>(next).onPressed, isNull);
    await _chooseKind(tester, 'Kemalingan');
    expect(tester.widget<FilledButton>(next).onPressed, isNotNull);
    await _tap(tester, 'Lanjut ke konfirmasi');
    await _tap(tester, 'Ya, kirim laporan');
    expect(sent, hasLength(1));
    expect((sent.single.data as Map)['jenis'], 'kemalingan');
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
