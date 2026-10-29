import 'dart:io' show Platform;
import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/emergency_platform.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';

const emergencyTermsVersion = '2026-10-29';
const emergencyLegacyTermsVersion = '2026-10-27';
const emergencyKinds = {'kebakaran': 'Kebakaran', 'kemalingan': 'Kemalingan', 'penyerangan': 'Penyerangan', 'lainnya': 'Darurat lain'};
const emergencyWarning = 'Tombol ini bukan pengganti 112, damkar, atau polisi. Jika bantuan diperlukan segera, hubungi bantuan darurat langsung.';

const _apaItu = [
  'Tombol Panik mengirim alarm darurat ke semua warga dan pengurus RT 05 Taman Kedaung yang aktif di aplikasi.',
  'Nama dan rumah Anda ikut terkirim agar tetangga tahu ke mana harus datang. Laporan ini tidak anonim.',
  'Alarm berbunyi mengikuti izin dan pengaturan bunyi di HP penerima, sehingga mungkin tidak semua HP berbunyi.',
  'Tombol ini membantu memanggil tetangga dan pengurus. Tombol ini bukan pengganti 112, pemadam kebakaran, atau polisi.',
];
const _risiko = [
  'Alarm langsung menyala begitu Anda selesai menggeser tombol. Tidak ada formulir tambahan.',
  'Setiap alarm tercatat di riwayat RT dan tidak dapat dihapus.',
  'Pengurus menilai setiap laporan. Alarm palsu atau iseng dapat dicatat sebagai penyalahgunaan.',
  'Setelah tiga kali penyalahgunaan, Tombol Panik di akun Anda dinonaktifkan. Layanan lain tetap berjalan dan Anda tetap menerima alarm dari warga lain.',
  'Alarm palsu membuat warga panik dan lama-kelamaan membuat alarm sungguhan diabaikan.',
];
const _nyalakanJika = [
  'Terjadi kebakaran di rumah atau lingkungan RT.',
  'Ada pencurian atau maling yang sedang atau baru saja terjadi.',
  'Ada penyerangan, perkelahian, atau ancaman terhadap keselamatan orang.',
  'Ada keadaan darurat serius lain yang membutuhkan bantuan warga secepatnya.',
];
const _janganNyalakan = [
  'Uji coba, bercanda, atau sekadar mencoba tombol.',
  'Pengumuman, undangan, atau informasi kegiatan.',
  'Keluhan lingkungan, kebisingan, atau urusan iuran. Gunakan menu Pengaduan.',
  'Kejadian yang sudah selesai dan tidak membutuhkan bantuan lagi.',
];

String emergencyDeliveryText(Object? state) => switch (state) {
  'antrean' || 'diproses' => 'Laporan tersimpan. Pemberitahuan masih diproses. Jangan menunggu alarm untuk menghubungi bantuan langsung.',
  'gagal' || 'sebagian_gagal' => 'Pengiriman pemberitahuan mengalami kegagalan. Hubungi bantuan darurat dan pengurus langsung.',
  'dilewati' => 'Pemberitahuan tidak dikirim atau sudah kedaluwarsa. Hubungi pengurus atau bantuan darurat langsung.',
  'provider_menerima' => 'Layanan notifikasi menerima pengiriman. Ini belum membuktikan HP penerima menerima atau membunyikan alarm.',
  _ => 'Laporan tersimpan. Status pemberitahuan belum diketahui. Hubungi bantuan langsung bila diperlukan.',
};

String emergencyKindLabel(Object? kind) => emergencyKinds[kind] ?? 'Darurat';

String emergencyStatusBadge(Object? status) => switch (status) {
  'aktif' => 'Menyala',
  'selesai' => 'Selesai',
  'dibatalkan_pelapor' => 'Dibatalkan pelapor',
  'palsu' => 'Laporan palsu',
  _ => '$status',
};

Color emergencyStatusColor(Object? status) => switch (status) {
  'aktif' => Brand.danger,
  'selesai' => Brand.success,
  'palsu' => Brand.warning,
  _ => Brand.muted,
};

String emergencyWhen(Object? iso) {
  final t = DateTime.tryParse('$iso')?.toUtc();
  if (t == null) return '-';
  final d = DateTime.now().toUtc().difference(t);
  if (d.inMinutes < 1) return 'Baru saja';
  if (d.inMinutes < 60) return '${d.inMinutes} menit lalu';
  if (d.inHours < 24) return '${d.inHours} jam lalu';
  return tanggal(t, jam: true);
}

String _key() {
  final r = Random.secure();
  final b = List<int>.generate(16, (_) => r.nextInt(256));
  b[6] = (b[6] & 15) | 64;
  b[8] = (b[8] & 63) | 128;
  final h = b.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
  return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
}

final emergencyStatusProvider = FutureProvider.autoDispose<Json>((ref) async {
  final session = ref.watch(sessionProvider);
  if (!session.readyForServices) return {};
  return Json.from(await ref.read(apiProvider).get('/emergencies/status') as Map);
}, retry: (_, _) => null);

