import 'dart:async';

import 'package:dio/dio.dart' show ResponseBody;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/router.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/push.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/auth/privacy_policy.dart';
import 'package:rt05_takeda/features/warga/dues_proof.dart';
import 'package:rt05_takeda/features/warga/notification_center.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';
import 'package:rt05_takeda/main.dart';

import 'support/fakes.dart';

class _UnconsentedSession extends SessionController {
  @override
  SessionState build() =>
      SessionState(loading: false, token: 'synthetic-token', akun: Akun({...akunJson(), 'privasi_perlu_setuju': true, 'privasi_versi': '2026-10-26'}));
}

class _ReadyHealth extends HealthController {
  @override
  HealthState build() => const HealthState.ok(HealthOk());
}

class _ActiveSession extends SessionController {
  @override
  SessionState build() => SessionState(loading: false, token: 'synthetic-token', akun: Akun(akunJson()));
}

class _InitialPush extends NoPushClient {
  @override
  bool get available => true;
  @override
  Future<PushPermission> permission() async => PushPermission.denied;
  @override
  Future<PushTap?> initialTap() async => _tap;
}

class _PendingPush extends NoPushClient {
  final refresh = StreamController<String>();
  @override
  bool get available => true;
  @override
  Future<PushPermission> permission() async => PushPermission.granted;
  @override
  Future<String?> token() async => 'synthetic-fcm-first';
  @override
  Stream<String> get tokenRefresh => refresh.stream;
}

class _DeviceId implements DeviceIdStore {
  @override
  Future<String> read() async => 'android-qa-synthetic';
}

const _agendaId = '00000000-0000-4000-8000-000000000001';
const _notificationId = '00000000-0000-4000-8000-000000000002';
const _tap = PushTap(notifikasiId: _notificationId, refType: 'agenda', refId: _agendaId);

Future<WidgetRef> _router(WidgetTester tester, FakeHandler handler) async {
  late WidgetRef result;
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        sessionProvider.overrideWith(_ActiveSession.new),
        healthProvider.overrideWith(_ReadyHealth.new),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('synthetic-token')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: Consumer(
        builder: (_, ref, _) {
          result = ref;
          return MaterialApp.router(routerConfig: ref.watch(routerProvider));
        },
      ),
    ),
  );
  await tester.pumpAndSettle();
  return result;
}

