import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import '../warga/providers.dart';
import 'finance_actions.dart';
import 'pengurus_pages.dart' show newKey;

class CashCorrectionPage extends ConsumerStatefulWidget {
  const CashCorrectionPage({super.key, required this.original});
  final Json original;
  @override
  ConsumerState<CashCorrectionPage> createState() => _CashCorrectionPageState();
}

class _CashCorrectionPageState extends ConsumerState<CashCorrectionPage> {
  late final _amount = TextEditingController(text: (widget.original['nominal'] as num).abs().toInt().toString());
  late final _category = TextEditingController(text: '${widget.original['kategori']}');
  late final _note = TextEditingController(text: '${widget.original['keterangan']}');
  final _reason = TextEditingController();
  late String _type = '${widget.original['jenis']}';
  late DateTime _date = DateTime.parse('${widget.original['tanggal']}');
  String _key = newKey();
  Json? _pending;
  bool _saving = false, _unknown = false;
  void _changed() {
    _pending = null;
    _key = newKey();
  }

  @override
  void dispose() {
    _amount.dispose();
    _category.dispose();
    _note.dispose();
    _reason.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_pending == null) {
      final amount = int.tryParse(_amount.text);
      if (amount == null || amount <= 0 || _reason.text.trim().length < 5 || _note.text.trim().length < 5 || _category.text.trim().isEmpty) {
        showMessage(context, 'Periksa nominal, kategori, keterangan dan alasan.', kind: AlertKind.warning);
        return;
      }
      if (!await confirm(
        context,
        title: 'Simpan koreksi kas?',
        message: 'Catatan asal dibalik lalu diganti dengan ${cashJenisLabel(_type)} ${rupiah(amount)}.\nAlasan: ${_reason.text.trim()}',
        yes: 'Simpan',
      )) {
        return;
      }
      if (!mounted) return;
      _pending = {
        'jenis': _type,
        'nominal': amount,
        'tanggal': '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
        'kategori': _category.text.trim(),
        'keterangan': _note.text.trim(),
        'alasan': _reason.text.trim(),
        'versi_keuangan': 2,
      };
    }
    setState(() => _saving = true);
    try {
      try {
        await ref.read(apiProvider).post('/admin/cash-transactions/${widget.original['id']}/correct', data: _pending, idempotencyKey: _key);
      } on ApiException catch (e) {
        if (e.code != negativeBalanceCode || !mounted) rethrow;
        if (!await confirm(context, title: 'Saldo kas akan negatif', message: e.message, yes: 'Tetap simpan', danger: true)) return;
        if (!mounted) return;
        _pending = {..._pending!, 'konfirmasi_saldo_negatif': true};
        await ref.read(apiProvider).post('/admin/cash-transactions/${widget.original['id']}/correct', data: _pending, idempotencyKey: _key);
      }
      ref.invalidate(cashProvider);
      if (!mounted) return;
      await showResultDialog(context, title: 'Koreksi kas tercatat', message: 'Pembalikan dan pengganti tersimpan. Riwayat asal tetap tersedia.');
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _unknown = e.status == 0 || e.status >= 500;
        if (!_unknown) _pending = null;
      });
      showApiError(context, e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Koreksi kas manual')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('Asal: ${cashJenisLabel('${widget.original['jenis']}')} ${rupiah(widget.original['nominal'] as num)} • ${widget.original['tanggal']}'),
        DropdownButtonFormField<String>(
          initialValue: _type,
          decoration: const InputDecoration(labelText: 'Jenis pengganti'),
          items: const [
            DropdownMenuItem(value: 'pemasukan', child: Text('Pemasukan lain')),
            DropdownMenuItem(value: 'pengeluaran', child: Text('Pengeluaran')),
          ],
          onChanged: _saving || _unknown
              ? null
              : (v) => setState(() {
                  _type = v!;
                  _changed();
                }),
        ),
        TextField(
          controller: _amount,
          enabled: !_saving && !_unknown,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(labelText: 'Nominal pengganti (Rp)'),
          onChanged: (_) => _changed(),
        ),
        TextField(
          controller: _category,
          enabled: !_saving && !_unknown,
          maxLength: 60,
          decoration: const InputDecoration(labelText: 'Kategori'),
          onChanged: (_) => _changed(),
        ),
        TextField(
          controller: _note,
          enabled: !_saving && !_unknown,
          maxLength: 500,
          decoration: const InputDecoration(labelText: 'Keterangan'),
          onChanged: (_) => _changed(),
        ),
        TextField(
          controller: _reason,
          key: const ValueKey('cash-correction-reason'),
          enabled: !_saving && !_unknown,
          maxLength: 300,
          decoration: const InputDecoration(labelText: 'Alasan koreksi (wajib)'),
          onChanged: (_) => _changed(),
        ),
        ListTile(
          title: Text('Tanggal pengganti: ${_date.day}/${_date.month}/${_date.year}'),
          trailing: const Icon(Icons.calendar_today_outlined),
          onTap: _saving || _unknown
              ? null
              : () async {
                  final date = await showDatePicker(
                    context: context,
                    initialDate: _date,
                    firstDate: DateTime(2025),
                    lastDate: toJakarta(DateTime.now().toUtc()),
                  );
                  if (date != null) {
                    setState(() {
                      _date = date;
                      _changed();
                    });
                  }
                },
        ),
        if (_unknown) const Text('Hasil belum diketahui. Coba lagi menggunakan rincian sama.'),
        BusyButton(label: _unknown ? 'Coba simpan lagi' : 'Simpan koreksi', onPressed: _saving ? null : _save),
      ],
    ),
  );
}
