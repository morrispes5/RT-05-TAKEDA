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
