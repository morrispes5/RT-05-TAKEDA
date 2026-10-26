import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/auth/account_access.dart';
import 'package:rt05_takeda/features/warga/activity_proposals.dart';
import 'package:url_launcher_platform_interface/url_launcher_platform_interface.dart';

import 'support/fakes.dart';

class RecordingLauncher extends UrlLauncherPlatform {
  @override
  get linkDelegate => null;
  final launches = <String>[];
  final launchSessionIds = <String?>[];
  String? Function()? accountAtLaunch;
  @override
  Future<bool> launchUrl(String url, LaunchOptions options) async {
    launches.add(url);
    launchSessionIds.add(accountAtLaunch?.call());
    return true;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  for (final action in ['active', 'pop', 'logout', 'same-token', 'privacy']) {
    testWidgets('private document handoff: $action', (tester) async {
      final linkGate = Completer<ResponseBody>();
      final launcher = RecordingLauncher();
      final previousLauncher = UrlLauncherPlatform.instance;
      UrlLauncherPlatform.instance = launcher;
      addTearDown(() => UrlLauncherPlatform.instance = previousLauncher);
      final adapter = FakeAdapter((o) {
        if (o.path.endsWith('/documents/d-1/link')) return linkGate.future;
        if (o.path.endsWith('/auth/logout')) return ResponseBody.fromString('', 204);
        if (o.path.endsWith('/history')) return jsonBody(200, {'data': []});
        return jsonBody(200, {
          'data': {
            'id': 'u-1',
            'versi': 1,
            'status': 'diajukan',
            'milik_saya': true,
            'nama': 'Proposal synthetic private',
            'jenis': 'olahraga',
            'lokasi': 'Synthetic location',
            'deskripsi': 'Synthetic private description',
            'mulai_at': '2026-11-01T01:00:00Z',
            'selesai_at': '2026-11-01T02:00:00Z',
            'dokumen': [
              {'id': 'd-1', 'urutan': 1, 'mime': 'application/pdf', 'bytes': 100},
            ],
          },
        });
      });
      final container = ProviderContainer(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage()),
          httpAdapterProvider.overrideWithValue(adapter),
        ],
      );
      addTearDown(container.dispose);
      launcher.accountAtLaunch = () => container.read(sessionProvider).akun?.id;
      await container.read(sessionProvider.notifier).signIn('synthetic-A', Akun(akunJson(id: 'account-A')));
      final nav = GlobalKey<NavigatorState>();
      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: MaterialApp(
            navigatorKey: nav,
            home: Consumer(
              builder: (context, ref, child) => Scaffold(
                body: Column(
                  children: [
                    FilledButton(
                      onPressed: () =>
                          Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const ActivityProposalDetailPage(id: 'u-1', admin: false))),
                      child: const Text('Open detail'),
                    ),
                    FilledButton(
                      onPressed: () => revokeServerSession(ref, path: '/auth/logout'),
                      child: const Text('Log out'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open detail'));
      await tester.pumpAndSettle();
      final detailContext = tester.element(find.byType(ActivityProposalDetailPage));
      await tester.scrollUntilVisible(find.text('Dokumen 1 (PDF)'), 200);
      await tester.tap(find.text('Dokumen 1 (PDF)'));
      await tester.pump();
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 10));
      }
      expect(adapter.requests.where((o) => o.path.endsWith('/documents/d-1/link')).length, 1);
      final linkRequest = adapter.requests.firstWhere((o) => o.path.endsWith('/documents/d-1/link'));
      expect(linkRequest.headers['Authorization'], 'Bearer synthetic-A');
      expect(launcher.launches, isEmpty);
      if (action == 'pop' || action == 'logout') {
        nav.currentState!.pop();
        await tester.pumpAndSettle();
        expect(detailContext.mounted, isFalse);
      }
      if (action == 'logout') {
        await tester.tap(find.text('Log out'));
        await tester.pumpAndSettle();
        expect(container.read(sessionProvider).signedIn, isFalse);
        expect(adapter.requests.any((o) => o.path.endsWith('/auth/logout')), isTrue);
        await container.read(sessionProvider.notifier).signIn('synthetic-B', Akun(akunJson(id: 'account-B')));
        await tester.pumpAndSettle();
      }
      if (action == 'same-token') {
        await container.read(sessionProvider.notifier).signIn('synthetic-A', Akun(akunJson(id: 'account-A')));
      }
      if (action == 'privacy') {
        final controller = container.read(sessionProvider.notifier);
        controller.updateAkun(Akun({...akunJson(id: 'account-A'), 'privasi_perlu_setuju': true}), epoch: controller.epoch);
      }
      linkGate.complete(
        jsonBody(200, {
          'data': {'url': '/api/v1/activity-proposals/documents/d-1/download?expires=1234567890&signature=synthetic'},
        }),
      );
      await tester.pumpAndSettle();
      expect(
        launcher.launches,
        action == 'active' ? ['https://api.example.test/api/v1/activity-proposals/documents/d-1/download?expires=1234567890&signature=synthetic'] : isEmpty,
      );
      expect(launcher.launchSessionIds, action == 'active' ? ['account-A'] : isEmpty);
      expect(tester.takeException(), isNull);
    });
  }
}
