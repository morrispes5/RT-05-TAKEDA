import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import 'family_actions.dart';

/// NIK/KK harus tepat 16 digit; kosong = tidak diubah.
String? identityNumberError(String value, {required String label}) {
  final text = value.trim();
  if (text.isEmpty) return null;
  return RegExp(r'^\d{16}$').hasMatch(text) ? null : '$label harus 16 digit angka.';
}

/// Data kependudukan opsional (ADR35): nomor KK keluarga dan NIK tiap anggota. Dipakai akun utama untuk
/// keluarganya sendiri ([familyId] null → endpoint /household) dan pengurus untuk keluarga mana pun.
/// Server tidak pernah mengirim nilai utuh: yang tampil hanya bentuk tersamar. Kolom diisi untuk mengganti,
/// atau ditandai "Kosongkan" untuk menghapus.
class IdentityDataPage extends ConsumerStatefulWidget {
  const IdentityDataPage({super.key, this.familyId});

  /// Null: keluarga akun ini (warga, akun utama). Terisi: keluarga yang dikelola pengurus.
  final String? familyId;

  @override
  ConsumerState<IdentityDataPage> createState() => _IdentityDataPageState();
}

class _IdentityDataPageState extends ConsumerState<IdentityDataPage> {
  Json? _data;
  String? _loadError;
  final _kk = TextEditingController();
  bool _clearKk = false;
  final _nik = <String, TextEditingController>{};
  final _clearNik = <String>{};
  Map<String, String> _errors = {};
  var _busy = false;

  bool get _admin => widget.familyId != null;
  String get _familyPath => _admin ? '/admin/families/${widget.familyId}' : '/household';
  String _memberPath(String id) => _admin ? '/admin/residents/$id' : '/household/members/$id';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _kk.dispose();
    for (final c in _nik.values) {
      c.dispose();
    }
    super.dispose();
  }

  List<Map> get _members => ((_data?['anggota'] as List?) ?? const []).whereType<Map>().toList();

  Future<Json> _fetch() async => Json.from(await ref.read(apiProvider).get(_familyPath) as Map);

  Future<void> _load() async {
    try {
      final data = await _fetch();
      if (!mounted) return;
      setState(() {
        _data = data;
        _loadError = null;
        for (final m in _members) {
          _nik.putIfAbsent('${m['id']}', TextEditingController.new);
        }
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _loadError = e.display);
    }
  }

  Future<void> _save() async {
    if (_busy || _data == null) return;
    final errors = <String, String>{
      'kk': ?identityNumberError(_kk.text, label: 'Nomor KK'),
      for (final m in _members) '${m['id']}': ?identityNumberError(_nik['${m['id']}']!.text, label: 'NIK'),
    };
    setState(() => _errors = errors);
    if (errors.isNotEmpty) return;

    final kkChange = _clearKk ? null : _kk.text.trim();
    final changes = <String, String?>{
      for (final m in _members)
        if (_clearNik.contains('${m['id']}'))
          '${m['id']}': null
        else if (_nik['${m['id']}']!.text.trim().isNotEmpty)
          '${m['id']}': _nik['${m['id']}']!.text.trim(),
    };
    if (!_clearKk && (kkChange ?? '').isEmpty && changes.isEmpty) {
      showMessage(context, 'Belum ada yang diubah.', kind: AlertKind.info);
      return;
    }
    setState(() => _busy = true);
    final api = ref.read(apiProvider);
    try {
      // Versi keluarga naik pada tiap perubahan: ambil versi terbaru sebelum setiap permintaan.
      if (_clearKk || (kkChange ?? '').isNotEmpty) {
        final versi = readVersi(await _fetch());
        await api.patch(_familyPath, data: {'versi': versi, 'nomor_kk': _clearKk ? null : kkChange});
      }
      for (final entry in changes.entries) {
        final versi = readVersi(await _fetch());
        await api.patch(_memberPath(entry.key), data: {'versi': versi, 'nik': entry.value});
      }
      if (!mounted) return;
      await showResultDialog(context, title: 'Data tersimpan', message: 'NIK dan nomor KK disimpan terenkripsi dan hanya tampil tersamar.');
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      final field = e.fields['nomor_kk'] ?? e.fields['nik'];
      if (field != null) showMessage(context, field.join(' '), error: true);
      showApiError(context, e);
      await _load();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _numberField({
    required Key key,
    required String label,
    required String? masked,
    required TextEditingController controller,
    required bool cleared,
    required ValueChanged<bool> onClear,
    String? error,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          key: key,
          controller: controller,
          enabled: !cleared && !_busy,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(16)],
          decoration: InputDecoration(
            labelText: label,
            helperText: cleared
                ? 'Akan dikosongkan saat disimpan.'
                : (masked == null ? 'Belum diisi. Opsional, 16 digit.' : 'Tersimpan: $masked. Isi untuk mengganti.'),
            helperMaxLines: 2,
            errorText: error,
            counterText: '',
          ),
        ),
        if (masked != null)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: _busy ? null : () => onClear(!cleared),
              icon: Icon(cleared ? Icons.undo : Icons.delete_outline, size: 18),
              label: Text(cleared ? 'Batal kosongkan' : 'Kosongkan'),
            ),
          ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Data kependudukan')),
    body: _data == null
        ? Center(
            child: _loadError == null
                ? const CircularProgressIndicator()
                : Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_loadError!, textAlign: TextAlign.center),
                  ),
          )
        : ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Brand.sky, borderRadius: BorderRadius.circular(12)),
                child: Text(
                  _admin
                      ? 'Opsional. Isi sesuai data RT bila warga belum mengisinya sendiri. Data disimpan terenkripsi dan hanya tampil tersamar.'
                      : 'Opsional, boleh dikosongkan. Hanya pengurus RT dan keluarga Anda yang dapat melihat (tersamar). Data disimpan terenkripsi.',
                  style: const TextStyle(height: 1.4),
                ),
              ),
              const SizedBox(height: 16),
              Text('Kartu Keluarga', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              _numberField(
                key: const ValueKey('field-nomor-kk'),
                label: 'Nomor KK',
                masked: _data!['nomor_kk_tersamar'] as String?,
                controller: _kk,
                cleared: _clearKk,
                onClear: (v) => setState(() => _clearKk = v),
                error: _errors['kk'],
              ),
              const SizedBox(height: 8),
              Text('NIK anggota keluarga', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final m in _members)
                _numberField(
                  key: ValueKey('field-nik-${m['id']}'),
                  label: 'NIK ${m['nama']}',
                  masked: m['nik_tersamar'] as String?,
                  controller: _nik['${m['id']}']!,
                  cleared: _clearNik.contains('${m['id']}'),
                  onClear: (v) => setState(() => v ? _clearNik.add('${m['id']}') : _clearNik.remove('${m['id']}')),
                  error: _errors['${m['id']}'],
                ),
              const SizedBox(height: 8),
              BusyButton(label: 'Simpan', icon: Icons.save_outlined, onPressed: _busy ? null : _save),
            ],
          ),
  );
}
