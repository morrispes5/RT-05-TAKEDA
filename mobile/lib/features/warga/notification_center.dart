import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/emergency_platform.dart';
import '../../core/models.dart';
import '../../core/push.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import 'activity_proposals.dart';
import 'emergencies.dart';
import 'providers.dart';
import 'warga_pages.dart';

/// Tautan dari inbox atau push (M10). Hanya jenis dari daftar tetap dan ID UUID yang dibuka;
/// detail dimuat ulang dari API, jadi tautan basi atau tidak berhak berakhir di pesan aman,
/// bukan data dari payload push (docs/MOBILE.md §push).
void openReference(BuildContext context, {required String? type, required String? id, required bool admin}) {
  if (type == null || !PushTap.isUuid(id)) return;
  final Widget? page = switch (type) {
    'agenda' => AgendaByIdPage(id: id!),
    'pengumuman' => AnnouncementByIdPage(id: id!),
    'pengaduan' => ReportDetailPage(aspirasi: false, id: id!, admin: admin),
    'aspirasi' => ReportDetailPage(aspirasi: true, id: id!, admin: admin),
    'usulan_kegiatan' => ActivityProposalDetailPage(id: id!, admin: admin),
    'kejadian_darurat' => EmergencyDetailPage(id: id!),
    'tagihan' => Scaffold(
      appBar: AppBar(title: const Text('Iuran')),
      body: const DuesPage(),
    ),
    _ => null,
  };
  if (page != null) Navigator.push(context, MaterialPageRoute(builder: (_) => page));
}

/// Ketukan push saat aplikasi di latar belakang atau tertutup: buka inbox, tandai dibaca, lalu detail.
Future<void> handlePushTap(GoRouter router, WidgetRef ref, PushTap tap) async {
  final session = ref.read(sessionProvider);
  final controller = ref.read(sessionProvider.notifier);
  final epoch = controller.epoch;
  final origin = ref.context;
  bool current() => origin.mounted && controller.isCurrentActive(session, epoch: epoch);
  if (!current()) return;
  if (tap.notifikasiId != null) {
    try {
      await ref.read(apiProvider).patch('/notifications/${tap.notifikasiId}/read');
    } catch (_) {
      // Notifikasi milik akun lain/terhapus: abaikan, detail tetap lewat policy API.
    }
  }
  if (!current()) return;
  ref.invalidate(notificationsProvider);
  router.go('/home');
  router.push('/notifications');
  await WidgetsBinding.instance.endOfFrame;
  if (!current()) return;
  final context = router.routerDelegate.navigatorKey.currentContext;
  if (context != null && context.mounted) openReference(context, type: tap.refType, id: tap.refId, admin: ref.read(sessionProvider).pengurus);
}

/// Push di depan layar: Android tidak menampilkan notifikasi sistem, jadi tampilkan alert info.
void showForegroundPush(GoRouter router, WidgetRef ref, PushTap tap) {
  if (!ref.read(sessionProvider).readyForServices) return;
  // ADR50: push alarm baru sudah berdering lewat penerima Android; jalur ini hanya untuk push alarm lama.
  if (tap.refType == 'kejadian_darurat' && PushTap.isUuid(tap.refId) && !tap.alarm) {
    ref.read(emergencyPlatformProvider).alarm(tap.refId!, tap.notifikasiId);
  }
  ref.invalidate(notificationsProvider);
  final context = router.routerDelegate.navigatorKey.currentContext;
  if (context == null || !context.mounted) return;
  if (tap.alarm) {
    showMessage(context, 'Ada laporan darurat warga. Buka notifikasi untuk melihat detail.', kind: AlertKind.warning, title: 'DARURAT di RT 05');
    return;
  }
  showMessage(context, tap.body ?? 'Ada notifikasi baru di kotak masuk.', kind: AlertKind.info, title: tap.title ?? 'Notifikasi baru');
}

Widget _unavailable(Object e, VoidCallback retry, String what) {
  if (e is ApiException && (e.status == 404 || e.status == 403)) {
    return EmptyState(
      icon: Icons.link_off,
      title: '$what tidak lagi tersedia',
      message: 'Mungkin sudah diarsipkan, dibatalkan, atau bukan untuk akun Anda. Riwayat notifikasi tetap tersimpan.',
    );
  }
  return ErrorState(message: e is ApiException ? e.display : 'Terjadi kesalahan. Coba lagi.', onRetry: retry);
}