final emergencyHistoryProvider = FutureProvider.autoDispose<List<Json>>((ref) async {
  final session = ref.watch(sessionProvider);
  if (!session.readyForServices) return [];
  final page = await ref.read(apiProvider).page('/emergencies');
  final raw = page['data'];
  if (raw is! List) return [];
  return raw.whereType<Map>().map(Json.from).toList();
}, retry: (_, _) => null);

Route<void> emergencyPageRoute() => PageRouteBuilder<void>(
  pageBuilder: (context, animation, _) => const EmergencyPage(),
  transitionDuration: const Duration(milliseconds: 280),
  reverseTransitionDuration: const Duration(milliseconds: 220),
  transitionsBuilder: (context, animation, _, child) {
    final fade = FadeTransition(opacity: animation, child: child);
    if (MediaQuery.of(context).disableAnimations) return fade;
    return SlideTransition(
      position: Tween(begin: const Offset(0, 1), end: Offset.zero).animate(CurvedAnimation(parent: animation, curve: Curves.easeOut)),
      child: fade,
    );
  },
);

class EmergencyFab extends ConsumerWidget {
  const EmergencyFab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(emergencyStatusProvider).asData?.value;
    // Gerbang rilis server (RT05_PANIC_ENABLED) belum dibuka: tidak ada FAB sama sekali, bukan FAB abu-abu.
    if (status == null || status['tersedia'] != true) return const SizedBox.shrink();
    final muted = status['aktif'] != true || (status['penyalahgunaan'] as num? ?? 0) >= 3;
    return Semantics(
      button: true,
      label: 'Tombol panik. Buka ketentuan dan nyalakan alarm darurat.',
      child: SizedBox(
        width: 56,
        height: 56,
        child: FloatingActionButton(
          heroTag: 'emergency-fab',
          tooltip: 'Tombol Panik',
          backgroundColor: muted ? Brand.muted : Brand.danger,
          foregroundColor: Colors.white,
          elevation: 3,
          onPressed: () => Navigator.of(context).push(emergencyPageRoute()),
          child: const Icon(Icons.emergency, size: 28),
        ),
      ),
    );
  }
}

/// Beranda warga dan pengurus: riwayat hanya tampil setelah gerbang rilis server terbuka,
/// agar server tanpa M19 tidak menampilkan galat 403 di Beranda.
class EmergencyHomeSection extends ConsumerWidget {
  const EmergencyHomeSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final open = ref.watch(emergencyStatusProvider).asData?.value['tersedia'] == true;
    if (!open) return const SizedBox.shrink();
    return const Padding(
      padding: EdgeInsets.only(top: 16),
      child: Column(
        children: [
          AlarmReadinessCard(),
          SectionCard(title: 'Laporan darurat', child: EmergencyHistorySection()),
        ],
      ),
    );
  }
}

final alarmReadinessProvider = FutureProvider.autoDispose<AlarmReadiness>((ref) => ref.read(emergencyPlatformProvider).readiness());

/// ADR50: alarm darurat hanya bisa berdering bila izin notifikasi dan layar penuh diberikan. Kartu hilang bila sudah siap.
class AlarmReadinessCard extends ConsumerStatefulWidget {
  const AlarmReadinessCard({super.key});

  @override
  ConsumerState<AlarmReadinessCard> createState() => _AlarmReadinessCardState();
}

class _AlarmReadinessCardState extends ConsumerState<AlarmReadinessCard> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // Kembali dari pengaturan HP: periksa ulang izin.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) ref.invalidate(alarmReadinessProvider);
  }

  @override
  Widget build(BuildContext context) {
    final readiness = ref.watch(alarmReadinessProvider).asData?.value;
    if (readiness == null || readiness.ready) return const SizedBox.shrink();
    final platform = ref.read(emergencyPlatformProvider);
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: SectionCard(
        title: 'Izinkan alarm darurat',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Agar HP ini berdering seperti panggilan saat ada warga menekan Tombol Panik, izinkan:'),
            if (!readiness.notifications) ...[
              const SizedBox(height: 8),
              const Text('• Notifikasi dan kanal "Alarm darurat RT05" (bunyi aktif).'),
              TextButton(onPressed: platform.settings, child: const Text('Buka pengaturan notifikasi')),
            ],
            if (!readiness.fullScreen) ...[
              const SizedBox(height: 8),
              const Text('• Notifikasi layar penuh (Android 14 ke atas).'),
              TextButton(onPressed: platform.fullScreenSettings, child: const Text('Izinkan layar penuh')),
            ],
          ],
        ),
      ),
    );
  }
}

