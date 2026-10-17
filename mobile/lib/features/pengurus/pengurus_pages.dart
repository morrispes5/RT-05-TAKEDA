import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import 'content_admin.dart';
import 'dues_admin.dart';
import 'finance_actions.dart';
import '../household/admin_census.dart';
import '../household/admin_links.dart';
import '../household/census_providers.dart';
import '../household/paged_list.dart';
import '../warga/activity_proposal_actions.dart' show jakartaWallToUtc;
import '../warga/activity_proposals.dart';
import '../warga/providers.dart';
import '../warga/warga_pages.dart';

/// UUID v4 untuk Idempotency-Key; dibuat sekali per niat transaksi dan dipakai ulang saat retry.
String newKey() {
  final r = Random.secure();
  final b = List<int>.generate(16, (_) => r.nextInt(256));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  final h = b.map((x) => x.toRadixString(16).padLeft(2, '0')).join();
  return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
}

final dashboardProvider = FutureProvider.autoDispose<Json>((ref) async => Json.from(await ref.read(apiProvider).get('/admin/dashboard') as Map));
final requestsProvider = FutureProvider.autoDispose.family<List<Json>, String>(
  (ref, status) async => ((await ref.read(apiProvider).page('/admin/additional-account-requests', query: {'status': status, 'per_page': 50}))['data'] as List)
      .cast<Map>()
      .map(Json.from)
      .toList(),
);
final tokenProvider = FutureProvider.autoDispose<Json>((ref) async => Json.from(await ref.read(apiProvider).get('/admin/registration-token') as Map));
final paymentsProvider = FutureProvider.autoDispose<List<Json>>(
  (ref) async => ((await ref.read(apiProvider).page('/admin/dues-payments', query: {'per_page': 50}))['data'] as List).cast<Map>().map(Json.from).toList(),
);

// ─────────────────────────────────────────── Ringkasan
class AdminHomePage extends ConsumerWidget {
  const AdminHomePage({super.key, required this.goTab});
  final void Function(int) goTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final d = ref.watch(dashboardProvider);
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(dashboardProvider),
      child: AsyncView(
        value: d,
        onRetry: () => ref.invalidate(dashboardProvider),
        builder: (d) {
          final iuran = d['iuran_bulan_ini'] as Map;
          final p = Map<String, dynamic>.from(d['pengaduan'] as Map? ?? {});
          final a = Map<String, dynamic>.from(d['aspirasi'] as Map? ?? {});
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(color: Brand.navy, borderRadius: BorderRadius.circular(20)),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Saldo kas RT', style: TextStyle(color: Colors.white70)),
                          Text(rupiah(d['saldo_kas'] as num), style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Colors.white)),
                          const SizedBox(height: 6),
                          Text(
                            'Iuran ${periode('${iuran['periode']}')}: ${iuran['lunas']} lunas, ${iuran['belum_bayar']} belum',
                            style: const TextStyle(color: Brand.yellow),
                          ),
                        ],
                      ),
                    ),
                    const GapuraMark(size: 52, light: true),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              // Dua kolom dengan tinggi mengikuti isi (bukan rasio tetap) agar aman pada skala teks besar.
              _TileRow(
                _Tile('Rumah aktif', '${d['rumah_aktif']}', Icons.home_work_outlined, () => goTab(1)),
                _Tile(
                  'Permohonan akun',
                  '${d['permohonan_menunggu']}',
                  Icons.how_to_reg_outlined,
                  () => goTab(1),
                  alert: (d['permohonan_menunggu'] as num) > 0,
                ),
              ),
              const SizedBox(height: 10),
              _TileRow(
                _Tile('Pengaduan baru', '${p['diajukan'] ?? 0}', Icons.report_outlined, () => goTab(2), alert: (p['diajukan'] ?? 0) > 0),
                _Tile('Aspirasi baru', '${a['diajukan'] ?? 0}', Icons.lightbulb_outline, () => goTab(2), alert: (a['diajukan'] ?? 0) > 0),
              ),
              const SizedBox(height: 16),
              BusyButton(
                label: 'Catat pembayaran iuran',
                icon: Icons.point_of_sale,
                onPressed: () async {
                  await Navigator.push(context, MaterialPageRoute(builder: (_) => const RecordPaymentPage()));
                  ref.invalidate(dashboardProvider);
                },
              ),
              const SizedBox(height: 10),
              BusyButton(
                label: 'Tinjau usulan kegiatan',
                icon: Icons.event_note_outlined,
                outlined: true,
                onPressed: () async {
                  await Navigator.push(context, MaterialPageRoute(builder: (_) => const ActivityProposalListPage(admin: true)));
                  ref.invalidate(dashboardProvider);
                },
              ),
              const SizedBox(height: 10),
              BusyButton(
                label: 'Token registrasi RT',
                icon: Icons.key_outlined,
                outlined: true,
                onPressed: () async {
                  await Navigator.push(context, MaterialPageRoute(builder: (_) => const TokenPage()));
                },
              ),
            ],
          );
        },
      ),
    );
  }
}

class _TileRow extends StatelessWidget {
  const _TileRow(this.left, this.right);
  final Widget left;
  final Widget right;

  @override
  Widget build(BuildContext context) => IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(child: left),
        const SizedBox(width: 10),
        Expanded(child: right),
      ],
    ),
  );
}

class _Tile extends StatelessWidget {
  const _Tile(this.label, this.value, this.icon, this.onTap, {this.alert = false});
  final String label;
  final String value;
  final IconData icon;
  final VoidCallback onTap;
  final bool alert;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Icon(icon, color: Brand.navy),
                const Spacer(),
                if (alert) const Icon(Icons.circle, size: 10, color: Brand.danger),
              ],
            ),
            const SizedBox(height: 6),
            Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800)),
            Text(label, style: const TextStyle(color: Brand.muted)),
          ],
        ),
      ),
    ),
  );
}

