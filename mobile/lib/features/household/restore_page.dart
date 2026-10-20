import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/widgets.dart';
import 'family_actions.dart';

/// Pulihkan data arsip (rumah, keluarga, akun) dengan alasan wajib. Hasilnya `true` bila berhasil
/// supaya layar pemanggil memuat ulang. Aturan tiap jenis ada di server (ADR34); teks [note] menjelaskan
/// apa yang TIDAK ikut kembali agar pengurus tidak menyangka semuanya pulih otomatis.
class RestoreArchivedPage extends ConsumerStatefulWidget {
  const RestoreArchivedPage({
    super.key,
    required this.title,
    required this.path,
    required this.name,
    required this.note,
    required this.doneTitle,
    required this.doneMessage,
  });

  final String title;
  final String path;
  final String name;
  final String note;
  final String doneTitle;
  final String doneMessage;

  @override
  ConsumerState<RestoreArchivedPage> createState() => _RestoreArchivedPageState();
}

class _RestoreArchivedPageState extends ConsumerState<RestoreArchivedPage> {
  final _alasan = TextEditingController();
  var _busy = false;
  String? _error;

  @override
  void dispose() {
    _alasan.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_busy) return;
    final reason = reasonError(_alasan.text);
    if (reason != null) {
      setState(() => _error = reason);
      return;
    }
    final ok = await confirm(context, title: '${widget.title} ${widget.name}?', message: widget.note, yes: 'Pulihkan');
    if (!ok || !mounted) return;
    setState(() => _busy = true);
    try {
      await ref.read(apiProvider).post(widget.path, data: {'alasan': _alasan.text.trim()});
      if (!mounted) return;
      await showResultDialog(context, title: widget.doneTitle, message: widget.doneMessage);
      if (!mounted) return;
      Navigator.pop(context, true);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.display);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(widget.note, style: const TextStyle(height: 1.35)),
        const SizedBox(height: 12),
        TextField(
          controller: _alasan,
          maxLength: 300,
          minLines: 2,
          maxLines: 4,
          decoration: InputDecoration(labelText: 'Alasan', errorText: _error, errorMaxLines: 4),
        ),
        const SizedBox(height: 16),
        BusyButton(label: widget.title, onPressed: _busy ? null : _save),
      ],
    ),
  );
}