Future<void> _frames(WidgetTester tester) async {
  for (var i = 0; i < 12; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _change(WidgetRef ref, String action) async {
  final controller = ref.read(sessionProvider.notifier);
  if (action == 'logout') {
    await controller.signOut();
    await controller.signIn('synthetic-B', Akun(akunJson(id: 'account-B')));
  } else if (action == 'same-token') {
    await controller.signIn('synthetic-token', Akun(akunJson()));
  } else if (action == 'privacy') {
    controller.updateAkun(Akun({...akunJson(), 'privasi_perlu_setuju': true, 'privasi_versi': '2026-10-26'}), epoch: controller.epoch);
  }
}

void main() {
  testWidgets('akun menunggu tetap mendaftarkan dan memperbarui push persetujuan', (tester) async {
    final client = _PendingPush();
    addTearDown(client.refresh.close);
    final tokens = <String>[];
    final base = apiHandler(
      me: () => akunJson(status: 'menunggu', jenis: 'tambahan'),
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('synthetic-token')),
          deviceIdStoreProvider.overrideWithValue(_DeviceId()),
          pushClientProvider.overrideWithValue(client),
          httpAdapterProvider.overrideWithValue(
            FakeAdapter((o) {
              if (o.method == 'PUT' && o.uri.path.endsWith('/devices/android-qa-synthetic')) {
                tokens.add('${(o.data as Map)['fcm_token']}');
                return jsonBody(200, {'data': {}});
              }
              return base(o);
            }),
          ),
        ],
        child: const Rt05App(),
      ),
    );
    await tester.pumpAndSettle();
    expect(tokens, ['synthetic-fcm-first']);
    final container = ProviderScope.containerOf(tester.element(find.byType(Rt05App)));
    expect(container.read(sessionProvider).pending, isTrue);
    expect(container.read(sessionProvider).readyForServices, isFalse);
    client.refresh.add('synthetic-fcm-second');
    await _frames(tester);
    expect(tokens, ['synthetic-fcm-first', 'synthetic-fcm-second']);
    expect(find.byType(AgendaByIdPage), findsNothing);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
  });
  for (final action in ['active', 'logout']) {
    testWidgets('inbox mark-read tertunda: $action', (tester) async {
      final gate = Completer<ResponseBody>();
      var reads = 0;
      final base = apiHandler(me: akunJson);
      final ref = await _router(tester, (o) {
        if (o.uri.path.endsWith('/read')) {
          reads++;
          return gate.future;
        }
        if (o.uri.path == '/api/v1/notifications') {
          return jsonBody(200, {
            'data': [
              {
                'id': _notificationId,
                'judul': 'Notifikasi QA sintetis',
                'isi': 'Agenda uji',
                'jenis': 'agenda',
                'created_at': '2026-10-26T00:00:00Z',
                'dibaca_at': null,
                'referensi': {'tipe': 'agenda', 'id': _agendaId},
              },
            ],
            'meta': {'current_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        return base(o);
      });
      ref.read(routerProvider).push('/notifications');
      await tester.pumpAndSettle();
      expect(find.byType(NotificationsPage), findsOneWidget);
      await tester.tap(find.text('Notifikasi QA sintetis'));
      await _frames(tester);
      expect(reads, 1);
      await _change(ref, action);
      gate.complete(jsonBody(200, {'data': {}}));
      await _frames(tester);
      expect(find.byType(AgendaByIdPage), action == 'active' ? findsOneWidget : findsNothing);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
    });
  }
  for (final action in ['active', 'logout', 'same-token', 'privacy']) {
    testWidgets('push mark-read tertunda: $action', (tester) async {
      final gate = Completer<ResponseBody>();
      var reads = 0;
      final base = apiHandler(me: akunJson);
      final ref = await _router(tester, (o) {
        if (o.uri.path.endsWith('/read')) {
          reads++;
          return gate.future;
        }
        return base(o);
      });
      final done = handlePushTap(ref.read(routerProvider), ref, _tap);
      await _frames(tester);
      expect(reads, 1);
      await _change(ref, action);
      gate.complete(jsonBody(200, {'data': {}}));
      await _frames(tester);
      await done;
      expect(find.byType(AgendaByIdPage), action == 'active' ? findsOneWidget : findsNothing);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
    });
  }

  for (final sameAccount in [true, false]) {
    testWidgets('push startup menunggu consent; akun sama=$sameAccount', (tester) async {
      var reads = 0;
      final me = {...akunJson(), 'privasi_perlu_setuju': true, 'privasi_versi': '2026-10-26'};
      final base = apiHandler(me: () => me);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
            tokenStorageProvider.overrideWithValue(MemoryTokenStorage('synthetic-token')),
            pushClientProvider.overrideWithValue(_InitialPush()),
            httpAdapterProvider.overrideWithValue(
              FakeAdapter((o) {
                if (o.uri.path.endsWith('/read')) {
                  reads++;
                  return jsonBody(200, {'data': {}});
                }
                return base(o);
              }),
            ),
          ],
          child: const Rt05App(),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.byType(PrivacyPolicyPage), findsOneWidget);
      expect(reads, 0);
      final container = ProviderScope.containerOf(tester.element(find.byType(Rt05App)));
      final controller = container.read(sessionProvider.notifier);
      if (sameAccount) {
        controller.updateAkun(Akun({...me, 'privasi_perlu_setuju': false}), epoch: controller.epoch);
      } else {
        await controller.signOut();
        await controller.signIn('synthetic-B', Akun(akunJson(id: 'account-B')));
      }
      await _frames(tester);
      await tester.pumpAndSettle();
      expect(reads, sameAccount ? 1 : 0);
      expect(find.byType(AgendaByIdPage), sameAccount ? findsOneWidget : findsNothing);
      await tester.pumpWidget(const SizedBox());
      await tester.pumpAndSettle();
    });
  }
  testWidgets('bukti cicilan menyarankan sisa tagihan', (tester) async {
    tester.view.physicalSize = const Size(1080, 4500);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final base = apiHandler(me: akunJson);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('synthetic-token')),
          httpAdapterProvider.overrideWithValue(
            FakeAdapter((o) {
              if (o.uri.path == '/api/v1/dues/my') {
                return jsonBody(200, {
                  'data': {
                    'tagihan': [
                      {
                        'id': 'qa-t-1',
                        'periode': '2026-10',
                        'nominal': 75000,
                        'terbayar': 50000,
                        'sisa': 25000,
                        'keringanan': 0,
                        'status': 'belum_bayar',
                        'status_label': 'Kurang bayar',
                      },
                    ],
                  },
                });
              }
              return base(o);
            }),
          ),
        ],
        child: const MaterialApp(home: DuesProofPage()),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('bulan-2026-10')));
    await tester.pumpAndSettle();
    final nominal = tester.widget<TextField>(find.byKey(const ValueKey('nominal-bukti'))).controller!.text;
    expect(nominal, '25000');
    expect(find.textContaining('Sisa'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('nominal-bukti')), '20000');
    await tester.tap(find.byKey(const ValueKey('bulan-2026-10')));
    await tester.pumpAndSettle();
    expect(tester.widget<TextField>(find.byKey(const ValueKey('nominal-bukti'))).controller!.text, '20000');
  });

  testWidgets('push tidak menandai dibaca atau membuka detail sebelum consent', (tester) async {
    late WidgetRef appRef;
    var reads = 0;
    final base = apiHandler(me: akunJson);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          sessionProvider.overrideWith(_UnconsentedSession.new),
          healthProvider.overrideWith(_ReadyHealth.new),
          httpAdapterProvider.overrideWithValue(
            FakeAdapter((o) {
              if (o.uri.path.endsWith('/read')) {
                reads++;
                return jsonBody(200, {'data': {}});
              }
              if (o.uri.path == '/api/v1/agendas/00000000-0000-4000-8000-000000000001') {
                return errorBody(404, 'NOT_FOUND', 'Tidak ditemukan.');
              }
              return base(o);
            }),
          ),
        ],
        child: Consumer(
          builder: (_, ref, _) {
            appRef = ref;
            return MaterialApp.router(routerConfig: ref.watch(routerProvider));
          },
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(appRef.read(sessionProvider).needsPrivacy, isTrue);
    expect(find.byType(PrivacyPolicyPage), findsOneWidget);
    final done = handlePushTap(
      appRef.read(routerProvider),
      appRef,
      const PushTap(notifikasiId: '00000000-0000-4000-8000-000000000002', refType: 'agenda', refId: '00000000-0000-4000-8000-000000000001'),
    );
    for (var i = 0; i < 12; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
    await done;
    await tester.pumpAndSettle();
    expect(reads, 0);
    expect(appRef.read(sessionProvider).needsPrivacy, isTrue);
    expect(find.byType(AgendaByIdPage), findsNothing);
    expect(find.byType(PrivacyPolicyPage), findsOneWidget);
    await tester.pumpWidget(const SizedBox());
    await tester.pumpAndSettle();
  });
}
