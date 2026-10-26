import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/widgets.dart';
import 'pengurus_pages.dart' show newKey;

/// Aksi keuangan pengurus (M12). Koreksi keuangan selalu lewat pembalikan: alasan wajib,
/// konfirmasi bahaya, satu Idempotency-Key per niat, lalu dialog hasil atau alert error standar.

/// Server: 422 bila kas akan negatif tanpa konfirmasi (BUSINESS_RULES: peringatan + alasan).
const negativeBalanceCode = 'NEGATIVE_BALANCE_CONFIRMATION_REQUIRED';

String cashJenisLabel(String jenis) =>
    const {'pengeluaran': 'Pengeluaran', 'pemasukan': 'Pemasukan lain', 'saldo_awal': 'Saldo awal', 'pembalikan': 'Pembalikan'}[jenis] ?? jenis;

/// Alasan minimal 5 karakter agar riwayat koreksi bisa dipahami pengurus lain.
String? reasonProblem(String? reason) => reason == null || reason.trim().length < 5 ? 'Alasan minimal 5 karakter.' : null;

Future<bool> reverseEntry(
  BuildContext context,
  WidgetRef ref, {
  required String path,
  required String title,
  required String done,
  required String effect,
}) async {
  final reason = await askText(context, title: title, label: 'Alasan (wajib)', hint: 'mis. salah rumah, nominal keliru');
  if (reason == null || !context.mounted) return false;
  final problem = reasonProblem(reason);
  if (problem != null) {
    showMessage(context, problem, kind: AlertKind.warning, title: 'Belum bisa dibalik');
    return false;
  }
  final ok = await confirm(
    context,
    title: title,
    message: '$effect\n\nAlasan: $reason\n\nData asal tidak dihapus; pembalikan tercatat di riwayat dan tidak bisa dibatalkan.',
    yes: 'Balik',
    danger: true,
    icon: Icons.undo,
  );
  if (!ok || !context.mounted) return false;
  try {
    await ref.read(apiProvider).post(path, data: {'alasan': reason.trim()}, idempotencyKey: newKey());
    if (context.mounted) await showResultDialog(context, title: done, message: effect);
    return true;
  } on ApiException catch (e) {
    if (context.mounted) showApiError(context, e);
    return false;
  }
}

/// UAT M15: ekspor CSV pengurus (iuran/rekap, kas, pembayaran, rumah). CSV ber-BOM UTF-8 dari server
/// langsung terbuka di Excel/Google Sheets. Disimpan lewat dialog "Simpan sebagai" Android.
typedef SaveBytes = Future<bool> Function(String fileName, Uint8List bytes);

Future<bool> _saveWithPicker(String fileName, Uint8List bytes) async =>
    await FilePicker.saveFile(
      fileName: fileName,
      bytes: bytes,
      mimeType: fileName.endsWith('.xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
      dialogTitle: 'Simpan laporan',
    ) !=
    null;

final saveBytesProvider = Provider<SaveBytes>((ref) => _saveWithPicker);

Future<void> exportCsv(BuildContext context, WidgetRef ref, {required String jenis, required String label, String? periode}) async {
  final fileName = 'rt05-$jenis${periode == null ? '' : '-$periode'}.csv';
  try {
    final bytes = await ref.read(apiProvider).download('/admin/exports/$jenis', query: {'periode': ?periode});
    final saved = await ref.read(saveBytesProvider)(fileName, bytes);
    if (!context.mounted || !saved) return;
    showMessage(context, '$label tersimpan sebagai $fileName. Buka dengan Excel atau Google Sheets.', title: 'Ekspor berhasil');
  } on ApiException catch (e) {
    if (context.mounted) showApiError(context, e);
  } catch (_) {
    if (context.mounted) showMessage(context, 'Berkas ekspor tidak dapat disimpan. Coba pilih folder lain.', error: true);
  }
}

class ExportButton extends ConsumerWidget {
  const ExportButton({super.key, required this.jenis, required this.label, this.periode});
  final String jenis;
  final String label;
  final String? periode;

  @override
  Widget build(BuildContext context, WidgetRef ref) => Align(
    alignment: Alignment.centerRight,
    child: OutlinedButton.icon(
      key: ValueKey('ekspor-$jenis'),
      onPressed: () => exportCsv(context, ref, jenis: jenis, label: label, periode: periode),
      icon: const Icon(Icons.download_outlined),
      label: const Text('Ekspor CSV'),
    ),
  );
}
