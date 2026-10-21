import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app/config.dart';
import 'app/router.dart';
import 'app/startup.dart';
import 'app/theme.dart';
import 'core/api.dart';
import 'core/push.dart';
import 'core/session.dart';
import 'features/warga/notification_center.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final AppConfig config;
  try {
    config = AppConfig.fromEnvironment();
  } on InvalidConfig catch (e) {
    runApp(ConfigErrorApp(problems: e.problems));
    return;
  }
  // Tanpa konfigurasi Firebase (dev/CI) → NoPushClient: aplikasi tetap jalan dengan inbox saja.
  final push = await FirebasePushClient.create();
  runApp(ProviderScope(overrides: [appConfigProvider.overrideWithValue(config), pushClientProvider.overrideWithValue(push)], child: const Rt05App()));
}

const appLocale = Locale('id', 'ID');

class Rt05App extends ConsumerStatefulWidget {
  const Rt05App({super.key});
  @override
  ConsumerState<Rt05App> createState() => _Rt05AppState();
}

class _Rt05AppState extends ConsumerState<Rt05App> {
  @override
  void initState() {
    super.initState();
    // Health /health/live + pemulihan sesi (token divalidasi ulang ke /auth/me; status & peran dari server).
    Future.microtask(() => bootstrap(ref));
    _initPush();
  }

  final _subs = <StreamSubscription<Object?>>[];
  PushTap? _waitingTap;

  /// Push M10: foreground → alert info; ketukan (latar belakang/tertutup) → inbox lalu detail via API.
  /// Ketukan sebelum sesi pulih disimpan dan dibuka setelah sesi aktif tervalidasi server.
  void _initPush() {
    final client = ref.read(pushClientProvider);
    if (!client.available) return;
    void open(PushTap tap) {
      final s = ref.read(sessionProvider);
      if (!s.signedIn || s.pending) {
        _waitingTap = tap;
        return;
      }
      handlePushTap(ref.read(routerProvider), ref, tap);
    }

    _subs
      ..add(client.foreground.listen((t) => showForegroundPush(ref.read(routerProvider), ref, t)))
      ..add(client.opened.listen(open))
      ..add(
        client.tokenRefresh.listen((t) {
          if (ref.read(sessionProvider).signedIn) ref.read(pushServiceProvider).sendToken(t);
        }),
      );
    client.initialTap().then((t) {
      if (t != null) open(t);
    });
    ref.listenManual(sessionProvider, (prev, next) {
      if (!next.signedIn) return;
      // ADR35: izin notifikasi baru diminta setelah Kebijakan Privasi disetujui, tidak menimpa halaman kebijakan.
      if (next.needsPrivacy) return;
      final consented = prev?.needsPrivacy == true;
      if (consented || prev?.token != next.token || prev?.akun?.id != next.akun?.id) ref.read(pushServiceProvider).register();
      final waiting = _waitingTap;
      if (!next.pending && waiting != null) {
        _waitingTap = null;
        handlePushTap(ref.read(routerProvider), ref, waiting);
      }
    }, fireImmediately: true);
  }

  @override
  void dispose() {
    for (final s in _subs) {
      s.cancel();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final flavor = ref.watch(appConfigProvider).flavor;
    return MaterialApp.router(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      locale: appLocale,
      supportedLocales: const [appLocale],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => FlavorBanner(flavor: flavor, child: child ?? const SizedBox.shrink()),
    );
  }
}

/// Dipakai widget test untuk memastikan error API tampil sebagai pesan, bukan crash.
String describeError(Object e) => e is ApiException ? e.display : 'Terjadi kesalahan. Coba lagi.';
