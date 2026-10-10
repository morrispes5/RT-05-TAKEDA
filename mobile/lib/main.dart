import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'app/config.dart';
import 'app/theme.dart';
import 'core/api.dart';
import 'core/models.dart';
import 'core/session.dart';
import 'core/widgets.dart';
import 'features/auth/auth_pages.dart';
import 'features/pengurus/pengurus_pages.dart';
import 'features/warga/providers.dart';
import 'features/warga/warga_pages.dart';

void main() => runApp(const ProviderScope(child: Rt05App()));

class Rt05App extends ConsumerStatefulWidget {
  const Rt05App({super.key});
  @override
  ConsumerState<Rt05App> createState() => _Rt05AppState();
}

class _Rt05AppState extends ConsumerState<Rt05App> {
  @override
  void initState() {
    super.initState();
    // Pulihkan sesi: token dari secure storage divalidasi ulang ke /auth/me (status & peran dari server).
    Future.microtask(() => ref.read(sessionProvider.notifier).restore((token) async {
          final me = await ref.read(apiProvider).get('/auth/me');
          return Akun(Map<String, dynamic>.from(me as Map));
        }));
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    final Widget home = session.loading
        ? const _Splash()
        : !session.signedIn
            ? const LoginPage()
            : session.pending
                ? const PendingPage()
                : HomeShell(key: ValueKey(session.akun!.id));
    return MaterialApp(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      home: home,
    );
  }
}

class _Splash extends StatelessWidget {
  const _Splash();
  @override
  Widget build(BuildContext context) => const Scaffold(
        backgroundColor: Brand.navy,
        body: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            GapuraMark(size: 88, light: true),
            SizedBox(height: 16),
            Text('RT05 TAKEDA', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: 1)),
            SizedBox(height: 4),
            Text('RT 05 Taman Kedaung', style: TextStyle(color: Brand.yellow)),
          ]),
        ),
      );
}

/// Satu aplikasi, navigasi sesuai peran dari API (docs/MOBILE.md):
/// warga: Beranda, Layanan, Agenda, Iuran, Akun — pengurus: Ringkasan, Warga, Layanan, Keuangan, Lainnya.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});
  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final pengurus = ref.watch(sessionProvider).pengurus;
    void go(int i) => setState(() => _index = i);
    final tabs = pengurus
        ? <(String, IconData, Widget)>[
            ('Ringkasan', Icons.dashboard_outlined, AdminHomePage(goTab: go)),
            ('Warga', Icons.groups_outlined, const ResidentsAdminPage()),
            ('Layanan', Icons.support_agent_outlined, const ServicesPage(admin: true)),
            ('Keuangan', Icons.account_balance_wallet_outlined, const FinanceAdminPage()),
            ('Lainnya', Icons.more_horiz, const _MoreTab()),
          ]
        : <(String, IconData, Widget)>[
            ('Beranda', Icons.home_outlined, WargaHomePage(goTab: go)),
            ('Layanan', Icons.support_agent_outlined, const ServicesPage()),
            ('Agenda', Icons.event_outlined, const AgendaPage()),
            ('Iuran', Icons.payments_outlined, const DuesPage()),
            ('Akun', Icons.person_outline, const AccountPage()),
          ];
    final unread = ref.watch(notificationsProvider).value?['meta']?['belum_dibaca'] as int? ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: Row(children: [
          const GapuraMark(size: 28, light: true),
          const SizedBox(width: 10),
          Text(tabs[_index].$1),
        ]),
        actions: [
          IconButton(
            tooltip: 'Notifikasi',
            onPressed: () async {
              await Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationsPage()));
              ref.invalidate(notificationsProvider);
            },
            icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_outlined)),
          ),
        ],
      ),
      body: IndexedStack(index: _index, children: [for (final t in tabs) t.$3]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: go,
        destinations: [for (final t in tabs) NavigationDestination(icon: Icon(t.$2), label: t.$1)],
      ),
    );
  }
}

class _MoreTab extends StatelessWidget {
  const _MoreTab();
  @override
  Widget build(BuildContext context) => const DefaultTabController(
        length: 2,
        child: Column(children: [
          Material(
            color: Colors.white,
            child: TabBar(labelColor: Brand.navy, indicatorColor: Brand.yellow, indicatorWeight: 3, tabs: [Tab(text: 'Kelola'), Tab(text: 'Akun')]),
          ),
          Expanded(child: TabBarView(children: [MoreAdminPage(), AccountPage()])),
        ]),
      );
}

/// Dipakai widget test untuk memastikan error API tampil sebagai pesan, bukan crash.
String describeError(Object e) => e is ApiException ? e.display : 'Terjadi kesalahan. Coba lagi.';