// ─────────────────────────────────────────── Token registrasi
class TokenPage extends ConsumerWidget {
  const TokenPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = ref.watch(tokenProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Token registrasi')),
      body: AsyncView(
        value: t,
        onRetry: () => ref.invalidate(tokenProvider),
        builder: (d) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SectionCard(
              child: Column(
                children: [
                  const Text(
                    'Bagikan token ini hanya kepada warga RT 05. Token tidak memberi hak pengurus.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Brand.muted),
                  ),
                  const SizedBox(height: 16),
                  SelectableText(
                    '${d['kode']}',
                    style: const TextStyle(fontSize: 44, letterSpacing: 10, fontWeight: FontWeight.w800, color: Brand.navy),
                  ),
                  const SizedBox(height: 6),
                  Text('Berlaku sampai ${tanggal(parseTime(d['berlaku_sampai']), jam: true)}', style: const TextStyle(color: Brand.muted)),
                  const SizedBox(height: 16),
                  BusyButton(
                    label: 'Salin token',
                    icon: Icons.copy,
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: '${d['kode']}'));
                      if (context.mounted) showMessage(context, 'Token disalin.');
                    },
                  ),
                  const SizedBox(height: 10),
                  BusyButton(
                    label: 'Rotasi sekarang',
                    icon: Icons.autorenew,
                    outlined: true,
                    onPressed: () async {
                      if (!await confirm(context, title: 'Rotasi token?', message: 'Token lama langsung tidak berlaku.')) return;
                      await ref.read(apiProvider).post('/admin/registration-token/rotate');
                      ref.invalidate(tokenProvider);
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Token juga dirotasi otomatis setiap Senin.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Brand.muted, fontSize: 13),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────── Warga: rumah & permohonan
class ResidentsAdminPage extends ConsumerStatefulWidget {
  const ResidentsAdminPage({super.key});
  @override
  ConsumerState<ResidentsAdminPage> createState() => _ResidentsAdminPageState();
}

class _ResidentsAdminPageState extends ConsumerState<ResidentsAdminPage> {
  String _q = '';
  bool _arsip = false;

  HouseQuery get _query => (q: _q, arsip: _arsip, page: 1);

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 3,
    child: Column(
      children: [
        const Material(
          color: Colors.white,
          child: TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelColor: Brand.navy,
            indicatorColor: Brand.yellow,
            indicatorWeight: 3,
            tabs: [
              Tab(text: 'Rumah & keluarga'),
              Tab(text: 'Permohonan akun'),
              Tab(text: 'Akun warga'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            children: [
              ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  TextField(
                    decoration: const InputDecoration(prefixIcon: Icon(Icons.search), labelText: 'Cari kode rumah / jalan'),
                    onSubmitted: (value) => setState(() => _q = value.trim()),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      FilterChip(label: const Text('Rumah arsip'), selected: _arsip, onSelected: (value) => setState(() => _arsip = value)),
                      TextButton(
                        onPressed: () async {
                          final created = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const HouseCreatePage()));
                          if (created == true) ref.invalidate(housesProvider(_query));
                        },
                        child: const Text('Rumah baru'),
                      ),
                      TextButton(
                        onPressed: () => Navigator.push<void>(context, MaterialPageRoute(builder: (_) => const FamiliesAdminPage())),
                        child: const Text('Daftar keluarga'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AsyncView(
                    value: ref.watch(housesProvider(_query)),
                    onRetry: () => ref.invalidate(housesProvider(_query)),
                    builder: (page) => page.rows.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.symmetric(vertical: 24),
                            child: Text(
                              _arsip ? 'Tidak ada rumah arsip.' : 'Belum ada rumah terdaftar.',
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: Brand.muted, height: 1.35),
                            ),
                          )
                        : AppendedPage(
                            key: ValueKey('rumah|$_q|$_arsip'),
                            first: page,
                            load: (n) => ref.read(housesProvider((q: _q, arsip: _arsip, page: n)).future),
                            builder: (context, list) => Column(
                              children: [
                                for (final h in list)
                                  Card(
                                    margin: const EdgeInsets.only(bottom: 8),
                                    child: ListTile(
                                      title: Text('${h['kode_rumah']} • ${h['alamat']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                      // Status di bawah subjudul (bukan trailing) agar judul alamat tetap lebar pada skala teks besar.
                                      subtitle: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            h['keluarga'] == null
                                                ? 'Belum ada keluarga aktif'
                                                : '${(h['keluarga'] as Map)['kepala'] ?? '-'} • ${(h['keluarga'] as Map)['jumlah_anggota']} anggota',
                                          ),
                                          const SizedBox(height: 6),
                                          Wrap(
                                            spacing: 6,
                                            runSpacing: 6,
                                            children: [
                                              if (h['archived_at'] != null) const StatusChip('nonaktif', label: 'Arsip'),
                                              if (h['keluarga'] != null)
                                                StatusChip(
                                                  (h['keluarga'] as Map)['status'] == 'terverifikasi' ? 'aktif' : 'menunggu',
                                                  label: (h['keluarga'] as Map)['status'] == 'terverifikasi' ? 'Terverifikasi' : 'Perlu verifikasi',
                                                ),
                                            ],
                                          ),
                                        ],
                                      ),
                                      onTap: () async {
                                        await Navigator.push(
                                          context,
                                          MaterialPageRoute(
                                            builder: (_) => HouseAdminPage(
                                              summary: h,
                                              onRecordPayment: (house) async {
                                                await Navigator.push(context, MaterialPageRoute(builder: (_) => RecordPaymentPage(house: house)));
                                              },
                                            ),
                                          ),
                                        );
                                        ref.invalidate(housesProvider(_query));
                                      },
                                    ),
                                  ),
                              ],
                            ),
                          ),
                  ),
                ],
              ),
              const _RequestsView(),
              const AccountsAdminView(),
            ],
          ),
        ),
      ],
    ),
  );
}