class EmergencyHistorySection extends ConsumerWidget {
  const EmergencyHistorySection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => AsyncView(
    value: ref.watch(emergencyHistoryProvider),
    onRetry: () => ref.invalidate(emergencyHistoryProvider),
    builder: (rows) {
      if (rows.isEmpty) {
        return const Padding(
          padding: EdgeInsets.symmetric(vertical: 8),
          child: Text('Belum ada laporan darurat. Semoga tetap begitu.', style: TextStyle(color: Brand.muted)),
        );
      }
      return Column(children: [for (final row in rows) _HistoryTile(row: row)]);
    },
  );
}

class _HistoryTile extends StatelessWidget {
  const _HistoryTile({required this.row});
  final Json row;

  @override
  Widget build(BuildContext context) {
    final status = row['status'];
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(Icons.emergency_outlined, color: emergencyStatusColor(status)),
      title: Text('${emergencyKindLabel(row['jenis'])} · ${emergencyStatusBadge(status)}'),
      subtitle: Text('${row['pelapor']} · ${row['rumah']}\n${emergencyWhen(row['created_at'])}'),
      isThreeLine: true,
      trailing: DecoratedBox(
        decoration: BoxDecoration(color: emergencyStatusColor(status).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          child: Text(
            emergencyStatusBadge(status),
            style: TextStyle(color: emergencyStatusColor(status), fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ),
      ),
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EmergencyDetailPage(id: '${row['id']}'))),
    );
  }
}

class EmergencyAdminSettings extends ConsumerStatefulWidget {
  const EmergencyAdminSettings({super.key});
  @override
  ConsumerState<EmergencyAdminSettings> createState() => _EmergencyAdminSettingsState();
}

class _EmergencyAdminSettingsState extends ConsumerState<EmergencyAdminSettings> {
  Json? _settingsPending;
  final String _settingsKey = _key();

  Future<void> _configure(Json status) async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch)) return;
    _settingsPending ??= {'versi': status['versi'], 'aktif': status['aktif'] != true};
    try {
      await ref
          .read(apiProvider)
          .post('/admin/emergencies/settings', data: _settingsPending, idempotencyKey: _settingsKey, bearer: s.token, sessionEpoch: epoch);
      if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch)) return;
      _settingsPending = null;
      ref.invalidate(emergencyStatusProvider);
    } on ApiException catch (e) {
      if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch)) return;
      if (e.status > 0 && e.status < 500) _settingsPending = null;
      showApiError(context, e);
      if (e.isConflict) ref.invalidate(emergencyStatusProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!ref.watch(sessionProvider).pengurus) return const SizedBox.shrink();
    return AsyncView(
      value: ref.watch(emergencyStatusProvider),
      onRetry: () => ref.invalidate(emergencyStatusProvider),
      builder: (status) {
        if (status['tersedia'] != true) return const SizedBox.shrink();
        return SectionCard(
          title: 'Tombol panik RT',
          child: BusyButton(
            label: _settingsPending != null
                ? 'Coba ulang perubahan pengaturan'
                : status['aktif'] == true
                ? 'Nonaktifkan tombol panik RT'
                : 'Aktifkan tombol panik RT',
            onPressed: () => _configure(status),
          ),
        );
      },
    );
  }
}

class EmergencyPage extends ConsumerStatefulWidget {
  const EmergencyPage({super.key});
  @override
  ConsumerState<EmergencyPage> createState() => _EmergencyPageState();
}

class _EmergencyPageState extends ConsumerState<EmergencyPage> {
  late final String? _owner = ref.read(sessionProvider).akun?.id;
  bool _busy = false;
  Json? _pending;
  String _requestKey = _key();
  String? _error;
  bool _seenSentinel = false;
  double _progress = 0;
  final _sentinelKey = GlobalKey();
  final _scroll = ScrollController();

