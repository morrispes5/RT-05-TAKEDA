import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import '../household/census_providers.dart';
import '../warga/providers.dart';
import 'dues_admin.dart';
import 'finance_actions.dart';
import 'flexible_payment.dart';
import 'pengurus_pages.dart' show newKey, paymentsProvider;

final houseRatesProvider = FutureProvider.autoDispose.family<List<Json>, String>(
  (ref, id) async => (await ref.read(apiProvider).get('/admin/houses/$id/dues-rates') as List).cast<Map>().map(Json.from).toList(),
);
final houseDepositsProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, id) async => Json.from(await ref.read(apiProvider).get('/admin/houses/$id/deposits') as Map),
);
final paymentDetailProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, id) async => Json.from(await ref.read(apiProvider).get('/admin/dues-payments/$id') as Map),
);

class HouseFinancePage extends ConsumerWidget {
  const HouseFinancePage({super.key, required this.house});
  final Json house;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final id = '${house['id']}';
    void refresh() {
      ref.invalidate(houseDuesProvider(id));
      ref.invalidate(houseRatesProvider(id));
      ref.invalidate(houseDepositsProvider(id));
      ref.invalidate(cashProvider);
    }

    Future<void> open(Widget page) async {
      await Navigator.push(context, MaterialPageRoute(builder: (_) => page));
      refresh();
    }

    Future<void> reverse(String path, String title, String effect) async {
      if (await reverseEntry(context, ref, path: path, title: title, done: 'Pembalikan tercatat', effect: effect)) refresh();
    }