class _RequestsView extends ConsumerStatefulWidget {
  const _RequestsView();

  @override
  ConsumerState<_RequestsView> createState() => _RequestsViewState();
}

class _RequestsViewState extends ConsumerState<_RequestsView> {
  String _status = 'diajukan';

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: () async => ref.invalidate(requestsProvider(_status)),
    child: AsyncView(
      value: ref.watch(requestsProvider(_status)),
      onRetry: () => ref.invalidate(requestsProvider(_status)),
      builder: (list) {
        final filters = Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final choice in const [('Menunggu', 'diajukan'), ('Disetujui', 'disetujui'), ('Ditolak', 'ditolak')])
              ChoiceChip(label: Text(choice.$1), selected: _status == choice.$2, onSelected: (_) => setState(() => _status = choice.$2)),
          ],
        );
        if (list.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              filters,
              const SizedBox(height: 24),
              Text(
                _status == 'diajukan' ? 'Tidak ada permohonan menunggu.' : 'Tidak ada permohonan pada saringan ini.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Brand.muted, height: 1.35),
              ),
            ],
          );
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            filters,
            const SizedBox(height: 12),
            for (final p in list)
              Card(
                margin: const EdgeInsets.only(bottom: 10),
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${p['nama_pemohon']} (${p['hubungan_keluarga'] ?? '-'})', style: Theme.of(context).textTheme.titleMedium),
                      Text('${p['email']}', style: const TextStyle(color: Brand.muted)),
                      const SizedBox(height: 6),
                      Text('Alamat diajukan: ${p['alamat_diajukan']}'),
                      Text(
                        p['rumah_cocok'] == null
                            ? 'Rumah tidak cocok dengan data — tolak atau minta pemohon mendaftar ulang.'
                            : 'Cocok dengan rumah ${(p['rumah_cocok'] as Map)['kode_rumah']}',
                        style: TextStyle(color: p['rumah_cocok'] == null ? Brand.danger : Brand.success),
                      ),
                      if (_status == 'diajukan') ...[
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: BusyButton(
                                label: 'Tolak',
                                outlined: true,
                                onPressed: () async {
                                  final reason = await askText(context, title: 'Alasan penolakan', label: 'Alasan (wajib)');
                                  if (!context.mounted || reason == null) return;
                                  if (reason.isEmpty) {
                                    showMessage(context, 'Alasan wajib diisi.', error: true);
                                    return;
                                  }
                                  try {
                                    await ref.read(apiProvider).post('/admin/additional-account-requests/${p['id']}/reject', data: {'alasan': reason});
                                    ref.invalidate(requestsProvider(_status));
                                  } on ApiException catch (error) {
                                    if (context.mounted) showMessage(context, error.display, error: true);
                                  }
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: BusyButton(
                                label: 'Setujui',
                                onPressed: p['rumah_cocok'] == null
                                    ? null
                                    : () async {
                                        try {
                                          await ref.read(apiProvider).post('/admin/additional-account-requests/${p['id']}/approve', data: {});
                                          ref.invalidate(requestsProvider(_status));
                                          if (context.mounted) showMessage(context, 'Akun disetujui sebagai warga.');
                                        } on ApiException catch (error) {
                                          if (context.mounted) showMessage(context, error.display, error: true);
                                        }
                                      },
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );
}

// ─────────────────────────────────────────── Keuangan
class FinanceAdminPage extends ConsumerWidget {
  const FinanceAdminPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => DefaultTabController(
    length: 3,
    child: Column(
      children: [
        const Material(
          color: Colors.white,
          child: TabBar(
            labelColor: Brand.navy,
            indicatorColor: Brand.yellow,
            indicatorWeight: 3,
            tabs: [
              Tab(text: 'Pembayaran'),
              Tab(text: 'Kas'),
              Tab(text: 'Rekap'),
            ],
          ),
        ),
        Expanded(child: TabBarView(children: [_PaymentsView(), const _AdminCashView(), const DuesRecapView()])),
      ],
    ),
  );
}

class _PaymentsView extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: Brand.sky,
    floatingActionButton: FloatingActionButton.extended(
      backgroundColor: Brand.yellow,
      foregroundColor: Brand.navy,
      icon: const Icon(Icons.point_of_sale),
      label: const Text('Catat iuran'),
      onPressed: () async {
        await Navigator.push(context, MaterialPageRoute(builder: (_) => const RecordPaymentPage()));
        ref.invalidate(paymentsProvider);
      },
    ),
    body: RefreshIndicator(
      onRefresh: () async => ref.invalidate(paymentsProvider),
      child: AsyncView(
        value: ref.watch(paymentsProvider),
        onRetry: () => ref.invalidate(paymentsProvider),
        builder: (list) => list.isEmpty
            ? ListView(
                children: const [EmptyState(icon: Icons.receipt_long_outlined, title: 'Belum ada pembayaran tercatat')],
              )
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                children: [
                  for (final p in list)
                    Card(
                      key: ValueKey('bayar-${p['id']}'),
                      margin: const EdgeInsets.only(bottom: 8),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ListTile(
                            title: Text('${p['nomor_bukti']} • ${(p['rumah'] as Map?)?['kode_rumah']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                            subtitle: Text(
                              '${(p['periode'] as List).map((x) => periode('$x')).join(', ')}\n${rupiah(p['total_bayar'] as num)} • dibayar ${p['tanggal_bayar']}',
                            ),
                            isThreeLine: true,
                          ),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 8, 8),
                            child: Wrap(
                              alignment: WrapAlignment.spaceBetween,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              spacing: 8,
                              children: [
                                StatusChip('${p['status']}'),
                                if (p['status'] == 'tercatat')
                                  TextButton.icon(
                                    key: ValueKey('batalkan-${p['id']}'),
                                    onPressed: () async {
                                      final done = await reverseEntry(
                                        context,
                                        ref,
                                        path: '/admin/dues-payments/${p['id']}/reverse',
                                        title: 'Batalkan pembayaran ${p['nomor_bukti']}?',
                                        done: 'Pembayaran dibatalkan',
                                        effect:
                                            'Tagihan ${(p['periode'] as List).map((x) => periode('$x')).join(', ')} kembali belum dibayar dan kas dikurangi ${rupiah(p['total_bayar'] as num)}.',
                                      );
                                      if (done) ref.invalidate(paymentsProvider);
                                    },
                                    icon: const Icon(Icons.undo),
                                    label: const Text('Batalkan'),
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  const Text(
                    'Pembatalan mencatat pembalikan dengan alasan; data asal tidak dihapus.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Brand.muted, fontSize: 13),
                  ),
                ],
              ),
      ),
    ),
  );
}

class _AdminCashView extends ConsumerWidget {
  const _AdminCashView();

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: Brand.sky,
    floatingActionButton: FloatingActionButton.extended(
      backgroundColor: Brand.yellow,
      foregroundColor: Brand.navy,
      icon: const Icon(Icons.add),
      label: const Text('Catat kas'),
      onPressed: () async {
        await Navigator.push(context, MaterialPageRoute(builder: (_) => const CashEntryPage()));
        ref.invalidate(cashProvider);
      },
    ),
    body: CashView(
      onReverse: (k) async {
        final done = await reverseEntry(
          context,
          ref,
          path: '/admin/cash-transactions/${k['id']}/reverse',
          title: 'Balik transaksi kas?',
          done: 'Transaksi kas dibalik',
          effect: '${cashJenisLabel('${k['jenis']}')} ${rupiah((k['nominal'] as num).abs())} (${k['kategori'] ?? '-'}) dinetralkan dengan baris pembalikan.',
        );
        if (done) ref.invalidate(cashProvider);
      },
    ),
  );
}

/// Pencatatan pembayaran iuran offline: pilih rumah → centang bulan belum bayar →
/// pratinjau total dari snapshot server → konfirmasi. Idempotency-Key tetap selama layar terbuka.
class RecordPaymentPage extends ConsumerStatefulWidget {
  const RecordPaymentPage({super.key, this.house});
  final Json? house;
  @override
  ConsumerState<RecordPaymentPage> createState() => _RecordPaymentPageState();
}

class _RecordPaymentPageState extends ConsumerState<RecordPaymentPage> {
  Json? _house;
  final Set<String> _selected = {};
  final _note = TextEditingController();
  DateTime _paidOn = toJakarta(DateTime.now().toUtc());
  final String _key = newKey();

  @override
  void initState() {
    super.initState();
    _house = widget.house;
  }

  @override
  Widget build(BuildContext context) {
    final houses = ref.watch(housesProvider((q: '', arsip: false, page: 1)));
    return Scaffold(
      appBar: AppBar(title: const Text('Catat pembayaran iuran')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: Brand.warningBg, borderRadius: BorderRadius.circular(12)),
            child: const Text('Catat hanya setelah uang diterima langsung (offline). Tidak ada pembayaran online.'),
          ),
          const SizedBox(height: 14),
          AsyncView(
            value: houses,
            onRetry: () => ref.invalidate(housesProvider((q: '', arsip: false, page: 1))),
            builder: (page) => AppendedPage(
              first: page,
              load: (n) => ref.read(housesProvider((q: '', arsip: false, page: n)).future),
              builder: (context, list) {
                final selected = _house == null ? null : '${_house!['id']}';
                final current = selected != null && list.any((h) => '${h['id']}' == selected) ? selected : null;
                return DropdownButtonFormField<String>(
                  initialValue: current,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Rumah'),
                  items: [
                    for (final h in list)
                      DropdownMenuItem(
                        value: '${h['id']}',
                        child: Text('${h['kode_rumah']} • ${h['alamat']}', overflow: TextOverflow.ellipsis),
                      ),
                  ],
                  onChanged: (v) => setState(() {
                    _house = list.firstWhere((h) => '${h['id']}' == v);
                    _selected.clear();
                  }),
                );
              },
            ),
          ),
          if (_house != null) ...[
            const SizedBox(height: 16),
            Text('Pilih bulan yang dibayar', style: Theme.of(context).textTheme.titleMedium),
            AsyncView(
              value: ref.watch(houseDuesProvider('${_house!['id']}')),
              onRetry: () => ref.invalidate(houseDuesProvider('${_house!['id']}')),
              builder: (d) {
                final unpaid = (d['tagihan'] as List).cast<Map>().map((m) => Tagihan(Json.from(m))).where((t) => !t.lunas).toList()
                  ..sort((a, b) => a.periode.compareTo(b.periode));
                final total = unpaid.where((t) => _selected.contains(t.periode)).fold<int>(0, (s, t) => s + t.nominal);
                if (unpaid.isEmpty) return const Padding(padding: EdgeInsets.all(16), child: Text('Semua tagihan rumah ini sudah lunas.'));
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final t in unpaid)
                      CheckboxListTile(
                        value: _selected.contains(t.periode),
                        onChanged: (v) => setState(() => v == true ? _selected.add(t.periode) : _selected.remove(t.periode)),
                        title: Text(periode(t.periode)),
                        subtitle: Text(rupiah(t.nominal)),
                      ),
                    const SizedBox(height: 8),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.calendar_today_outlined),
                      title: Text('Tanggal diterima: ${_paidOn.day}/${_paidOn.month}/${_paidOn.year}'),
                      onTap: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _paidOn,
                          firstDate: DateTime(2025),
                          lastDate: toJakarta(DateTime.now().toUtc()),
                        );
                        if (picked != null) setState(() => _paidOn = picked);
                      },
                    ),
                    TextField(
                      controller: _note,
                      decoration: const InputDecoration(labelText: 'Catatan (opsional)'),
                    ),
                    const SizedBox(height: 16),
                    SectionCard(
                      child: Row(
                        children: [
                          const Expanded(child: Text('Total sesuai tagihan')),
                          Text(
                            rupiah(total),
                            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Brand.navy),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    BusyButton(
                      label: 'Simpan pembayaran',
                      icon: Icons.check,
                      onPressed: _selected.isEmpty
                          ? null
                          : () async {
                              final months = _selected.toList()..sort();
                              if (!await confirm(
                                context,
                                title: 'Konfirmasi pembayaran',
                                message: 'Rumah ${_house!['kode_rumah']}\n${months.map(periode).join(', ')}\nTotal ${rupiah(total)}',
                                yes: 'Simpan',
                              )) {
                                return;
                              }
                              try {
                                final res = await ref
                                    .read(apiProvider)
                                    .post(
                                      '/admin/dues-payments',
                                      idempotencyKey: _key,
                                      data: {
                                        'rumah_id': _house!['id'],
                                        'periode': months,
                                        'nominal': total,
                                        'tanggal_bayar':
                                            '${_paidOn.year}-${_paidOn.month.toString().padLeft(2, '0')}-${_paidOn.day.toString().padLeft(2, '0')}',
                                        'catatan': _note.text.trim().isEmpty ? null : _note.text.trim(),
                                      },
                                    );
                                ref.invalidate(houseDuesProvider('${_house!['id']}'));
                                if (context.mounted) {
                                  showMessage(context, 'Tercatat: ${(res as Map)['nomor_bukti']}');
                                  Navigator.pop(context);
                                }
                              } on ApiException catch (e) {
                                if (e.isUnknownOutcome) {
                                  // Kunci yang sama dipakai saat retry sehingga server memutar ulang hasil, tidak dobel.
                                  if (context.mounted) showMessage(context, '${e.display} Tekan Simpan lagi untuk memeriksa (aman, tidak dobel).', error: true);
                                } else {
                                  ref.invalidate(houseDuesProvider('${_house!['id']}'));
                                  if (context.mounted) showMessage(context, e.display, error: true);
                                }
                              }
                            },
                    ),
                  ],
                );
              },
            ),
          ],
        ],
      ),
    );
  }
}

