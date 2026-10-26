import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import '../household/census_providers.dart';
import '../household/paged_list.dart';
import '../warga/providers.dart';
import 'dues_admin.dart';
import 'dues_confirmations.dart';
import 'pengurus_pages.dart' show newKey, paymentsProvider;

/// Amount and settlement preview are server-owned. A retry after unknown outcome keeps the exact body/key.
class FlexiblePaymentPage extends ConsumerStatefulWidget {
  const FlexiblePaymentPage({
    super.key,
    this.house,
    this.initialPeriods = const [],
    this.initialAmount,
    this.confirmationId,
    this.paymentId,
    this.deposit = false,
    this.usageId,
  });
  final Json? house;
  final List<String> initialPeriods;
  final int? initialAmount;
  final String? confirmationId;
  final String? paymentId;
  final bool deposit;
  final String? usageId;

  @override
  ConsumerState<FlexiblePaymentPage> createState() => _FlexiblePaymentPageState();
}

class _FlexiblePaymentPageState extends ConsumerState<FlexiblePaymentPage> {
  Json? _house;
  late final _amount = TextEditingController(text: widget.initialAmount?.toString() ?? '');
  final _reason = TextEditingController();
  late final Set<String> _periods = widget.initialPeriods.toSet();
  DateTime _date = toJakarta(DateTime.now().toUtc());
  Json? _preview;
  String? _shortfall;
  String? _excess;
  String _key = newKey();
  Json? _pending;
  bool _busy = false;
  bool _uncertain = false;
  bool get _correcting => widget.paymentId != null || widget.usageId != null;

  @override
  void initState() {
    super.initState();
    _house = widget.house;
  }

  @override
  void dispose() {
    _amount.dispose();
    _reason.dispose();
    super.dispose();
  }

  void _changed() {
    setState(() {
      _preview = null;
      _pending = null;
      _shortfall = null;
      _excess = null;
      _key = newKey();
    });
  }

