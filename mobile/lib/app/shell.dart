import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/app_update_platform.dart';
import '../core/session.dart';
import '../core/widgets.dart';
import '../features/pengurus/pengurus_pages.dart';
import '../features/warga/app_about.dart';
import '../features/warga/emergencies.dart';
import '../features/warga/providers.dart';
import '../features/warga/warga_pages.dart';
import 'theme.dart';

/// Satu aplikasi, navigasi sesuai peran dari API (docs/MOBILE.md):
/// warga: Beranda, Layanan, Agenda, Iuran, Akun — pengurus: Ringkasan, Warga, Layanan, Keuangan, Lainnya.
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({super.key});
  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _index = 0;

  // Kembali ke aplikasi: muat ulang lencana inbox (push bisa ditolak atau belum tersedia).
  late final AppLifecycleListener _lifecycle = AppLifecycleListener(onResume: () => ref.invalidate(notificationsProvider));

  @override
  void initState() {
    super.initState();
    _lifecycle;
  }

  @override
  void dispose() {
    _lifecycle.dispose();
    super.dispose();
  }

  // ADR46: tawarkan pembaruan sekali per aplikasi dibuka, hanya saat shell tampil paling atas
  // (tidak menutupi halaman darurat/notifikasi yang dibuka dari push atau shortcut).
  void _offerUpdate(AppRelease? release) {
    final offer = ref.read(updateOfferProvider);
    if (offer.shown || release == null || release.berkas.isEmpty || !isNewerRelease(release, ref.read(installedBuildProvider))) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || offer.shown || ModalRoute.of(context)?.isCurrent != true) return;
      offer.shown = true;
      showAppUpdateDialog(context, release);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (ref.read(appUpdatePlatformProvider).supported) ref.listen(latestReleaseProvider, (_, next) => _offerUpdate(next.value));
    final session = ref.watch(sessionProvider);
    // Sesi berakhir/keluar: router berpindah ke login pada frame berikutnya; jangan bangun tab
    // yang membaca data akun pada frame peralihan ini.
    if (!session.signedIn || session.pending) return const Scaffold(body: SizedBox.shrink());
    final pengurus = session.pengurus;
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

    // Tombol Kembali (UAT M15 20 Okt): dari tab mana pun kembali ke tab pertama (dashboard); dari dashboard
    // minta konfirmasi sebelum menutup aplikasi. Halaman yang di-push berada di atas shell dan tetap menutup lebih dulu.
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        if (_index != 0) {
          setState(() => _index = 0);
          return;
        }
        final leave = await confirm(
          context,
          title: 'Keluar dari aplikasi?',
          message: 'Aplikasi ditutup. Akunmu tetap masuk dan bisa dibuka lagi kapan saja.',
          yes: 'Keluar aplikasi',
        );
        if (leave) await SystemNavigator.pop();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Row(
            children: [
              const GapuraMark(size: 28, light: true),
              const SizedBox(width: 10),
              Flexible(child: Text(tabs[_index].$1, overflow: TextOverflow.ellipsis)),
            ],
          ),
          actions: [
            IconButton(
              tooltip: 'Notifikasi',
              onPressed: () async {
                await context.push('/notifications');
                ref.invalidate(notificationsProvider);
              },
              icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: const Icon(Icons.notifications_outlined)),
            ),
          ],
        ),
        body: IndexedStack(index: _index, children: [for (final t in tabs) t.$3]),
        floatingActionButton: _index == 0 ? const EmergencyFab() : null,
        // Label navigasi dibatasi 1,3× agar lima tab tetap muat di layar sempit; isi halaman tetap mengikuti skala sistem.
        bottomNavigationBar: MediaQuery.withClampedTextScaling(
          maxScaleFactor: 1.3,
          child: NavigationBar(
            selectedIndex: _index,
            onDestinationSelected: go,
            destinations: [for (final t in tabs) NavigationDestination(icon: Icon(t.$2), label: t.$1)],
          ),
        ),
      ),
    );
  }
}

class _MoreTab extends StatelessWidget {
  const _MoreTab();
  @override
  Widget build(BuildContext context) => const DefaultTabController(
    length: 2,
    child: Column(
      children: [
        Material(
          color: Colors.white,
          child: TabBar(
            labelColor: Brand.navy,
            indicatorColor: Brand.yellow,
            indicatorWeight: 3,
            tabs: [
              Tab(text: 'Kelola'),
              Tab(text: 'Akun'),
            ],
          ),
        ),
        Expanded(child: TabBarView(children: [MoreAdminPage(), AccountPage()])),
      ],
    ),
  );
}