class CashEntryPage extends ConsumerStatefulWidget {
  const CashEntryPage({super.key});
  @override
  ConsumerState<CashEntryPage> createState() => _CashEntryPageState();
}

class _CashEntryPageState extends ConsumerState<CashEntryPage> {
  String _jenis = 'pengeluaran';
  final _nominal = TextEditingController();
  final _kategori = TextEditingController();
  final _ket = TextEditingController();
  final String _key = newKey();

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Catat kas')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'pengeluaran', label: Text('Pengeluaran')),
            ButtonSegment(value: 'pemasukan', label: Text('Pemasukan lain')),
            ButtonSegment(value: 'saldo_awal', label: Text('Saldo awal')),
          ],
          selected: {_jenis},
          onSelectionChanged: (s) => setState(() => _jenis = s.first),
        ),
        const SizedBox(height: 8),
        const Text('Iuran warga dicatat lewat "Catat iuran", bukan di sini.', style: TextStyle(color: Brand.muted, fontSize: 13)),
        const SizedBox(height: 14),
        TextField(
          controller: _nominal,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(labelText: 'Nominal (Rp)'),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _kategori,
          decoration: const InputDecoration(labelText: 'Kategori', hintText: 'mis. Kebersihan, Keamanan, Donasi'),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _ket,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Keterangan'),
        ),
        const SizedBox(height: 20),
        BusyButton(
          label: 'Simpan',
          onPressed: () async {
            final n = int.tryParse(_nominal.text) ?? 0;
            if (n <= 0 || _kategori.text.trim().isEmpty || _ket.text.trim().length < 5) {
              showMessage(context, 'Lengkapi nominal, kategori, dan keterangan (minimal 5 karakter).', kind: AlertKind.warning, title: 'Belum lengkap');
              return;
            }
            if (!await confirm(
              context,
              title: 'Simpan transaksi kas?',
              message: '${cashJenisLabel(_jenis)} ${rupiah(n)}\nTransaksi tidak dapat diedit; koreksi melalui pembalikan.',
            )) {
              return;
            }
            final now = toJakarta(DateTime.now().toUtc());
            final body = {
              'jenis': _jenis,
              'nominal': n,
              'tanggal': '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}',
              'kategori': _kategori.text.trim(),
              'keterangan': _ket.text.trim(),
            };
            Future<void> post([Map<String, Object> extra = const {}]) =>
                ref.read(apiProvider).post('/admin/cash-transactions', idempotencyKey: _key, data: {...body, ...extra});
            try {
              try {
                await post();
              } on ApiException catch (e) {
                if (e.code != negativeBalanceCode || !context.mounted) rethrow;
                // Saldo boleh negatif, tetapi pengurus wajib sadar dan menjelaskan di keterangan.
                final sure = await confirm(
                  context,
                  title: 'Saldo kas akan negatif',
                  message: '${e.message}\n\nKeterangan: ${_ket.text.trim()}',
                  yes: 'Tetap catat',
                  danger: true,
                  icon: Icons.warning_amber_rounded,
                );
                if (!sure || !context.mounted) return;
                // Penolakan tidak menyimpan reservasi kunci, jadi kunci yang sama aman dipakai ulang.
                await post({'konfirmasi_saldo_negatif': true});
              }
              if (context.mounted) {
                await showResultDialog(context, title: 'Transaksi kas tercatat', message: '${cashJenisLabel(_jenis)} ${rupiah(n)} tersimpan.');
                if (context.mounted) Navigator.pop(context);
              }
            } on ApiException catch (e) {
              if (context.mounted) showApiError(context, e);
            }
          },
        ),
      ],
    ),
  );
}

