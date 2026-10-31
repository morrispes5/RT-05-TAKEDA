import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import 'finance_actions.dart';
import 'pengurus_pages.dart' show newKey;
import 'house_finance.dart';

/// M11 pengurus: rekap iuran bulanan dan tarif per periode. Angka selalu dari server;
/// fungsi murni di bawah hanya untuk navigasi bulan dan validasi form sebelum dikirim.

/// "2026-10" + 1 → "2026-11".
String shiftMonth(String yyyyMm, int delta) {
  final y = int.parse(yyyyMm.substring(0, 4));
  final m = int.parse(yyyyMm.substring(5, 7));
  final d = DateTime.utc(y, m + delta);
  return '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}';
}

/// Bulan berjalan menurut Asia/Jakarta.
String currentPeriod(DateTime nowUtc) {
  final j = toJakarta(nowUtc);
  return '${j.year.toString().padLeft(4, '0')}-${j.month.toString().padLeft(2, '0')}';
}

/// Tarif baru hanya untuk bulan depan s.d. 24 bulan ke depan (bulan berjalan sudah ditagih).
List<String> ratePeriods(String current) => [for (var i = 1; i <= 24; i++) shiftMonth(current, i)];

Map<String, String> validateRate({required String nominal, required String? mulai, required String alasan, required Set<String> taken}) {
  final n = int.tryParse(nominal.replaceAll('.', '').trim());
  return {
    if (n == null || n < 1000 || n > 10000000) 'nominal': 'Nominal Rp1.000–Rp10.000.000, tanpa titik/koma.',
    if (mulai == null) 'mulai_periode': 'Pilih bulan mulai berlaku.',
    if (mulai != null && taken.contains(mulai)) 'mulai_periode': 'Bulan ini sudah punya tarif. Pilih bulan lain.',
    if (alasan.trim().length < 5) 'alasan': 'Tulis alasan perubahan (minimal 5 karakter).',
  };
}

final duesRecapProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, p) async => Json.from(await accountApi(ref).get('/admin/dues-recap', query: {'periode': p}) as Map),
);

final duesRatesProvider = FutureProvider.autoDispose<List<Json>>(
  (ref) async => (await accountApi(ref).get('/admin/dues-rates') as List).cast<Map>().map(Json.from).toList(),
);

/// Tab Keuangan → Rekap: per bulan, lunas/belum, tunggakan sebelumnya, per kode rumah.
class DuesRecapView extends ConsumerStatefulWidget {
  const DuesRecapView({super.key, this.now});
  final DateTime Function()? now;
  @override
  ConsumerState<DuesRecapView> createState() => _DuesRecapViewState();
}

class _DuesRecapViewState extends ConsumerState<DuesRecapView> {
  final _generateKey = newKey();
  late String _period = currentPeriod((widget.now?.call() ?? DateTime.now()).toUtc());