  bool _current(SessionState s, int epoch) => mounted && ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch);

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_measureSentinel);
    WidgetsBinding.instance.addPostFrameCallback((_) => _measureSentinel());
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _measureSentinel() {
    final ctx = _sentinelKey.currentContext;
    if (ctx == null || !mounted) return;
    final box = ctx.findRenderObject() as RenderBox?;
    if (box == null || !box.hasSize) return;
    final y = box.localToGlobal(Offset.zero).dy;
    final view = MediaQuery.sizeOf(context).height;
    final visible = y < view - 88;
    final max = _scroll.hasClients ? _scroll.position.maxScrollExtent : 0;
    final pixels = _scroll.hasClients ? _scroll.position.pixels : 0;
    final nextProgress = max <= 0 ? 1.0 : (pixels / max).clamp(0.0, 1.0);
    final unlock = visible || max <= 0;
    if (unlock != _seenSentinel || nextProgress != _progress) {
      setState(() {
        if (unlock && !_seenSentinel) HapticFeedback.selectionClick();
        _seenSentinel = unlock;
        _progress = unlock ? 1 : nextProgress;
      });
    }
  }

  Future<void> _send() async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    if (!_current(s, epoch) || _busy) return;
    _pending ??= {'konfirmasi': true, 'versi_ketentuan': emergencyTermsVersion};
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = Json.from(
        await ref.read(apiProvider).post('/emergencies', data: _pending, idempotencyKey: _requestKey, bearer: s.token, sessionEpoch: epoch) as Map,
      );
      if (!mounted || !_current(s, epoch)) return;
      _pending = null;
      _requestKey = _key();
      ref.invalidate(emergencyStatusProvider);
      ref.invalidate(emergencyHistoryProvider);
      await Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => EmergencyAlarmPage(id: '${result['id']}')));
    } on ApiException catch (e) {
      if (!mounted || !_current(s, epoch)) return;
      if (e.status > 0 && e.status < 500) _pending = null;
      setState(() => _error = _pending == null ? e.display : 'Belum terkirim. Periksa koneksi, lalu coba lagi.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _talkBackArm() async {
    if (!_seenSentinel || _busy) return;
    final ok = await confirm(
      context,
      title: 'Nyalakan alarm darurat?',
      message: 'Nama dan rumah Anda akan terlihat oleh warga dan pengurus penerima. Geser atau konfirmasi ini menyalakan alarm.',
      yes: 'Nyalakan',
      danger: true,
      icon: Icons.emergency,
    );
    if (ok) await _send();
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    if (!session.readyForServices || session.akun?.id != _owner) {
      return const Scaffold(body: Center(child: Text('Sesi berubah. Buka kembali dari beranda.')));
    }
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(title: const Text('Tombol Panik')),
        body: AsyncView(
          value: ref.watch(emergencyStatusProvider),
          onRetry: () => ref.invalidate(emergencyStatusProvider),
          builder: (status) {
            final blocked = (status['penyalahgunaan'] as num? ?? 0) >= 3;
            final staleTerms = status['versi_ketentuan'] != emergencyTermsVersion && status['versi_ketentuan'] != emergencyLegacyTermsVersion;
            final allowed = status['tersedia'] == true && status['aktif'] == true && !blocked && !staleTerms;
            return Column(
              children: [
                Expanded(
                  child: NotificationListener<ScrollNotification>(
                    onNotification: (_) {
                      WidgetsBinding.instance.addPostFrameCallback((_) => _measureSentinel());
                      return false;
                    },
                    child: ListView(
                      controller: _scroll,
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                      children: [
                        if (status['uji_terbatas'] == true)
                          Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(color: Brand.warningBg, borderRadius: BorderRadius.circular(12)),
                            child: const Text('Uji terbatas: alarm hanya dikirim ke akun uji.', style: TextStyle(color: Brand.warning)),
                          ),
                        if (!allowed)
                          SectionCard(
                            title: blocked
                                ? 'Tombol Panik di akun Anda dinonaktifkan setelah tiga penyalahgunaan. Anda tetap menerima alarm dari warga lain. Hubungi pengurus.'
                                : staleTerms
                                ? 'Ketentuan terbaru belum tersedia di aplikasi ini. Perbarui aplikasi.'
                                : 'Tombol Panik sedang tidak aktif. Dalam keadaan darurat, hubungi pengurus atau 112.',
                            child: const Text(emergencyWarning, style: TextStyle(color: Brand.muted, height: 1.45)),
                          )
                        else ...[
                          const _HeroSiren(),
                          const SizedBox(height: 12),
                          _TermsCard(title: 'Apa itu Tombol Panik', icon: Icons.info_outline, items: _apaItu),
                          const SizedBox(height: 12),
                          _TermsCard(title: 'Risiko dan Konsekuensi', icon: Icons.warning_amber, items: _risiko, dangerEdge: true),
                          const SizedBox(height: 12),
                          _KetentuanCard(),
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(color: Brand.warningBg, borderRadius: BorderRadius.circular(16)),
                            child: const Text('Jika nyawa terancam, hubungi juga 112.', style: TextStyle(color: Brand.warning, height: 1.45)),
                          ),
                          const SizedBox(height: 12),
                          const Text('Ketentuan Tombol Panik · Versi 29 Oktober 2026.', style: TextStyle(color: Brand.muted, fontSize: 13)),
                          KeyedSubtree(key: _sentinelKey, child: const SizedBox(height: 1)),
                        ],
                        const SizedBox(height: 16),
                        TextButton(onPressed: () => ref.read(emergencyPlatformProvider).settings(), child: const Text('Periksa izin dan bunyi darurat di HP')),
                        BusyButton(
                          label: 'Tambahkan shortcut di layar utama HP',
                          outlined: true,
                          onPressed: () async {
                            final started = ref.read(sessionProvider);
                            final epoch = ref.read(sessionProvider.notifier).epoch;
                            final requested = await ref.read(emergencyPlatformProvider).pinShortcut();
                            if (!context.mounted || !ref.read(sessionProvider.notifier).isCurrentActive(started, epoch: epoch)) return;
                            showMessage(
                              context,
                              requested
                                  ? 'Permintaan dikirim. Konfirmasi penambahan shortcut di layar HP.'
                                  : 'Launcher HP belum mendukung penambahan ini. Gunakan Tombol panik di Beranda aplikasi.',
                            );
                          },
                        ),
                        const SizedBox(height: 16),
                        const Text('Riwayat', style: TextStyle(fontWeight: FontWeight.w700)),
                        const EmergencyHistorySection(),
                      ],
                    ),
                  ),
                ),
                if (allowed)
                  Material(
                    color: Colors.white,
                    elevation: 8,
                    child: SafeArea(
                      top: false,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            LinearProgressIndicator(value: _progress, minHeight: 4, backgroundColor: Brand.skyDeep, color: Brand.navy),
                            const SizedBox(height: 12),
                            if (_error != null) Text(_error!, style: const TextStyle(color: Brand.danger, height: 1.4)),
                            if (_pending != null && _error != null)
                              BusyButton(label: 'Coba kirim ulang', onPressed: _send)
                            else
                              EmergencyArmSlider(unlocked: _seenSentinel, busy: _busy, onArmed: _send, onTalkBack: _talkBackArm),
                            TextButton(onPressed: _busy ? null : () => Navigator.maybePop(context), child: const Text('Batal')),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _HeroSiren extends StatelessWidget {
  const _HeroSiren();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 20),
    decoration: BoxDecoration(color: Brand.dangerBg, borderRadius: BorderRadius.circular(16)),
    child: const Column(
      children: [
        _PulseSiren(),
        SizedBox(height: 12),
        Text(
          'Baca dulu sebelum menyalakan.',
          textAlign: TextAlign.center,
          style: TextStyle(fontFamily: Brand.fontDisplay, fontWeight: FontWeight.w700, fontSize: 20, color: Brand.navyDeep),
        ),
        SizedBox(height: 6),
        Text(
          'Alarm ini akan didengar seluruh warga. Pastikan Anda memahami ketentuannya.',
          textAlign: TextAlign.center,
          style: TextStyle(color: Brand.muted, height: 1.45),
        ),
      ],
    ),
  );
}

class _PulseSiren extends StatefulWidget {
  const _PulseSiren();
  @override
  State<_PulseSiren> createState() => _PulseSirenState();
}

class _PulseSirenState extends State<_PulseSiren> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1600));

  @override
  void initState() {
    super.initState();
    if (!Platform.environment.containsKey('FLUTTER_TEST')) {
      _c.repeat();
    }
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduce = MediaQuery.of(context).disableAnimations || !TickerMode.valuesOf(context).enabled;
    const icon = Icon(Icons.emergency, color: Brand.danger, size: 36);
    if (reduce) {
      return Container(
        width: 72,
        height: 72,
        alignment: Alignment.center,
        decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
        child: icon,
      );
    }
    return SizedBox(
      width: 96,
      height: 96,
      child: AnimatedBuilder(
        animation: _c,
        builder: (context, _) {
          return Stack(
            alignment: Alignment.center,
            children: [
              for (final shift in [0.0, 0.5]) _ring(((_c.value + shift) % 1.0)),
              Container(
                width: 64,
                height: 64,
                alignment: Alignment.center,
                decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                child: icon,
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _ring(double t) => Container(
    width: 64 + 32 * t,
    height: 64 + 32 * t,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      border: Border.all(color: Brand.danger.withValues(alpha: (1 - t) * 0.45), width: 2),
    ),
  );
}

class _TermsCard extends StatelessWidget {
  const _TermsCard({required this.title, required this.icon, required this.items, this.dangerEdge = false});
  final String title;
  final IconData icon;
  final List<String> items;
  final bool dangerEdge;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: Container(
      decoration: dangerEdge
          ? const BoxDecoration(
              border: Border(left: BorderSide(color: Brand.danger, width: 4)),
            )
          : null,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: dangerEdge ? Brand.danger : Brand.navy),
              const SizedBox(width: 8),
              Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium)),
            ],
          ),
          const SizedBox(height: 12),
          for (final item in items) _Bullet(item),
        ],
      ),
    ),
  );
}