  Future<void> _loadPreview() async {
    final amount = int.tryParse(_amount.text);
    if (_house == null || _periods.isEmpty || amount == null || amount <= 0 || amount > 1000000000) {
      showMessage(context, 'Pilih rumah dan bulan, lalu isi nominal rupiah yang benar.', kind: AlertKind.warning);
      return;
    }
    setState(() => _busy = true);
    try {
      final value = await ref
          .read(apiProvider)
          .post(
            '/admin/dues-payments/preview',
            data: {
              'rumah_id': _house!['id'],
              'periode': _periods.toList()..sort(),
              'nominal': amount,
              if (widget.paymentId != null) 'koreksi_pembayaran_id': widget.paymentId,
              if (widget.usageId != null) 'koreksi_penggunaan_id': widget.usageId,
            },
          );
      if (mounted) setState(() => _preview = Json.from(value as Map));
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _save() async {
    if (_pending == null) {
      final p = _preview!;
      final short = (p['kurang'] as num).toInt();
      final extra = (p['lebih'] as num).toInt();
      final amount = int.parse(_amount.text);
      final depositBalance = (p['titipan_setelah_pembalikan'] ?? p['titipan']) as num;
      final due = (p['sisa_setelah_pembalikan'] ?? p['sisa']) as num;
      if (widget.deposit && (amount > depositBalance || amount > due)) {
        showMessage(context, 'Nominal tidak boleh melebihi saldo titipan atau sisa tagihan.', kind: AlertKind.warning);
        return;
      }
      if (!widget.deposit && ((short > 0 && _shortfall == null) || (extra > 0 && _excess == null))) {
        showMessage(context, 'Tentukan perlakuan selisih terlebih dahulu.', kind: AlertKind.warning);
        return;
      }
      final needsReason = _correcting || widget.confirmationId != null && widget.initialAmount != amount || _shortfall == 'keringanan';
      if (needsReason && _reason.text.trim().length < 5) {
        showMessage(context, 'Alasan minimal 5 karakter.', kind: AlertKind.warning);
        return;
      }
      if (_reason.text.trim().isNotEmpty && _reason.text.trim().length < 5) {
        showMessage(context, 'Lengkapi alasan minimal 5 karakter atau kosongkan.', kind: AlertKind.warning);
        return;
      }
      final body = <String, dynamic>{
        'rumah_id': _house!['id'],
        'periode': _periods.toList()..sort(),
        'nominal': amount,
        widget.deposit ? 'tanggal' : 'tanggal_bayar': '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
        'fingerprint': p['fingerprint'],
        'versi_keuangan': 2,
        if (_shortfall != null && !widget.deposit) 'perlakuan_kurang': _shortfall,
        if (_excess != null && !widget.deposit) 'perlakuan_lebih': _excess,
        if (_reason.text.trim().isNotEmpty) 'alasan': _reason.text.trim(),
      };
      final detail = widget.deposit
          ? 'Titipan dipakai ${rupiah(amount)}. Tidak ada uang baru masuk.'
          : 'Uang diterima ${rupiah(amount)}\n'
                '${short > 0
                    ? _shortfall == 'keringanan'
                          ? 'Keringanan ${rupiah(short)}'
                          : 'Sisa tunggakan ${rupiah(short)}'
                    : ''}'
                '${extra > 0 ? '\n${_excess == 'titipan' ? 'Titipan' : 'Sumbangan'} ${rupiah(extra)}' : ''}';
      if (!await confirm(
        context,
        title: _correcting ? 'Simpan koreksi?' : 'Simpan pencatatan?',
        message: 'Rumah ${_house!['kode_rumah']}\n$detail${_correcting ? '\nCatatan asal dibalik, lalu diganti dalam satu transaksi.' : ''}',
        yes: 'Simpan',
      )) {
        return;
      }
      if (!mounted) return;
      _pending = body;
    }
    setState(() => _busy = true);
    final path = widget.confirmationId != null
        ? '/admin/dues-confirmations/${widget.confirmationId}/approve'
        : widget.paymentId != null
        ? '/admin/dues-payments/${widget.paymentId}/correct'
        : widget.usageId != null
        ? '/admin/deposit-usages/${widget.usageId}/correct'
        : widget.deposit
        ? '/admin/deposit-usages'
        : '/admin/dues-payments';
    try {
      await ref.read(apiProvider).post(path, data: _pending, idempotencyKey: _key);
      ref.invalidate(houseDuesProvider('${_house!['id']}'));
      ref.invalidate(paymentsProvider);
      ref.invalidate(duesRecapProvider);
      ref.invalidate(duesConfirmationsProvider);
      ref.invalidate(cashProvider);
      if (!mounted) return;
      await showResultDialog(
        context,
        title: 'Pencatatan berhasil',
        message: widget.deposit ? 'Titipan sudah dipakai sesuai rincian. Kas tidak bertambah.' : 'Penerimaan dan penyelesaian tagihan sudah tercatat.',
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _uncertain = e.status == 0 || e.status >= 500;
        if (!_uncertain) {
          _pending = null;
          _preview = null;
          _key = newKey();
        }
      });
      if (e.code == 'FINANCE_PREVIEW_STALE') ref.invalidate(houseDuesProvider('${_house!['id']}'));
      showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _choice(String label, String value) => RadioListTile<String>(title: Text(label), value: value, enabled: !_busy && !_uncertain);

  @override
  Widget build(BuildContext context) {
    final p = _preview;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.confirmationId != null
              ? 'Setujui bukti dan catat uang'
              : widget.deposit
              ? 'Pakai titipan rumah'
              : _correcting
              ? 'Koreksi penerimaan'
              : 'Catat uang diterima',
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            widget.deposit
                ? 'Titipan milik rumah dipakai atas pilihan pengurus. Pemakaian tidak menambah kas.'
                : 'Isi uang yang benar-benar diterima. Periksa rincian dan tentukan selisih sebelum menyimpan.',
          ),
          if (widget.house == null)
            AsyncView(
              value: ref.watch(housesProvider((q: '', arsip: false, page: 1))),
              onRetry: () => ref.invalidate(housesProvider((q: '', arsip: false, page: 1))),
              builder: (page) => AppendedPage(
                first: page,
                load: (n) => ref.read(housesProvider((q: '', arsip: false, page: n)).future),
                builder: (context, list) => DropdownButtonFormField<String>(
                  key: const ValueKey('finance-house'),
                  initialValue: _house?['id'] as String?,
                  isExpanded: true,
                  decoration: const InputDecoration(labelText: 'Rumah'),
                  items: [
                    for (final h in list)
                      DropdownMenuItem(
                        value: '${h['id']}',
                        child: Text('${h['kode_rumah']} • ${h['alamat']}', overflow: TextOverflow.ellipsis),
                      ),
                  ],
                  onChanged: _busy || _uncertain
                      ? null
                      : (id) {
                          _house = list.firstWhere((h) => h['id'] == id);
                          _periods.clear();
                          _changed();
                        },
                ),
              ),
            )
          else
            ListTile(contentPadding: EdgeInsets.zero, title: Text('Rumah ${_house!['kode_rumah']}')),
          if (_house != null)
            AsyncView(
              value: ref.watch(houseDuesProvider('${_house!['id']}')),
              onRetry: () => ref.invalidate(houseDuesProvider('${_house!['id']}')),
              builder: (d) => Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (widget.deposit) Text('Saldo titipan: ${rupiah((d['saldo_titipan'] as num?) ?? 0)}'),
                  const Text('Pilih bulan. Bulan yang sudah lunas boleh dipilih untuk mencatat penerimaan tambahan.'),
                  for (final row in (d['tagihan'] as List).cast<Map>())
                    CheckboxListTile(
                      value: _periods.contains(row['periode']),
                      title: Text(periode('${row['periode']}')),
                      subtitle: Text('${row['status_label']} • Sisa ${rupiah((row['sisa'] as num?) ?? 0)}'),
                      onChanged: _busy || _uncertain
                          ? null
                          : (v) {
                              v == true ? _periods.add('${row['periode']}') : _periods.remove('${row['periode']}');
                              _changed();
                            },
                    ),
                ],
              ),
            ),
          const SizedBox(height: 12),
          TextField(
            key: const ValueKey('finance-amount'),
            controller: _amount,
            enabled: !_busy && !_uncertain,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: InputDecoration(
              labelText: widget.deposit ? 'Titipan yang dipakai (Rp)' : 'Uang yang diterima (Rp)',
              helperText: 'Rupiah utuh, tanpa titik atau koma',
            ),
            onChanged: (_) => _changed(),
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('Tanggal: ${_date.day}/${_date.month}/${_date.year}'),
            trailing: const Icon(Icons.calendar_today_outlined),
            onTap: _busy || _uncertain
                ? null
                : () async {
                    final date = await showDatePicker(
                      context: context,
                      initialDate: _date,
                      firstDate: DateTime(2025),
                      lastDate: toJakarta(DateTime.now().toUtc()),
                    );
                    if (date != null) {
                      _date = date;
                      _changed();
                    }
                  },
          ),
          if (!_uncertain) BusyButton(label: 'Periksa rincian', icon: Icons.fact_check_outlined, onPressed: _busy ? null : _loadPreview),
          if (p != null) ...[
            const SizedBox(height: 16),
            SectionCard(
              title: 'Rincian pencatatan',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (_correcting) const Text('Berikut rincian setelah catatan asal dibalik.'),
                  for (final a in (p['alokasi'] as List).cast<Map>())
                    Text('${periode('${a['periode']}')}: dialokasikan ${rupiah(a['alokasi'] as num)}, sisa ${rupiah(a['sisa_sesudah'] as num)}'),
                  if (!widget.deposit) Text('Uang masuk kas: ${rupiah(p['kas_diterima'] as num)}'),
                ],
              ),
            ),
            if (!widget.deposit && (p['kurang'] as num) > 0) ...[
              Text('Kurang ${rupiah(p['kurang'] as num)}. Tentukan perlakuannya:'),
              RadioGroup<String>(
                groupValue: _shortfall,
                onChanged: (v) => setState(() => _shortfall = v),
                child: Column(
                  children: [_choice('Kurang bayar, sisanya tetap tunggakan', 'kurang_bayar'), _choice('Lunas dengan keringanan (alasan wajib)', 'keringanan')],
                ),
              ),
            ],
            if (!widget.deposit && (p['lebih'] as num) > 0) ...[
              Text('Lebih ${rupiah(p['lebih'] as num)}. Tentukan perlakuannya:'),
              RadioGroup<String>(
                groupValue: _excess,
                onChanged: (v) => setState(() => _excess = v),
                child: Column(children: [_choice('Sumbangan ke kas', 'sumbangan'), _choice('Titipan rumah untuk bulan berikutnya', 'titipan')]),
              ),
            ],
            TextField(
              key: const ValueKey('finance-reason'),
              controller: _reason,
              enabled: !_busy && !_uncertain,
              maxLength: 300,
              decoration: const InputDecoration(labelText: 'Alasan (wajib untuk keringanan/koreksi)'),
              onChanged: (_) {
                _pending = null;
                _key = newKey();
              },
            ),
            if (_uncertain) const Text('Hasil belum diketahui. Rincian dikunci agar percobaan ulang memakai transaksi yang sama. Periksa riwayat bila perlu.'),
            const SizedBox(height: 12),
            BusyButton(
              key: const ValueKey('finance-save'),
              label: _uncertain ? 'Coba simpan lagi' : 'Simpan pencatatan',
              icon: Icons.check,
              onPressed: _busy ? null : _save,
            ),
          ],
        ],
      ),
    );
  }
}