  Future<void> _generate() async {
    final ok = await confirm(
      context,
      title: 'Buat tagihan yang belum ada?',
      message: 'Tagihan dibuat sampai bulan berjalan untuk rumah yang sudah mulai ditagih. Tagihan yang sudah ada tidak berubah.',
      yes: 'Buat tagihan',
      icon: Icons.receipt_long_outlined,
    );
    if (!ok || !mounted) return;
    try {
      final r = await ref.read(apiProvider).post('/admin/dues/generate', data: <String, dynamic>{}, idempotencyKey: _generateKey);
      ref.invalidate(duesRecapProvider(_period));
      final n = (r is Map ? r['tagihan_baru'] : null) ?? 0;
      if (mounted) showMessage(context, n == 0 ? 'Semua tagihan sudah ada.' : '$n tagihan baru dibuat.');
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(duesRecapProvider(_period));
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(duesRecapProvider(_period)),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              IconButton(
                key: const ValueKey('rekap-sebelumnya'),
                tooltip: 'Bulan sebelumnya',
                onPressed: () => setState(() => _period = shiftMonth(_period, -1)),
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(periode(_period), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
              ),
              IconButton(
                key: const ValueKey('rekap-berikutnya'),
                tooltip: 'Bulan berikutnya',
                onPressed: () => setState(() => _period = shiftMonth(_period, 1)),
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
          ExportButton(jenis: 'iuran', label: 'Rekap ${periode(_period)}', periode: _period),
          const SizedBox(height: 8),
          AsyncView(
            value: data,
            onRetry: () => ref.invalidate(duesRecapProvider(_period)),
            builder: (d) {
              final rows = (d['rumah'] as List).cast<Map>();
              final lunas = d['lunas'] as Map;
              final belum = d['belum_bayar'] as Map;
              final lalu = d['tunggakan_sebelumnya'] as Map;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  SectionCard(
                    child: Wrap(
                      spacing: 16,
                      runSpacing: 12,
                      children: [
                        _Figure('Lunas', '${lunas['rumah']} rumah', rupiah(lunas['nominal'] as num), Brand.success),
                        _Figure('Belum dibayar', '${belum['rumah']} rumah', rupiah(belum['nominal'] as num), Brand.danger),
                        _Figure('Tunggakan bulan sebelumnya', '${lalu['rumah']} rumah', rupiah(lalu['nominal'] as num), Brand.warning),
                      ],
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    d['tarif'] == null
                        ? 'Belum ada tarif untuk bulan ini.'
                        : 'Tarif umum ${rupiah(d['tarif'] as num)} per rumah. Tarif khusus mengikuti ketentuan tiap rumah.',
                    style: const TextStyle(color: Brand.muted, fontSize: 13),
                  ),
                  const SizedBox(height: 12),
                  if (rows.isEmpty)
                    const EmptyState(
                      icon: Icons.receipt_long_outlined,
                      title: 'Belum ada tagihan bulan ini',
                      message: 'Tagihan dibuat otomatis tiap awal bulan untuk rumah yang sudah mulai ditagih.',
                    ),
                  for (final r in rows)
                    Card(
                      key: ValueKey('rekap-${r['kode_rumah']}'),
                      margin: const EdgeInsets.only(bottom: 6),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        child: Wrap(
                          alignment: WrapAlignment.spaceBetween,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          spacing: 8,
                          runSpacing: 6,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text('${r['kode_rumah']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                                Text(
                                  [
                                    rupiah(r['nominal'] as num),
                                    if (r['sisa'] != null) 'sisa ${rupiah(r['sisa'] as num)}',
                                    if (r['keringanan'] != null && (r['keringanan'] as num) > 0) 'keringanan ${rupiah(r['keringanan'] as num)}',
                                    if (r['tanggal_bayar'] != null) 'dibayar ${r['tanggal_bayar']}',
                                    if ((r['bulan_tunggakan_sebelumnya'] as num) > 0) 'tunggakan ${r['bulan_tunggakan_sebelumnya']} bulan sebelumnya',
                                  ].join(' • '),
                                  style: const TextStyle(color: Brand.muted),
                                ),
                              ],
                            ),
                            StatusChip('${r['status']}', label: '${r['status_label']}'),
                            if (ref.watch(sessionProvider).akun?.keuanganFleksibel == true)
                              TextButton(
                                onPressed: () async {
                                  await Navigator.push(
                                    context,
                                    MaterialPageRoute(builder: (_) => HouseFinancePage(house: {'id': r['rumah_id'], 'kode_rumah': r['kode_rumah']})),
                                  );
                                  ref.invalidate(duesRecapProvider(_period));
                                },
                                child: const Text('Kelola rumah'),
                              ),
                          ],
                        ),
                      ),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 12),
          BusyButton(label: 'Buat tagihan yang belum ada', icon: Icons.receipt_long_outlined, outlined: true, onPressed: _generate),
        ],
      ),
    );
  }
}

class _Figure extends StatelessWidget {
  const _Figure(this.label, this.count, this.amount, this.color);
  final String label;
  final String count;
  final String amount;
  final Color color;
  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: const BoxConstraints(minWidth: 130),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label, style: const TextStyle(color: Brand.muted, fontSize: 13)),
        Text(
          count,
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: color),
        ),
        Text(amount, style: const TextStyle(fontSize: 13)),
      ],
    ),
  );
}