class _KetentuanCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.rule, color: Brand.navy),
              const SizedBox(width: 8),
              Expanded(child: Text('Ketentuan Penggunaan', style: Theme.of(context).textTheme.titleMedium)),
            ],
          ),
          const SizedBox(height: 12),
          const Text('Nyalakan hanya jika', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          for (final item in _nyalakanJika) _Bullet(item),
          const SizedBox(height: 8),
          const Text('Jangan nyalakan untuk', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          for (final item in _janganNyalakan) _Bullet(item, color: Brand.danger, icon: Icons.close),
        ],
      ),
    ),
  );
}

class _Bullet extends StatelessWidget {
  const _Bullet(this.text, {this.color = Brand.navy, this.icon = Icons.circle});
  final String text;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 7),
          child: Icon(icon, size: icon == Icons.circle ? 6 : 14, color: color),
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(height: 1.45))),
      ],
    ),
  );
}

class EmergencyArmSlider extends StatefulWidget {
  const EmergencyArmSlider({super.key, required this.unlocked, required this.busy, required this.onArmed, required this.onTalkBack});
  final bool unlocked;
  final bool busy;
  final Future<void> Function() onArmed;
  final Future<void> Function() onTalkBack;

  @override
  State<EmergencyArmSlider> createState() => _EmergencyArmSliderState();
}