/// Tautan basi (404/403) tidak diulang otomatis: pesan aman langsung tampil.
Duration? _noRetryOn4xx(int count, Object e) => e is ApiException && e.status >= 400 && e.status < 500 || count >= 2 ? null : const Duration(seconds: 1);

final agendaByIdProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, id) async => Json.from(await accountApi(ref).get('/agendas/$id') as Map),
  retry: _noRetryOn4xx,
);

final announcementByIdProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, id) async => Json.from(await accountApi(ref).get('/announcements/$id') as Map),
  retry: _noRetryOn4xx,
);

class AgendaByIdPage extends ConsumerWidget {
  const AgendaByIdPage({super.key, required this.id});
  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) => ref
      .watch(agendaByIdProvider(id))
      .when(
        data: (a) => AgendaDetailPage(a),
        loading: () => Scaffold(
          appBar: AppBar(title: const Text('Agenda')),
          body: const Center(child: CircularProgressIndicator()),
        ),
        error: (e, _) => Scaffold(
          appBar: AppBar(title: const Text('Agenda')),
          body: _unavailable(e, () => ref.invalidate(agendaByIdProvider(id)), 'Agenda'),
        ),
      );
}

class AnnouncementByIdPage extends ConsumerWidget {
  const AnnouncementByIdPage({super.key, required this.id});
  final String id;

  @override
  Widget build(BuildContext context, WidgetRef ref) => ref
      .watch(announcementByIdProvider(id))
      .when(
        data: (n) => AnnouncementPage(n),
        loading: () => Scaffold(
          appBar: AppBar(title: const Text('Pengumuman')),
          body: const Center(child: CircularProgressIndicator()),
        ),
        error: (e, _) => Scaffold(
          appBar: AppBar(title: const Text('Pengumuman')),
          body: _unavailable(e, () => ref.invalidate(announcementByIdProvider(id)), 'Pengumuman'),
        ),
      );
}

final notificationPreferencesProvider = FutureProvider.autoDispose<Json>(
  (ref) async => Json.from(await accountApi(ref).get('/notification-preferences') as Map),
);

final pushPermissionProvider = FutureProvider.autoDispose<PushPermission>((ref) => ref.read(pushClientProvider).permission());

/// Pengaturan notifikasi: izin push perangkat dan preferensi per jenis. Inbox tetap tersimpan
/// apa pun pilihannya (BUSINESS_RULES: preferensi hanya mematikan push nonkritis).
class NotificationSettingsPage extends ConsumerWidget {
  const NotificationSettingsPage({super.key});