/// Lainnya → Tarif iuran: riwayat tarif dan penetapan tarif bulan berikutnya (BR14).
class DuesRatesPage extends ConsumerWidget {
  const DuesRatesPage({super.key, this.now});
  final DateTime Function()? now;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rates = ref.watch(duesRatesProvider);
    final current = currentPeriod((now?.call() ?? DateTime.now()).toUtc());
    return Scaffold(
      appBar: AppBar(title: const Text('Tarif iuran')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(duesRatesProvider),
        child: AsyncView(
          value: rates,
          onRetry: () => ref.invalidate(duesRatesProvider),
          builder: (list) {
            final active = list.where((r) => r['dibatalkan_at'] == null && '${r['mulai_periode']}'.compareTo(current) <= 0).firstOrNull;
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                SectionCard(
                  title: 'Tarif berlaku ${periode(current)}',
                  child: Text(
                    active == null ? 'Belum ditetapkan' : '${rupiah(active['nominal'] as num)} per rumah per bulan',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Tarif baru hanya berlaku mulai bulan yang dipilih. Tagihan yang sudah dibuat tidak berubah.',
                  style: TextStyle(color: Brand.muted, height: 1.4),
                ),
                const SizedBox(height: 12),
                BusyButton(
                  label: 'Tetapkan tarif baru',
                  icon: Icons.add_card_outlined,
                  onPressed: () async {
                    final saved = await Navigator.push<bool>(
                      context,
                      MaterialPageRoute(
                        builder: (_) => DuesRateFormPage(current: current, taken: {for (final r in list) '${r['mulai_periode']}'}),
                      ),
                    );
                    if (saved == true) ref.invalidate(duesRatesProvider);
                  },
                ),
                const SizedBox(height: 20),
                Text('Riwayat tarif', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final r in list)
                  Card(
                    margin: const EdgeInsets.only(bottom: 6),
                    child: ListTile(
                      title: Text(
                        '${rupiah(r['nominal'] as num)} • mulai ${periode('${r['mulai_periode']}')}',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      subtitle: Text('${r['alasan']}${r['dibatalkan_at'] == null ? '' : '\nSudah dibalik'}'),
                      trailing: ref.watch(sessionProvider).akun?.keuanganFleksibel == true && r['dibatalkan_at'] == null
                          ? IconButton(
                              tooltip: 'Koreksi tarif umum',
                              icon: const Icon(Icons.edit_outlined),
                              onPressed: () async {
                                await Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceRatePage(original: r)));
                                ref.invalidate(duesRatesProvider);
                              },
                            )
                          : null,
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

class DuesRateFormPage extends ConsumerStatefulWidget {
  const DuesRateFormPage({super.key, required this.current, required this.taken});
  final String current;
  final Set<String> taken;
  @override
  ConsumerState<DuesRateFormPage> createState() => _DuesRateFormPageState();
}

class _DuesRateFormPageState extends ConsumerState<DuesRateFormPage> {
  final _key = newKey();
  final _nominal = TextEditingController(text: '75000');
  final _alasan = TextEditingController();
  String? _mulai;
  Map<String, String> _errors = {};

  Future<void> _submit() async {
    final errors = validateRate(nominal: _nominal.text, mulai: _mulai, alasan: _alasan.text, taken: widget.taken);
    setState(() => _errors = errors);
    if (errors.isNotEmpty) {
      showMessage(context, 'Periksa isian yang ditandai.', kind: AlertKind.warning, title: 'Belum lengkap');
      return;
    }
    final nominal = int.parse(_nominal.text.replaceAll('.', '').trim());
    final ok = await confirm(
      context,
      title: 'Tetapkan tarif baru?',
      message:
          '${rupiah(nominal)} per rumah per bulan mulai ${periode(_mulai!)}. Tagihan bulan sebelumnya tidak berubah. Tarif ini tidak bisa dihapus dari riwayat.',
      yes: 'Tetapkan',
      icon: Icons.add_card_outlined,
    );
    if (!ok || !mounted) return;
    try {
      await ref
          .read(apiProvider)
          .post('/admin/dues-rates', data: {'nominal': nominal, 'mulai_periode': _mulai, 'alasan': _alasan.text.trim()}, idempotencyKey: _key);
      if (!mounted) return;
      await showResultDialog(context, title: 'Tarif ditetapkan', message: '${rupiah(nominal)} berlaku mulai ${periode(_mulai!)}.');
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = {for (final f in e.fields.entries) f.key: f.value.first, if (e.code.startsWith('RATE_PERIOD')) 'mulai_periode': e.message});
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Tarif baru')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          key: const ValueKey('tarif-nominal'),
          controller: _nominal,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(labelText: 'Nominal per rumah per bulan (Rp)', errorText: _errors['nominal']),
        ),
        const SizedBox(height: 14),
        DropdownButtonFormField<String>(
          key: const ValueKey('tarif-mulai'),
          isExpanded: true,
          initialValue: _mulai,
          decoration: InputDecoration(labelText: 'Mulai berlaku', errorText: _errors['mulai_periode']),
          items: [
            for (final p in ratePeriods(widget.current))
              DropdownMenuItem(value: p, enabled: !widget.taken.contains(p), child: Text(widget.taken.contains(p) ? '${periode(p)} (sudah ada)' : periode(p))),
          ],
          onChanged: (v) => setState(() => _mulai = v),
        ),
        const SizedBox(height: 14),
        TextField(
          key: const ValueKey('tarif-alasan'),
          controller: _alasan,
          maxLength: 300,
          maxLines: 3,
          decoration: InputDecoration(labelText: 'Alasan perubahan', alignLabelWithHint: true, errorText: _errors['alasan']),
        ),
        const SizedBox(height: 12),
        BusyButton(label: 'Simpan tarif', icon: Icons.save_outlined, onPressed: _submit),
      ],
    ),
  );
}