class _EmergencyArmSliderState extends State<EmergencyArmSlider> {
  double _value = 0;

  @override
  void didUpdateWidget(covariant EmergencyArmSlider oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!widget.unlocked && _value != 0) _value = 0;
  }

  @override
  Widget build(BuildContext context) {
    final unlocked = widget.unlocked && !widget.busy;
    return Semantics(
      button: true,
      enabled: unlocked,
      label: 'Nyalakan alarm darurat',
      onTap: unlocked ? widget.onTalkBack : null,
      child: LayoutBuilder(
        builder: (context, box) {
          final width = box.maxWidth;
          final travel = max(width - 56, 1.0);
          return GestureDetector(
            key: const ValueKey('geser-aktifkan'),
            onHorizontalDragUpdate: !unlocked ? null : (d) => setState(() => _value = (_value + d.delta.dx / travel).clamp(0.0, 1.0)),
            onHorizontalDragEnd: !unlocked
                ? null
                : (_) async {
                    if (_value >= 0.9) {
                      HapticFeedback.heavyImpact();
                      await widget.onArmed();
                      if (mounted) setState(() => _value = 0);
                    } else {
                      setState(() => _value = 0);
                    }
                  },
            child: AnimatedContainer(
              duration: MediaQuery.of(context).disableAnimations ? Duration.zero : const Duration(milliseconds: 200),
              height: 56,
              decoration: BoxDecoration(color: widget.unlocked ? Brand.dangerBg : const Color(0xFFE8EEF2), borderRadius: BorderRadius.circular(28)),
              child: Stack(
                alignment: Alignment.centerLeft,
                children: [
                  FractionallySizedBox(
                    widthFactor: _value,
                    child: Container(
                      decoration: BoxDecoration(color: Brand.danger.withValues(alpha: 0.35), borderRadius: BorderRadius.circular(28)),
                    ),
                  ),
                  Center(
                    child: widget.busy
                        ? const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Brand.danger)),
                              SizedBox(width: 8),
                              Text(
                                'Menyalakan…',
                                style: TextStyle(color: Brand.navyDeep, fontWeight: FontWeight.w600),
                              ),
                            ],
                          )
                        : Text(
                            widget.unlocked ? 'Geser untuk menyalakan alarm' : 'Scroll sampai bawah untuk membuka',
                            style: TextStyle(color: widget.unlocked ? Brand.navyDeep : Brand.muted, fontWeight: FontWeight.w600),
                          ),
                  ),
                  Transform.translate(
                    offset: Offset(4 + _value * travel, 0),
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(color: widget.unlocked ? Brand.danger : Brand.muted, shape: BoxShape.circle),
                      child: Icon(widget.unlocked ? Icons.emergency : Icons.lock, color: Colors.white),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class EmergencyAlarmPage extends ConsumerStatefulWidget {
  const EmergencyAlarmPage({super.key, required this.id});
  final String id;
  @override
  ConsumerState<EmergencyAlarmPage> createState() => _EmergencyAlarmPageState();
}

class _EmergencyAlarmPageState extends ConsumerState<EmergencyAlarmPage> {
  late final String? _owner = ref.read(sessionProvider).akun?.id;
  late Future<dynamic> _detail = ref.read(apiProvider).get('/emergencies/${widget.id}');
  String _kindKey = _key();
  final _cancelKey = _key();
  bool _busy = false;

  Future<void> _setKind(Json e, String kind) async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch) || _busy) return;
    setState(() => _busy = true);
    try {
      final updated = await ref
          .read(apiProvider)
          .post('/emergencies/${widget.id}/jenis', data: {'versi': e['versi'], 'jenis': kind}, idempotencyKey: _kindKey, bearer: s.token, sessionEpoch: epoch);
      if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch)) return;
      setState(() {
        _kindKey = _key();
        _detail = Future.value(updated);
      });
      ref.invalidate(emergencyHistoryProvider);
    } on ApiException catch (error) {
      if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch)) return;
      showApiError(context, error);
      if (error.isConflict) setState(() => _detail = ref.read(apiProvider).get('/emergencies/${widget.id}'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _cancel(Json e) async {
    final sure = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('Batalkan alarm?'),
        content: const Text('Warga dan pengurus akan menerima pemberitahuan bahwa alarm dibatalkan. Laporan tetap tercatat di riwayat.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Tetap nyalakan')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Brand.danger),
            onPressed: () => Navigator.pop(c, true),
            child: const Text('Ya, batalkan alarm'),
          ),
        ],
      ),
    );
    if (sure != true || !mounted) return;
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    try {
      await ref
          .read(apiProvider)
          .post('/emergencies/${widget.id}/cancel', data: {'versi': e['versi']}, idempotencyKey: _cancelKey, bearer: s.token, sessionEpoch: epoch);
      if (!mounted || !ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch)) return;
      ref.invalidate(emergencyHistoryProvider);
      Navigator.of(context).popUntil((r) => r.isFirst);
    } on ApiException catch (error) {
      if (!mounted) return;
      showApiError(context, error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(sessionProvider);
    if (!s.readyForServices || s.akun?.id != _owner) {
      return const Scaffold(body: Center(child: Text('Sesi berubah. Buka kembali dari beranda.')));
    }
    return Scaffold(
      backgroundColor: Brand.danger,
      appBar: AppBar(backgroundColor: Brand.danger, foregroundColor: Colors.white, title: const Text('Alarm darurat menyala')),
      body: FutureBuilder<dynamic>(
        future: _detail,
        builder: (context, snap) {
          if (snap.hasError) {
            return ErrorState(message: '${snap.error}', onRetry: () => setState(() => _detail = ref.read(apiProvider).get('/emergencies/${widget.id}')));
          }
          if (!snap.hasData) return const Center(child: CircularProgressIndicator(color: Colors.white));
          final e = Json.from(snap.data as Map);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              const _PulseSiren(),
              const SizedBox(height: 16),
              const Text(
                'Alarm darurat menyala',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, fontFamily: Brand.fontDisplay, fontWeight: FontWeight.w700, fontSize: 24),
              ),
              const SizedBox(height: 8),
              const Text(
                'Laporan diterima server. Pemberitahuan sedang dikirim ke warga dan pengurus.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, height: 1.45),
              ),
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'IDENTITAS TERKIRIM',
                        style: TextStyle(color: Brand.danger, fontWeight: FontWeight.w700, fontSize: 12, letterSpacing: 0.4),
                      ),
                      const SizedBox(height: 8),
                      Text('Pelapor: ${e['pelapor']}\nRumah: ${e['rumah']}', style: const TextStyle(height: 1.45)),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Bantu tetangga lebih siap',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final kind in emergencyKinds.entries)
                    FilterChip(
                      selected: e['jenis'] == kind.key,
                      label: Text(kind.value),
                      checkmarkColor: Brand.danger,
                      onSelected: _busy || e['status'] != 'aktif' ? null : (_) => _setKind(e, kind.key),
                    ),
                ],
              ),
              const SizedBox(height: 20),
              if (e['status'] == 'aktif')
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Colors.white),
                  ),
                  onPressed: () => _cancel(e),
                  child: const Text('Salah pencet? Batalkan alarm'),
                ),
              TextButton(
                onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
                child: const Text('Kembali ke Beranda', style: TextStyle(color: Colors.white)),
              ),
            ],
          );
        },
      ),
    );
  }
}