  static const kinds = [
    ('pengumuman', 'Pengumuman RT', Icons.campaign_outlined),
    ('agenda', 'Agenda dan pengingat H-1', Icons.event),
    ('pengaduan', 'Status pengaduan saya', Icons.report_outlined),
    ('aspirasi', 'Status aspirasi saya', Icons.lightbulb_outline),
    ('iuran', 'Iuran (pengingat dan pembayaran tercatat)', Icons.payments_outlined),
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final prefs = ref.watch(notificationPreferencesProvider);
    final permission = ref.watch(pushPermissionProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Pengaturan notifikasi')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SectionCard(
            title: 'Notifikasi di HP ini',
            child: permission.when(
              loading: () => const LinearProgressIndicator(),
              error: (_, _) => const _PushStatus(PushPermission.unavailable),
              data: (p) => _PushStatus(p),
            ),
          ),
          // Uji HP M10: sebagian merek Android memblokir push setelah aplikasi digeser dari daftar terbaru.
          if (permission.value == PushPermission.granted) ...[
            const SizedBox(height: 16),
            const SectionCard(
              key: ValueKey('tips-baterai'),
              child: ExpansionTile(
                tilePadding: EdgeInsets.zero,
                leading: Icon(Icons.battery_alert_outlined, color: Brand.navy),
                title: Text('Agar notifikasi tidak terlewat'),
                childrenPadding: EdgeInsets.only(bottom: 8),
                expandedCrossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Beberapa HP menahan notifikasi setelah aplikasi ditutup dari daftar aplikasi terbaru. Bila notifikasi '
                    'sering terlambat atau tidak muncul:\n'
                    '1. Pengaturan HP → Aplikasi → RT05 TAKEDA → Baterai → pilih "Tidak dibatasi".\n'
                    '2. Xiaomi/Redmi/POCO: aktifkan "Mulai otomatis".\n'
                    '3. Oppo/Realme/Vivo: izinkan aktivitas latar belakang.\n'
                    '4. Samsung: keluarkan RT05 TAKEDA dari "Aplikasi tidur".\n'
                    'Pemberitahuan yang terlewat tetap tersimpan di kotak masuk.',
                    style: TextStyle(height: 1.5),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          SectionCard(
            title: 'Jenis notifikasi push',
            child: AsyncView(
              value: prefs,
              onRetry: () => ref.invalidate(notificationPreferencesProvider),
              builder: (p) => Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Mematikan jenis hanya menghentikan push. Semua pemberitahuan tetap masuk ke kotak masuk aplikasi. '
                    'Status usulan kegiatan dan informasi sistem selalu dikirim.',
                    style: TextStyle(color: Brand.muted, height: 1.4),
                  ),
                  const SizedBox(height: 8),
                  for (final (key, label, icon) in kinds)
                    SwitchListTile(
                      key: ValueKey('pref-$key'),
                      contentPadding: EdgeInsets.zero,
                      secondary: Icon(icon, color: Brand.navy),
                      title: Text(label),
                      value: p[key] == true,
                      onChanged: (v) async {
                        try {
                          await ref.read(apiProvider).patch('/notification-preferences', data: {key: v});
                          ref.invalidate(notificationPreferencesProvider);
                          if (context.mounted) {
                            showMessage(context, v ? '$label akan dikirim sebagai push.' : '$label tidak dikirim sebagai push; tetap ada di kotak masuk.');
                          }
                        } on ApiException catch (e) {
                          if (context.mounted) showApiError(context, e);
                        }
                      },
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _PushStatus extends ConsumerWidget {
  const _PushStatus(this.permission);
  final PushPermission permission;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final (icon, color, title, text) = switch (permission) {
      PushPermission.granted => (Icons.notifications_active, Brand.success, 'Aktif', 'HP ini menerima notifikasi RT05 TAKEDA.'),
      PushPermission.denied => (
        Icons.notifications_off_outlined,
        Brand.warning,
        'Diblokir',
        'Izin notifikasi ditolak. Layanan tetap bisa dipakai dan kotak masuk tetap diperbarui. Untuk mengaktifkan: '
            'Pengaturan HP → Aplikasi → RT05 TAKEDA → Notifikasi.',
      ),
      PushPermission.notDetermined => (Icons.notifications_none, Brand.navy, 'Belum diizinkan', 'Izinkan agar pengumuman dan pengingat agenda muncul di HP.'),
      PushPermission.unavailable => (
        Icons.inbox_outlined,
        Brand.muted,
        'Push belum tersedia',
        'Versi aplikasi ini belum tersambung ke layanan push. Pemberitahuan tetap masuk ke kotak masuk aplikasi.',
      ),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color, size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(fontWeight: FontWeight.w800, color: color),
                  ),
                  const SizedBox(height: 4),
                  Text(text, style: const TextStyle(height: 1.4)),
                ],
              ),
            ),
          ],
        ),
        if (permission == PushPermission.notDetermined) ...[
          const SizedBox(height: 12),
          BusyButton(
            label: 'Izinkan notifikasi',
            icon: Icons.notifications_active_outlined,
            onPressed: () async {
              final p = await ref.read(pushServiceProvider).register();
              ref.invalidate(pushPermissionProvider);
              if (!context.mounted) return;
              p == PushPermission.granted
                  ? showMessage(context, 'Notifikasi aktif di HP ini.')
                  : showMessage(context, 'Izin tidak diberikan. Kotak masuk tetap bekerja.', kind: AlertKind.warning);
            },
          ),
        ],
      ],
    );
  }
}
