import 'package:flutter/material.dart';

import '../app/theme.dart';
import 'format.dart';

/// UAT M15: bulan dipilih dari dialog (bukan diketik). Nilai controller tetap `YYYY-MM` sesuai API.
class MonthField extends StatelessWidget {
  const MonthField({super.key, required this.controller, required this.label, this.errorText, this.enabled = true, this.helperText});

  final TextEditingController controller;
  final String label;
  final String? errorText;
  final String? helperText;
  final bool enabled;

  Future<void> _pick(BuildContext context) async {
    final now = DateTime.now();
    final current = RegExp(r'^(\d{4})-(\d{2})').firstMatch(controller.text);
    final picked = await showDialog<String>(
      context: context,
      builder: (_) => _MonthDialog(
        initialYear: current == null ? now.year : int.parse(current.group(1)!),
        selected: current == null ? null : '${current.group(1)}-${current.group(2)}',
      ),
    );
    if (picked != null) controller.text = picked;
  }

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<TextEditingValue>(
    valueListenable: controller,
    builder: (context, value, _) {
      final text = value.text.trim();
      final valid = RegExp(r'^\d{4}-\d{2}').hasMatch(text);
      return Semantics(
        button: enabled,
        label: label,
        child: InkWell(
          onTap: enabled ? () => _pick(context) : null,
          borderRadius: BorderRadius.circular(12),
          child: InputDecorator(
            isEmpty: text.isEmpty,
            decoration: InputDecoration(
              labelText: label,
              hintText: 'Pilih bulan',
              helperText: helperText,
              helperMaxLines: 3,
              errorText: errorText,
              errorMaxLines: 3,
              enabled: enabled,
              prefixIcon: const Icon(Icons.calendar_month_outlined),
              suffixIcon: text.isEmpty || !enabled
                  ? null
                  : IconButton(tooltip: 'Kosongkan bulan', icon: const Icon(Icons.clear), onPressed: () => controller.clear()),
            ),
            child: Text(text.isEmpty ? '' : (valid ? periode(text.substring(0, 7)) : text)),
          ),
        ),
      );
    },
  );
}

class _MonthDialog extends StatefulWidget {
  const _MonthDialog({required this.initialYear, this.selected});
  final int initialYear;
  final String? selected;

  @override
  State<_MonthDialog> createState() => _MonthDialogState();
}

class _MonthDialogState extends State<_MonthDialog> {
  late int _year = widget.initialYear;
  static const _names = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Row(
      children: [
        IconButton(tooltip: 'Tahun sebelumnya', icon: const Icon(Icons.chevron_left), onPressed: () => setState(() => _year--)),
        Expanded(child: Text('$_year', textAlign: TextAlign.center)),
        IconButton(tooltip: 'Tahun berikutnya', icon: const Icon(Icons.chevron_right), onPressed: () => setState(() => _year++)),
      ],
    ),
    content: SizedBox(
      width: 300,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final (i, name) in _names.indexed)
            Builder(
              builder: (context) {
                final value = '$_year-${(i + 1).toString().padLeft(2, '0')}';
                return ChoiceChip(
                  label: SizedBox(width: 40, child: Text(name, textAlign: TextAlign.center)),
                  selected: value == widget.selected,
                  selectedColor: Brand.yellow,
                  onSelected: (_) => Navigator.pop(context, value),
                );
              },
            ),
        ],
      ),
    ),
    actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Batal'))],
  );
}