    Widget history(String title, List rows, bool relief) => SectionCard(
      title: title,
      child: Column(
        children: [
          if (rows.isEmpty) const Text('Belum ada riwayat.'),
          for (final raw in rows)
            Builder(
              builder: (_) {
                final r = Json.from(raw as Map);
                final reversed = relief ? r['dibatalkan_at'] != null : r['membalik_id'] != null || rows.any((x) => (x as Map)['membalik_id'] == r['id']);
                final linked = relief && r['pembayaran_id'] != null;
                final type = relief ? 'dues-reliefs' : 'dues-adjustments';
                final amount = (r[relief ? 'nominal' : 'delta'] as num).toInt();
                return ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('${periode('${r['periode']}'.substring(0, 7))} • ${rupiah(amount)}'),
                  subtitle: Text(
                    '${r['alasan']}\n${reversed
                        ? 'Sudah dibalik'
                        : linked
                        ? 'Terkait penerimaan; koreksi melalui pembayaran'
                        : 'Aktif'}',
                  ),
                  onTap: linked ? () => open(PaymentDetailPage(id: '${r['pembayaran_id']}')) : null,
                  trailing: reversed || linked
                      ? null
                      : PopupMenuButton<String>(
                          tooltip: 'Koreksi atau balik',
                          itemBuilder: (_) => const [
                            PopupMenuItem(value: 'correct', child: Text('Koreksi')),
                            PopupMenuItem(value: 'reverse', child: Text('Balik')),
                          ],
                          onSelected: (action) async {
                            if (action == 'reverse') {
                              await reverse('/admin/$type/${r['id']}/reverse', 'Balik catatan ini?', 'Sisa tagihan dihitung ulang; kas tidak berubah.');
                            } else {
                              await open(
                                FinanceAmountPage(
                                  title: 'Koreksi ${relief ? 'keringanan' : 'tagihan'}',
                                  path: '/admin/$type/${r['id']}/correct',
                                  field: relief ? 'nominal' : 'delta',
                                  initial: amount,
                                ),
                              );
                            }
                          },
                        ),
                );
              },
            ),
        ],
      ),
    );
    return Scaffold(
      appBar: AppBar(title: Text('Keuangan rumah ${house['kode_rumah']}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton.icon(
                onPressed: () => open(FlexiblePaymentPage(house: house)),
                icon: const Icon(Icons.point_of_sale),
                label: const Text('Catat penerimaan'),
              ),
              OutlinedButton.icon(
                onPressed: () => open(FlexiblePaymentPage(house: house, deposit: true)),
                icon: const Icon(Icons.event_available),
                label: const Text('Pakai titipan'),
              ),
              OutlinedButton.icon(
                onPressed: () => open(FinanceRatePage(house: house)),
                icon: const Icon(Icons.price_change_outlined),
                label: const Text('Tarif rumah'),
              ),
            ],
          ),
          const SizedBox(height: 16),
          AsyncView(
            value: ref.watch(houseDuesProvider(id)),
            onRetry: refresh,
            builder: (d) => Column(
              children: [
                SectionCard(
                  title: 'Tagihan dan penyelesaian',
                  child: Column(
                    children: [
                      for (final raw in (d['tagihan'] as List))
                        Builder(
                          builder: (_) {
                            final t = Json.from(raw as Map);
                            final oldBook = (t['terbayar_buku_lama'] as num? ?? 0).toInt();
                            return ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text('${periode('${t['periode']}')} • ${t['status_label']}'),
                              subtitle: Text(
                                'Tagihan ${rupiah(t['nominal_efektif'] as num)}\nTerbayar ${rupiah(t['terbayar'] as num)} • Keringanan ${rupiah(t['keringanan'] as num)}\nSisa ${rupiah(t['sisa'] as num)}'
                                '${oldBook > 0 ? '\nTermasuk ${rupiah(oldBook)} dari buku lama (rekap bendahara, tanpa kas)' : ''}',
                              ),
                              trailing: PopupMenuButton<String>(
                                tooltip: 'Kelola tagihan',
                                itemBuilder: (_) => [
                                  if ((t['sisa'] as num) > 0) const PopupMenuItem(value: 'relief', child: Text('Beri keringanan')),
                                  const PopupMenuItem(value: 'adjustment', child: Text('Koreksi tagihan')),
                                  if (t['buku_lama_id'] != null) const PopupMenuItem(value: 'old-book', child: Text('Batalkan catatan buku lama')),
                                ],
                                onSelected: (action) async {
                                  if (action == 'old-book') {
                                    await reverse(
                                      '/admin/old-book-settlements/${t['buku_lama_id']}/cancel',
                                      'Batalkan catatan buku lama?',
                                      'Bulan ini kembali belum lunas sebesar ${rupiah(oldBook)}. Kas tidak berubah. Bila uangnya baru diterima, catat sebagai penerimaan.',
                                    );
                                    return;
                                  }
                                  await open(
                                    FinanceAmountPage(
                                      title: action == 'relief' ? 'Beri keringanan' : 'Koreksi tagihan',
                                      path: '/admin/dues-charges/${t['id']}/${action == 'relief' ? 'reliefs' : 'adjustments'}',
                                      field: action == 'relief' ? 'nominal' : 'delta',
                                      initial: action == 'relief' ? (t['sisa'] as num).toInt() : null,
                                    ),
                                  );
                                },
                              ),
                            );
                          },
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                history('Riwayat keringanan', d['riwayat_keringanan'] as List? ?? [], true),
                const SizedBox(height: 12),
                history('Riwayat koreksi tagihan', d['riwayat_penyesuaian'] as List? ?? [], false),
              ],
            ),
          ),
          const SizedBox(height: 12),
          AsyncView(
            value: ref.watch(houseDepositsProvider(id)),
            onRetry: refresh,
            builder: (d) => SectionCard(
              title: 'Titipan: ${rupiah(d['saldo'] as num)}',
              child: Column(
                children: [
                  if ((d['lot'] as List).isEmpty) const Text('Belum ada titipan. Titipan dicatat dari kelebihan penerimaan.'),
                  for (final lot in (d['lot'] as List).cast<Map>())
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('Sisa titipan ${rupiah(int.parse('${lot['saldo']}'))}'),
                      subtitle: const Text('Ketuk untuk melihat penerimaan asal'),
                      onTap: () => open(PaymentDetailPage(id: '${lot['pembayaran_id']}')),
                    ),
                  for (final raw in (d['penggunaan'] as List? ?? []).cast<Map>())
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text('Pemakaian ${rupiah(int.parse('${raw['nominal']}'))} • ${raw['tanggal']}'),
                      subtitle: Text(
                        '${(raw['periode'] as List).map((p) => periode('$p')).join(', ')}\n${raw['dibatalkan_at'] == null ? 'Aktif' : 'Sudah dibalik: ${raw['alasan_pembatalan']}'}',
                      ),
                      trailing: raw['dibatalkan_at'] != null
                          ? null
                          : PopupMenuButton<String>(
                              tooltip: 'Kelola pemakaian titipan',
                              itemBuilder: (_) => const [
                                PopupMenuItem(value: 'correct', child: Text('Koreksi')),
                                PopupMenuItem(value: 'reverse', child: Text('Balik')),
                              ],
                              onSelected: (action) async {
                                if (action == 'reverse') {
                                  await reverse(
                                    '/admin/deposit-usages/${raw['id']}/reverse',
                                    'Balik pemakaian titipan?',
                                    'Titipan dikembalikan ke saldo rumah dan sisa tagihan dibuka; kas tidak berubah.',
                                  );
                                } else {
                                  await open(
                                    FlexiblePaymentPage(
                                      house: house,
                                      deposit: true,
                                      usageId: '${raw['id']}',
                                      initialAmount: int.parse('${raw['nominal']}'),
                                      initialPeriods: [for (final p in raw['periode'] as List) '$p'],
                                    ),
                                  );
                                }
                              },
                            ),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          AsyncView(
            value: ref.watch(houseRatesProvider(id)),
            onRetry: refresh,
            builder: (rows) => SectionCard(
              title: 'Riwayat tarif rumah',
              child: Column(
                children: [
                  if (rows.isEmpty) const Text('Mengikuti tarif umum. Belum ada tarif khusus.'),
                  for (final r in rows)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        '${r['mode'] == 'umum' ? 'Tarif umum' : rupiah(int.parse('${r['nominal']}'))} mulai ${periode('${r['mulai_periode']}'.substring(0, 7))}',
                      ),
                      subtitle: Text('${r['alasan']}\n${r['dibatalkan_at'] == null ? 'Aktif sesuai bulan berlaku' : 'Sudah dibalik'}'),
                      trailing: r['dibatalkan_at'] == null
                          ? IconButton(
                              tooltip: 'Koreksi tarif rumah',
                              icon: const Icon(Icons.edit_outlined),
                              onPressed: () => open(FinanceRatePage(house: house, original: r)),
                            )
                          : null,
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

class FinanceAmountPage extends ConsumerStatefulWidget {
  const FinanceAmountPage({super.key, required this.title, required this.path, required this.field, this.initial});
  final String title, path, field;
  final int? initial;
  @override
  ConsumerState<FinanceAmountPage> createState() => _FinanceAmountPageState();
}

class _FinanceAmountPageState extends ConsumerState<FinanceAmountPage> {
  late final _amount = TextEditingController(text: widget.initial?.toString() ?? '');
  final _reason = TextEditingController();
  String _key = newKey();
  Json? _pending;
  bool _unknown = false;
  bool _saving = false;
  @override
  void dispose() {
    _amount.dispose();
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          widget.field == 'delta'
              ? 'Isi selisih koreksi. Angka negatif mengurangi tagihan, angka positif menambahnya. Catatan tagihan asal tetap tersimpan. Tidak mengubah kas.'
              : 'Keringanan mengurangi kewajiban rumah dan tidak menambah kas. Alasan disimpan dalam riwayat.',
        ),
        TextField(
          key: const ValueKey('adjustment-amount'),
          controller: _amount,
          enabled: !_unknown && !_saving,
          keyboardType: TextInputType.numberWithOptions(signed: widget.field == 'delta'),
          decoration: InputDecoration(labelText: widget.field == 'delta' ? 'Selisih koreksi (Rp), boleh negatif' : 'Nominal keringanan (Rp)'),
          onChanged: (_) {
            _pending = null;
            _key = newKey();
          },
        ),
        TextField(
          controller: _reason,
          enabled: !_unknown && !_saving,
          maxLength: 300,
          decoration: const InputDecoration(labelText: 'Alasan wajib (minimal 5 karakter)'),
          onChanged: (_) {
            _pending = null;
            _key = newKey();
          },
        ),
        if (_unknown) const Text('Hasil belum diketahui. Coba lagi dengan rincian yang sama.'),
        BusyButton(
          label: _unknown ? 'Coba simpan lagi' : 'Simpan',
          onPressed: _saving
              ? null
              : () async {
                  if (_pending == null) {
                    final amount = int.tryParse(_amount.text.trim());
                    if (amount == null || amount == 0 || widget.field != 'delta' && amount < 0 || _reason.text.trim().length < 5) {
                      showMessage(context, 'Periksa nominal dan alasan.', kind: AlertKind.warning);
                      return;
                    }
                    if (!await confirm(context, title: widget.title, message: '${rupiah(amount)}\n${_reason.text.trim()}\nKas tidak berubah.', yes: 'Simpan')) {
                      return;
                    }
                    _pending = {widget.field: amount, 'alasan': _reason.text.trim(), 'versi_keuangan': 2};
                  }
                  if (!mounted) return;
                  setState(() => _saving = true);
                  try {
                    await ref.read(apiProvider).post(widget.path, data: _pending, idempotencyKey: _key);
                    if (context.mounted) Navigator.pop(context, true);
                  } on ApiException catch (e) {
                    if (!context.mounted) return;
                    setState(() {
                      _unknown = e.status == 0 || e.status >= 500;
                      if (!_unknown) _pending = null;
                    });
                    showApiError(context, e);
                  } finally {
                    if (mounted) setState(() => _saving = false);
                  }
                },
        ),
      ],
    ),
  );
}

class FinanceRatePage extends ConsumerStatefulWidget {
  const FinanceRatePage({super.key, this.house, this.original});
  final Json? house, original;
  @override
  ConsumerState<FinanceRatePage> createState() => _FinanceRatePageState();
}

class _FinanceRatePageState extends ConsumerState<FinanceRatePage> {
  late final _amount = TextEditingController(text: widget.original?['nominal']?.toString() ?? '75000');
  final _reason = TextEditingController();
  late String? _period = widget.original == null ? null : '${widget.original!['mulai_periode']}'.substring(0, 7);
  late bool _common = widget.original?['mode'] == 'umum';
  String _key = newKey();
  Json? _pending;
  bool _unknown = false;
  bool _saving = false;
  @override
  void dispose() {
    _amount.dispose();
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.house == null ? 'Koreksi tarif umum' : 'Tarif rumah ${widget.house!['kode_rumah']}')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Tarif berlaku mulai bulan mendatang yang belum ditagihkan. Tagihan lama tidak berubah; kesalahan tagihan lama diperbaiki melalui koreksi tagihan.',
        ),
        if (widget.house != null)
          SwitchListTile(
            value: _common,
            title: const Text('Ikuti tarif umum mulai bulan dipilih'),
            onChanged: _unknown || _saving
                ? null
                : (v) => setState(() {
                    _common = v;
                    _pending = null;
                    _key = newKey();
                  }),
          ),
        if (!_common)
          TextField(
            controller: _amount,
            enabled: !_unknown && !_saving,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(labelText: 'Nominal per bulan (Rp)'),
            onChanged: (_) {
              _pending = null;
              _key = newKey();
            },
          ),
        if (widget.original == null)
          DropdownButtonFormField<String>(
            initialValue: _period,
            decoration: const InputDecoration(labelText: 'Mulai bulan'),
            items: [for (final p in ratePeriods(currentPeriod(DateTime.now().toUtc()))) DropdownMenuItem(value: p, child: Text(periode(p)))],
            onChanged: _unknown || _saving
                ? null
                : (v) => setState(() {
                    _period = v;
                    _pending = null;
                    _key = newKey();
                  }),
          )
        else
          ListTile(title: Text('Mulai ${periode(_period!)}')),
        TextField(
          controller: _reason,
          enabled: !_unknown && !_saving,
          maxLength: 300,
          decoration: const InputDecoration(labelText: 'Alasan wajib'),
          onChanged: (_) {
            _pending = null;
            _key = newKey();
          },
        ),
        if (_unknown) const Text('Hasil belum diketahui. Coba lagi dengan rincian sama.'),
        BusyButton(
          label: _unknown ? 'Coba simpan lagi' : 'Simpan tarif',
          onPressed: _saving
              ? null
              : () async {
                  if (_pending == null) {
                    final amount = int.tryParse(_amount.text);
                    if (_period == null || (!_common && (amount == null || amount < 1000 || amount > 10000000)) || _reason.text.trim().length < 5) {
                      showMessage(context, 'Periksa bulan, nominal dan alasan.', kind: AlertKind.warning);
                      return;
                    }
                    if (!await confirm(
                      context,
                      title: 'Simpan tarif?',
                      message: '${_common ? 'Mengikuti tarif umum' : rupiah(amount!)} mulai ${periode(_period!)}\n${_reason.text.trim()}',
                      yes: 'Simpan',
                    )) {
                      return;
                    }
                    _pending = {'mulai_periode': _period, 'nominal': _common ? null : amount, 'alasan': _reason.text.trim(), 'versi_keuangan': 2};
                  }
                  final path = widget.house == null
                      ? '/admin/dues-rates/${widget.original!['id']}/correct'
                      : '/admin/houses/${widget.house!['id']}/dues-rates${widget.original == null ? '' : '/${widget.original!['id']}/correct'}';
                  if (!mounted) return;
                  setState(() => _saving = true);
                  try {
                    await ref.read(apiProvider).post(path, data: _pending, idempotencyKey: _key);
                    ref.invalidate(duesRatesProvider);
                    if (context.mounted) Navigator.pop(context, true);
                  } on ApiException catch (e) {
                    if (!context.mounted) return;
                    setState(() {
                      _unknown = e.status == 0 || e.status >= 500;
                      if (!_unknown) _pending = null;
                    });
                    showApiError(context, e);
                  } finally {
                    if (mounted) setState(() => _saving = false);
                  }
                },
        ),
      ],
    ),
  );
}

class PaymentDetailPage extends ConsumerWidget {
  const PaymentDetailPage({super.key, required this.id});
  final String id;
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(title: const Text('Rincian penerimaan')),
    body: AsyncView(
      value: ref.watch(paymentDetailProvider(id)),
      onRetry: () => ref.invalidate(paymentDetailProvider(id)),
      builder: (p) => ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('${p['nomor_bukti']}', style: Theme.of(context).textTheme.titleLarge),
          Text('Rumah ${(p['rumah'] as Map?)?['kode_rumah']} • ${p['tanggal_bayar']}'),
          Text('Uang diterima: ${rupiah(p['total_bayar'] as num)}'),
          Text('Dialokasikan ke iuran: ${rupiah((p['nominal_alokasi'] as num?) ?? 0)}'),
          Text('Sumbangan: ${rupiah((p['nominal_sumbangan'] as num?) ?? 0)}'),
          Text('Titipan: ${rupiah((p['nominal_titipan'] as num?) ?? 0)}'),
          Text('Status: ${p['status'] == 'tercatat' ? 'Tercatat' : 'Dibatalkan: ${p['alasan_pembatalan']}'}'),
          if (p['menggantikan_id'] != null)
            TextButton(
              onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PaymentDetailPage(id: '${p['menggantikan_id']}'))),
              child: const Text('Lihat penerimaan asal'),
            ),
          if (p['status'] == 'tercatat') ...[
            FilledButton.icon(
              icon: const Icon(Icons.edit_outlined),
              label: const Text('Koreksi penerimaan'),
              onPressed: () async {
                final saved = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => FlexiblePaymentPage(
                      house: Json.from(p['rumah'] as Map),
                      paymentId: id,
                      initialAmount: (p['total_bayar'] as num).toInt(),
                      initialPeriods: [for (final x in p['periode'] as List) '$x'],
                    ),
                  ),
                );
                if (saved == true) {
                  ref.invalidate(paymentDetailProvider(id));
                  ref.invalidate(paymentsProvider);
                }
              },
            ),
            OutlinedButton.icon(
              icon: const Icon(Icons.undo),
              label: const Text('Balik penerimaan'),
              onPressed: () async {
                if (await reverseEntry(
                  context,
                  ref,
                  path: '/admin/dues-payments/$id/reverse',
                  title: 'Balik penerimaan?',
                  done: 'Penerimaan dibalik',
                  effect:
                      'Kas dikurangi ${rupiah(p['total_bayar'] as num)}. Alokasi dan keringanan terkait dibalik. Jika titipan sudah dipakai, balik pemakaiannya lebih dahulu.',
                )) {
                  ref.invalidate(paymentDetailProvider(id));
                  ref.invalidate(paymentsProvider);
                  ref.invalidate(cashProvider);
                }
              },
            ),
          ],
        ],
      ),
    ),
  );
}