// ─────────────────────────────────────────── Lainnya: agenda & pengumuman
class MoreAdminPage extends ConsumerWidget {
  const MoreAdminPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => ListView(
    padding: const EdgeInsets.all(16),
    children: [
      SectionCard(
        title: 'Komunitas',
        child: Column(
          children: [
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.fact_check_outlined, color: Brand.navy),
              title: const Text('Usulan kegiatan'),
              subtitle: const Text('Tinjau usulan warga. Hanya yang disetujui masuk agenda resmi.'),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ActivityProposalListPage(admin: true))),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.event_note_outlined, color: Brand.navy),
              title: const Text('Agenda & pengumuman'),
              subtitle: const Text('Buat, ubah, atau arsipkan. Warga menerima notifikasi dan pengingat H-1'),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunityAdminPage())),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.public, color: Brand.navy),
              title: const Text('Konten website'),
              subtitle: const Text('Artikel, album, fasilitas, dan profil RT. Tampil setelah diterbitkan'),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ContentAdminPage())),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.price_change_outlined, color: Brand.navy),
              title: const Text('Tarif iuran'),
              subtitle: const Text('Riwayat tarif dan tarif baru untuk bulan berikutnya'),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const DuesRatesPage())),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.key_outlined, color: Brand.navy),
              title: const Text('Token registrasi'),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TokenPage())),
            ),
          ],
        ),
      ),
    ],
  );
}

