import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../app/theme.dart';
import 'api.dart';
import 'format.dart';

/// Tampilan AsyncValue standar: loading, error + coba lagi, data. Dipakai di setiap alur.
class AsyncView<T> extends StatelessWidget {
  const AsyncView({super.key, required this.value, required this.builder, this.onRetry});

  final AsyncValue<T> value;
  final Widget Function(T data) builder;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => value.when(
        data: builder,
        loading: () => const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator())),
        error: (e, _) => ErrorState(message: e is ApiException ? e.display : 'Terjadi kesalahan. Coba lagi.', onRetry: onRetry),
      );
}

class ErrorState extends StatelessWidget {
  const ErrorState({super.key, required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_rounded, size: 48, color: Brand.muted),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Coba lagi')),
            ],
          ]),
        ),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.message});
  final IconData icon;
  final String title;
  final String? message;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 52, color: Brand.navy.withValues(alpha: 0.35)),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.titleMedium, textAlign: TextAlign.center),
            if (message != null) ...[const SizedBox(height: 6), Text(message!, textAlign: TextAlign.center, style: const TextStyle(color: Brand.muted))],
          ]),
        ),
      );
}

/// Label status: warna SELALU disertai teks dan ikon (PRD FR14, aksesibilitas).
class StatusChip extends StatelessWidget {
  const StatusChip(this.status, {super.key, this.label});
  final String status;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final (fg, bg, icon) = switch (status) {
      'lunas' || 'selesai' || 'aktif' || 'disetujui' || 'tercatat' => (Brand.success, Brand.successBg, Icons.check_circle_rounded),
      'belum_bayar' || 'ditolak' || 'dibatalkan' || 'nonaktif' => (Brand.danger, Brand.dangerBg, Icons.error_rounded),
      'diproses' || 'ditinjau' || 'menunggu' => (Brand.warning, Brand.warningBg, Icons.timelapse_rounded),
      _ => (Brand.navy, Brand.sky, Icons.radio_button_checked_rounded),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 15, color: fg),
        const SizedBox(width: 5),
        Text(label ?? statusLabel(status), style: TextStyle(color: fg, fontWeight: FontWeight.w700, fontSize: 12.5)),
      ]),
    );
  }
}

/// Tombol aksi yang terkunci selama request berjalan (anti ketuk ganda, docs/MOBILE.md).
class BusyButton extends StatefulWidget {
  const BusyButton({super.key, required this.label, required this.onPressed, this.icon, this.outlined = false});
  final String label;
  final Future<void> Function()? onPressed;
  final IconData? icon;
  final bool outlined;

  @override
  State<BusyButton> createState() => _BusyButtonState();
}

class _BusyButtonState extends State<BusyButton> {
  bool _busy = false;

  Future<void> _run() async {
    setState(() => _busy = true);
    try {
      await widget.onPressed!();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final child = _busy
        ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
        : Row(mainAxisSize: MainAxisSize.min, children: [
            if (widget.icon != null) ...[Icon(widget.icon, size: 20), const SizedBox(width: 8)],
            Text(widget.label),
          ]);
    final onPressed = _busy || widget.onPressed == null ? null : _run;
    return widget.outlined ? OutlinedButton(onPressed: onPressed, child: child) : FilledButton(onPressed: onPressed, child: child);
  }
}

class SectionCard extends StatelessWidget {
  const SectionCard({super.key, this.title, this.trailing, required this.child, this.padding = const EdgeInsets.all(16)});
  final String? title;
  final Widget? trailing;
  final Widget child;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: padding,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (title != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(children: [
                  Expanded(child: Text(title!, style: Theme.of(context).textTheme.titleMedium)),
                  ?trailing,
                ]),
              ),
            child,
          ]),
        ),
      );
}

/// Logo gapura RT05 TAKEDA (versi native sederhana dari mark website).
class GapuraMark extends StatelessWidget {
  const GapuraMark({super.key, this.size = 44, this.light = false});
  final double size;
  final bool light;

  @override
  Widget build(BuildContext context) => SizedBox(
        width: size,
        height: size,
        child: CustomPaint(painter: _GapuraPainter(light ? Colors.white : Brand.navy, Brand.yellow)),
      );
}

class _GapuraPainter extends CustomPainter {
  _GapuraPainter(this.main, this.accent);
  final Color main;
  final Color accent;

  @override
  void paint(Canvas canvas, Size s) {
    final p = Paint()..color = main;
    final w = s.width, h = s.height;
    canvas.drawRRect(RRect.fromLTRBR(w * .12, h * .30, w * .30, h * .92, Radius.circular(w * .03)), p);
    canvas.drawRRect(RRect.fromLTRBR(w * .70, h * .30, w * .88, h * .92, Radius.circular(w * .03)), p);
    final arch = Path()
      ..moveTo(w * .06, h * .34)
      ..quadraticBezierTo(w * .5, h * .02, w * .94, h * .34)
      ..lineTo(w * .94, h * .44)
      ..quadraticBezierTo(w * .5, h * .14, w * .06, h * .44)
      ..close();
    canvas.drawPath(arch, p);
    canvas.drawRRect(RRect.fromLTRBR(w * .38, h * .78, w * .62, h * .86, Radius.circular(w * .02)), Paint()..color = accent);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

void showMessage(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), backgroundColor: error ? Brand.danger : Brand.navyDeep));
}

Future<bool> confirm(BuildContext context, {required String title, required String message, String yes = 'Ya, lanjutkan'}) async =>
    await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Batal')),
          FilledButton(onPressed: () => Navigator.pop(c, true), style: FilledButton.styleFrom(minimumSize: const Size(0, 44)), child: Text(yes)),
        ],
      ),
    ) ??
    false;

Future<String?> askText(BuildContext context, {required String title, required String label, String? hint}) async {
  final ctrl = TextEditingController();
  final result = await showDialog<String>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(title),
      content: TextField(controller: ctrl, autofocus: true, maxLines: 3, decoration: InputDecoration(labelText: label, hintText: hint)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c), child: const Text('Batal')),
        FilledButton(
          onPressed: () => Navigator.pop(c, ctrl.text.trim()),
          style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
          child: const Text('Simpan'),
        ),
      ],
    ),
  );
  return (result == null || result.isEmpty) ? null : result;
}