class EmergencyDetailPage extends ConsumerStatefulWidget {
  const EmergencyDetailPage({super.key, required this.id});
  final String id;
  @override
  ConsumerState<EmergencyDetailPage> createState() => _EmergencyDetailPageState();
}

class _EmergencyDetailPageState extends ConsumerState<EmergencyDetailPage> {
  late final String? _owner = ref.read(sessionProvider).akun?.id;
  late Future<dynamic> _detail = ref.read(apiProvider).get('/emergencies/${widget.id}');
  final _reason = TextEditingController();
  final _requestKey = _key();
  String _status = 'selesai';
  bool _misuse = false;
  Json? _pending;
  String? _error;
  final _correctionReason = TextEditingController();
  final _correctionKey = _key();
  Json? _correctionPending;
  @override
  void dispose() {
    _reason.dispose();
    _correctionReason.dispose();
    super.dispose();
  }

  Future<void> _resolve(Json e) async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    bool current() => mounted && ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch);
    if (!current()) return;
    if (_reason.text.trim().length < 3) {
      setState(() => _error = 'Isi alasan penanganan, minimal tiga karakter.');
      return;
    }
    _pending ??= {'versi': e['versi'], 'status': _status, 'penyalahgunaan': _misuse, 'alasan': _reason.text.trim()};
    setState(() => _error = null);
    try {
      final updated = await ref
          .read(apiProvider)
          .post('/admin/emergencies/${widget.id}/resolve', data: _pending, idempotencyKey: _requestKey, bearer: s.token, sessionEpoch: epoch);
      if (!current()) return;
      setState(() {
        _pending = null;
        _detail = Future.value(updated);
      });
      ref.invalidate(emergencyStatusProvider);
      ref.invalidate(emergencyHistoryProvider);
    } on ApiException catch (error) {
      if (!current()) return;
      if (error.status > 0 && error.status < 500) _pending = null;
      setState(() {
        _error = error.display;
        if (error.isConflict) _detail = ref.read(apiProvider).get('/emergencies/${widget.id}');
      });
    }
  }

  Future<void> _correctMisuse() async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    bool current() => mounted && ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch);
    if (!current()) return;
    if (_correctionReason.text.trim().length < 5) {
      setState(() => _error = 'Isi alasan pembatalan, minimal lima karakter.');
      return;
    }
    _correctionPending ??= {'alasan': _correctionReason.text.trim()};
    setState(() => _error = null);
    try {
      final updated = await ref
          .read(apiProvider)
          .post(
            '/admin/emergencies/${widget.id}/misuse-correction',
            data: _correctionPending,
            idempotencyKey: _correctionKey,
            bearer: s.token,
            sessionEpoch: epoch,
          );
      if (!current()) return;
      setState(() {
        _correctionPending = null;
        _detail = Future.value(updated);
      });
      if (!mounted) return;
      showMessage(context, 'Penanda penyalahgunaan dibatalkan. Pelapor diberi tahu.');
    } on ApiException catch (error) {
      if (!current()) return;
      if (error.status > 0 && error.status < 500) _correctionPending = null;
      setState(() {
        _error = error.display;
        if (error.isConflict) _detail = ref.read(apiProvider).get('/emergencies/${widget.id}');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(sessionProvider);
    if (!s.readyForServices || s.akun?.id != _owner) return const Scaffold(body: Center(child: Text('Sesi berubah. Buka kembali dari beranda.')));
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan darurat')),
      body: FutureBuilder<dynamic>(
        future: _detail,
        builder: (context, snap) {
          if (snap.hasError) {
            return ErrorState(message: '${snap.error}', onRetry: () => setState(() => _detail = ref.read(apiProvider).get('/emergencies/${widget.id}')));
          }
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final e = Json.from(snap.data as Map);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(emergencyKindLabel(e['jenis']), style: Theme.of(context).textTheme.headlineSmall),
              Text('Pelapor: ${e['pelapor']}\nRumah: ${e['rumah']}\nStatus: ${emergencyStatusBadge(e['status'])}'),
              Text(emergencyDeliveryText(e['pemberitahuan'])),
              const SizedBox(height: 12),
              const Text(emergencyWarning),
              if (e['catatan_pengurus'] != null) Text('Catatan pengurus: ${e['catatan_pengurus']}'),
              if (s.pengurus && e['penyalahgunaan'] == true && e['penyalahgunaan_dibatalkan'] == true)
                const Text('Penanda penyalahgunaan sudah dibatalkan; tidak dihitung pada akun pelapor.'),
              if (s.pengurus && e['status'] == 'palsu' && e['penyalahgunaan'] == true && e['penyalahgunaan_dibatalkan'] != true) ...[
                const SizedBox(height: 12),
                const Text('Salah menandai penyalahgunaan? Batalkan penandanya. Riwayat laporan tidak berubah.'),
                TextField(
                  key: const ValueKey('alasan-batal-penyalahgunaan'),
                  controller: _correctionReason,
                  enabled: _correctionPending == null,
                  maxLength: 300,
                  decoration: const InputDecoration(labelText: 'Alasan pembatalan (wajib)'),
                ),
                if (_error != null) Text(_error!, style: const TextStyle(color: Brand.danger)),
                BusyButton(
                  label: _correctionPending == null ? 'Batalkan penanda penyalahgunaan' : 'Coba ulang pembatalan',
                  outlined: true,
                  onPressed: _correctMisuse,
                ),
              ],
              if (s.pengurus && e['status'] == 'aktif') ...[
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: const InputDecoration(labelText: 'Hasil penanganan'),
                  items: const [
                    DropdownMenuItem(value: 'selesai', child: Text('Selesai')),
                    DropdownMenuItem(value: 'palsu', child: Text('Laporan palsu')),
                  ],
                  onChanged: _pending != null
                      ? null
                      : (v) => setState(() {
                          _status = v!;
                          if (v != 'palsu') _misuse = false;
                        }),
                ),
                if (_status == 'palsu')
                  CheckboxListTile(
                    value: _misuse,
                    title: const Text('Catat sebagai penyalahgunaan (menambah hitungan akun)'),
                    onChanged: _pending != null ? null : (v) => setState(() => _misuse = v == true),
                  ),
                TextField(
                  controller: _reason,
                  enabled: _pending == null,
                  maxLength: 1000,
                  decoration: const InputDecoration(labelText: 'Alasan penanganan (wajib)'),
                ),
                if (_error != null) Text(_error!, style: const TextStyle(color: Brand.danger)),
                BusyButton(label: _pending == null ? 'Simpan penanganan' : 'Coba ulang penanganan', onPressed: () => _resolve(e)),
              ],
            ],
          );
        },
      ),
    );
  }
}