/// Kelola agenda & pengumuman (M10): daftar aktif, buat, ubah, arsipkan. Arsip menghilangkan dari
/// warga tetapi riwayat dan notifikasi lama tetap; tautan notifikasi ke item terarsip aman (404).
class CommunityAdminPage extends ConsumerWidget {
  const CommunityAdminPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final agendas = ref.watch(agendasProvider);
    final news = ref.watch(announcementsProvider);
    Future<void> archive(String path, String what, String name, void Function() refresh) async {
      final ok = await confirm(
        context,
        title: 'Arsipkan $what?',
        message: '"$name" tidak lagi tampil untuk warga. Riwayat dan notifikasi lama tetap tersimpan.',
        yes: 'Arsipkan',
        danger: true,
        icon: Icons.archive_outlined,
      );
      if (!ok) return;
      try {
        await ref.read(apiProvider).post(path);
        refresh();
        if (context.mounted) showMessage(context, '${what[0].toUpperCase()}${what.substring(1)} diarsipkan.');
      } on ApiException catch (e) {
        if (context.mounted) showApiError(context, e);
      }
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Agenda & pengumuman')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(agendasProvider);
          ref.invalidate(announcementsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            BusyButton(
              label: 'Buat agenda',
              icon: Icons.event_note_outlined,
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AgendaFormPage())),
            ),
            const SizedBox(height: 8),
            BusyButton(
              label: 'Terbitkan pengumuman',
              icon: Icons.campaign_outlined,
              outlined: true,
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AnnouncementFormPage())),
            ),
            const SizedBox(height: 20),
            Text('Agenda mendatang', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            AsyncView(
              value: agendas,
              onRetry: () => ref.invalidate(agendasProvider),
              builder: (list) => list.isEmpty
                  ? const EmptyState(icon: Icons.event_busy_outlined, title: 'Belum ada agenda mendatang')
                  : Column(
                      children: [
                        for (final a in list)
                          Card(
                            child: ListTile(
                              key: ValueKey('admin-agenda-${a['id']}'),
                              title: Text('${a['nama']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              subtitle: Text('${tanggal(parseTime(a['mulai_at']), jam: true, hari: true)} WIB\n${a['lokasi']}'),
                              isThreeLine: true,
                              trailing: PopupMenuButton<String>(
                                tooltip: 'Aksi agenda',
                                onSelected: (v) => v == 'ubah'
                                    ? Navigator.push(context, MaterialPageRoute(builder: (_) => AgendaFormPage(initial: a)))
                                    : archive('/admin/agendas/${a['id']}/archive', 'agenda', '${a['nama']}', () => ref.invalidate(agendasProvider)),
                                itemBuilder: (_) => const [
                                  PopupMenuItem(value: 'ubah', child: Text('Ubah')),
                                  PopupMenuItem(value: 'arsip', child: Text('Arsipkan')),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
            const SizedBox(height: 20),
            Text('Pengumuman', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            AsyncView(
              value: news,
              onRetry: () => ref.invalidate(announcementsProvider),
              builder: (list) => list.isEmpty
                  ? const EmptyState(icon: Icons.campaign_outlined, title: 'Belum ada pengumuman')
                  : Column(
                      children: [
                        for (final n in list)
                          Card(
                            child: ListTile(
                              key: ValueKey('admin-pengumuman-${n['id']}'),
                              title: Text('${n['judul']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              subtitle: Text(tanggal(parseTime(n['published_at']), jam: true)),
                              trailing: PopupMenuButton<String>(
                                tooltip: 'Aksi pengumuman',
                                onSelected: (v) => v == 'ubah'
                                    ? Navigator.push(context, MaterialPageRoute(builder: (_) => AnnouncementFormPage(initial: n)))
                                    : archive(
                                        '/admin/announcements/${n['id']}/archive',
                                        'pengumuman',
                                        '${n['judul']}',
                                        () => ref.invalidate(announcementsProvider),
                                      ),
                                itemBuilder: (_) => const [
                                  PopupMenuItem(value: 'ubah', child: Text('Ubah')),
                                  PopupMenuItem(value: 'arsip', child: Text('Arsipkan')),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Validasi lokal agenda (server tetap memvalidasi). [nowJakarta] = jam dinding WIB saat ini.
Map<String, String> validateAgenda({
  required String nama,
  required String lokasi,
  required String deskripsi,
  required DateTime mulai,
  DateTime? selesai,
  required DateTime nowJakarta,
  bool requireFuture = true,
}) => {
  if (nama.trim().length < 3) 'nama': 'Nama kegiatan minimal 3 karakter.',
  if (lokasi.trim().length < 3) 'lokasi': 'Lokasi minimal 3 karakter.',
  if (deskripsi.trim().isEmpty) 'deskripsi': 'Deskripsi wajib diisi.',
  if (requireFuture && !mulai.isAfter(nowJakarta)) 'mulai_at': 'Waktu mulai harus setelah saat ini.',
  if (selesai != null && !selesai.isAfter(mulai)) 'selesai_at': 'Waktu selesai harus setelah waktu mulai.',
};

class AgendaFormPage extends ConsumerStatefulWidget {
  const AgendaFormPage({super.key, this.initial, this.now});
  final Json? initial;

  /// Jam dinding WIB saat ini; dapat diinjeksi untuk tes.
  final DateTime Function()? now;

  @override
  ConsumerState<AgendaFormPage> createState() => _AgendaFormPageState();
}

class _AgendaFormPageState extends ConsumerState<AgendaFormPage> {
  late final _nama = TextEditingController(text: '${widget.initial?['nama'] ?? ''}');
  late final _lokasi = TextEditingController(text: '${widget.initial?['lokasi'] ?? ''}');
  late final _desk = TextEditingController(text: '${widget.initial?['deskripsi'] ?? ''}');
  late DateTime _start;
  DateTime? _end;
  Map<String, String> _errors = {};

  DateTime _nowJakarta() => widget.now?.call() ?? toJakarta(DateTime.now().toUtc());
  bool get _editing => widget.initial != null;

  @override
  void initState() {
    super.initState();
    final s = parseTime(widget.initial?['mulai_at']);
    final e = parseTime(widget.initial?['selesai_at']);
    final now = _nowJakarta();
    _start = s == null ? DateTime(now.year, now.month, now.day + 1, 8) : toJakarta(s);
    _end = e == null ? null : toJakarta(e);
  }

  String _fmt(DateTime t) => '${t.day}/${t.month}/${t.year} ${t.hour.toString().padLeft(2, '0')}.${t.minute.toString().padLeft(2, '0')} WIB';

  Future<DateTime?> _pick(DateTime initial) async {
    final now = _nowJakarta();
    final d = await showDatePicker(
      context: context,
      initialDate: initial.isBefore(now) ? now : initial,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: now.add(const Duration(days: 365)),
    );
    if (d == null || !mounted) return null;
    final t = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(initial));
    return DateTime(d.year, d.month, d.day, t?.hour ?? initial.hour, t?.minute ?? initial.minute);
  }

  Future<void> _submit() async {
    final moved = !_editing || toJakarta(parseTime(widget.initial!['mulai_at'])!) != _start;
    final errors = validateAgenda(
      nama: _nama.text,
      lokasi: _lokasi.text,
      deskripsi: _desk.text,
      mulai: _start,
      selesai: _end,
      nowJakarta: _nowJakarta(),
      requireFuture: moved,
    );
    setState(() => _errors = errors);
    if (errors.isNotEmpty) {
      showMessage(context, 'Periksa isian yang ditandai.', kind: AlertKind.warning, title: 'Belum lengkap');
      return;
    }
    final ok = await confirm(
      context,
      title: _editing ? 'Simpan perubahan agenda?' : 'Terbitkan agenda?',
      message: _editing
          ? '${_nama.text.trim()} — ${_fmt(_start)}. Bila waktu atau lokasi berubah, semua warga diberi tahu.'
          : '${_nama.text.trim()} — ${_fmt(_start)} di ${_lokasi.text.trim()}. Semua warga aktif menerima notifikasi dan pengingat H-1.',
      yes: _editing ? 'Simpan' : 'Terbitkan',
      icon: Icons.event_available,
    );
    if (!ok || !mounted) return;
    // Input dipahami sebagai jam dinding WIB, dikirim UTC dengan zona eksplisit (Z).
    final body = {
      'nama': _nama.text.trim(),
      'lokasi': _lokasi.text.trim(),
      'deskripsi': _desk.text.trim(),
      'mulai_at': jakartaWallToUtc(_start).toIso8601String(),
      'selesai_at': _end == null ? null : jakartaWallToUtc(_end!).toIso8601String(),
    };
    try {
      final api = ref.read(apiProvider);
      _editing ? await api.put('/admin/agendas/${widget.initial!['id']}', data: body) : await api.post('/admin/agendas', data: body);
      ref.invalidate(agendasProvider);
      if (!mounted) return;
      await showResultDialog(
        context,
        title: _editing ? 'Agenda diperbarui' : 'Agenda diterbitkan',
        message: _editing ? 'Perubahan tersimpan.' : 'Warga menerima notifikasi sekarang dan pengingat sehari sebelum kegiatan.',
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = {for (final f in e.fields.entries) f.key: f.value.first});
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_editing ? 'Ubah agenda' : 'Agenda baru')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          key: const ValueKey('agenda-nama'),
          controller: _nama,
          maxLength: 160,
          decoration: InputDecoration(labelText: 'Nama kegiatan', errorText: _errors['nama']),
        ),
        const SizedBox(height: 8),
        TextField(
          key: const ValueKey('agenda-lokasi'),
          controller: _lokasi,
          maxLength: 200,
          decoration: InputDecoration(labelText: 'Lokasi', errorText: _errors['lokasi']),
        ),
        const SizedBox(height: 8),
        ListTile(
          key: const ValueKey('agenda-mulai'),
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.schedule),
          title: Text('Mulai: ${_fmt(_start)}'),
          subtitle: _errors['mulai_at'] == null ? null : Text(_errors['mulai_at']!, style: const TextStyle(color: Brand.danger)),
          onTap: () async {
            final t = await _pick(_start);
            if (t != null) setState(() => _start = t);
          },
        ),
        ListTile(
          key: const ValueKey('agenda-selesai'),
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.timer_off_outlined),
          title: Text(_end == null ? 'Selesai: tidak ditentukan (opsional)' : 'Selesai: ${_fmt(_end!)}'),
          subtitle: _errors['selesai_at'] == null ? null : Text(_errors['selesai_at']!, style: const TextStyle(color: Brand.danger)),
          trailing: _end == null
              ? null
              : IconButton(tooltip: 'Hapus waktu selesai', icon: const Icon(Icons.clear), onPressed: () => setState(() => _end = null)),
          onTap: () async {
            final t = await _pick(_end ?? _start.add(const Duration(hours: 2)));
            if (t != null) setState(() => _end = t);
          },
        ),
        const SizedBox(height: 8),
        TextField(
          key: const ValueKey('agenda-deskripsi'),
          controller: _desk,
          maxLines: 4,
          maxLength: 5000,
          decoration: InputDecoration(labelText: 'Deskripsi', alignLabelWithHint: true, errorText: _errors['deskripsi']),
        ),
        const SizedBox(height: 12),
        BusyButton(label: _editing ? 'Simpan perubahan' : 'Terbitkan agenda', icon: Icons.send_outlined, onPressed: _submit),
      ],
    ),
  );
}

class AnnouncementFormPage extends ConsumerStatefulWidget {
  const AnnouncementFormPage({super.key, this.initial});
  final Json? initial;
  @override
  ConsumerState<AnnouncementFormPage> createState() => _AnnouncementFormPageState();
}

class _AnnouncementFormPageState extends ConsumerState<AnnouncementFormPage> {
  late final _judul = TextEditingController(text: '${widget.initial?['judul'] ?? ''}');
  late final _isi = TextEditingController(text: '${widget.initial?['isi'] ?? ''}');
  Map<String, String> _errors = {};
  bool get _editing => widget.initial != null;

  Future<void> _submit() async {
    final errors = {if (_judul.text.trim().isEmpty) 'judul': 'Judul wajib diisi.', if (_isi.text.trim().isEmpty) 'isi': 'Isi pengumuman wajib diisi.'};
    setState(() => _errors = errors);
    if (errors.isNotEmpty) {
      showMessage(context, 'Periksa isian yang ditandai.', kind: AlertKind.warning, title: 'Belum lengkap');
      return;
    }
    final ok = await confirm(
      context,
      title: _editing ? 'Simpan perubahan pengumuman?' : 'Terbitkan pengumuman?',
      message: _editing
          ? 'Perubahan langsung tampil untuk warga. Notifikasi tidak dikirim ulang.'
          : '"${_judul.text.trim()}" tampil untuk warga dan dikirim sebagai notifikasi ke semua warga aktif.',
      yes: _editing ? 'Simpan' : 'Terbitkan',
      icon: Icons.campaign_outlined,
    );
    if (!ok || !mounted) return;
    final body = {'judul': _judul.text.trim(), 'isi': _isi.text.trim()};
    try {
      final api = ref.read(apiProvider);
      _editing ? await api.put('/admin/announcements/${widget.initial!['id']}', data: body) : await api.post('/admin/announcements', data: body);
      ref.invalidate(announcementsProvider);
      if (!mounted) return;
      await showResultDialog(
        context,
        title: _editing ? 'Pengumuman diperbarui' : 'Pengumuman diterbitkan',
        message: _editing ? 'Perubahan tersimpan.' : 'Warga menerima notifikasi sekarang.',
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = {for (final f in e.fields.entries) f.key: f.value.first});
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_editing ? 'Ubah pengumuman' : 'Pengumuman baru')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          key: const ValueKey('pengumuman-judul'),
          controller: _judul,
          maxLength: 160,
          decoration: InputDecoration(labelText: 'Judul', errorText: _errors['judul']),
        ),
        const SizedBox(height: 8),
        TextField(
          key: const ValueKey('pengumuman-isi'),
          controller: _isi,
          maxLines: 8,
          maxLength: 10000,
          decoration: InputDecoration(labelText: 'Isi pengumuman', alignLabelWithHint: true, errorText: _errors['isi']),
        ),
        const SizedBox(height: 12),
        BusyButton(label: _editing ? 'Simpan perubahan' : 'Terbitkan', icon: Icons.send_outlined, onPressed: _submit),
      ],
    ),
  );
}
