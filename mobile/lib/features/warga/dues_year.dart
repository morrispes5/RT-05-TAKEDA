import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';

const _months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/// Iuran rumah per tahun seperti rekap bendahara: 12 bulan sekilas, lalu rincian bulan pada tahun terpilih.
/// Bulan diputihkan (ADR53) bukan tunggakan; tanda TF muncul bila rekap/pencatatan menyebut transfer.
class DuesYearView extends StatefulWidget {
  const DuesYearView({super.key, required this.tagihan});
  final List<Tagihan> tagihan;

  @override
  State<DuesYearView> createState() => _DuesYearViewState();
}

class _DuesYearViewState extends State<DuesYearView> {
  int? _year;

  @override
  Widget build(BuildContext context) {
    final years = {for (final t in widget.tagihan) int.parse(t.periode.substring(0, 4))}.toList()..sort((a, b) => b.compareTo(a));
    if (years.isEmpty) return const EmptyState(icon: Icons.receipt_long_outlined, title: 'Belum ada tagihan');
    final now = toJakarta(DateTime.now().toUtc()).year;
    final year = _year != null && years.contains(_year) ? _year! : years.firstWhere((y) => y <= now, orElse: () => years.first);
    final ofYear = {
      for (final t in widget.tagihan)
        if (t.periode.startsWith('$year-')) int.parse(t.periode.substring(5, 7)): t,
    };
    final paid = ofYear.values.fold<int>(0, (sum, t) => sum + t.terbayar);
    final due = ofYear.values.fold<int>(0, (sum, t) => sum + t.sisa);
    final details = ofYear.keys.toList()..sort((a, b) => b.compareTo(a));
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (years.length > 1)
          Wrap(
            spacing: 8,
            children: [for (final y in years) ChoiceChip(label: Text('$y'), selected: y == year, onSelected: (_) => setState(() => _year = y))],
          ),
        const SizedBox(height: 8),
        SectionCard(
          title: 'Iuran $year',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LayoutBuilder(
                builder: (context, box) {
                  final columns = box.maxWidth < 300 ? 3 : 4;
                  final width = (box.maxWidth - 6 * (columns - 1)) / columns;
                  return Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (var m = 1; m <= 12; m++)
                        SizedBox(
                          width: width,
                          child: _MonthTile(month: _months[m - 1], tagihan: ofYear[m]),
                        ),
                    ],
                  );
                },
              ),
              const SizedBox(height: 10),
              Text('Dibayar ${rupiah(paid)} • Tunggakan ${rupiah(due)}', style: const TextStyle(color: Brand.muted, fontSize: 13)),
            ],
          ),
        ),
        const SizedBox(height: 8),
        for (final m in details) _DuesCard(ofYear[m]!),
      ],
    );
  }
}

({String text, Color fg, Color bg}) _state(Tagihan? t) {
  if (t == null) return (text: '–', fg: Brand.muted, bg: Brand.sky);
  if (t.label == 'Diputihkan') return (text: 'Diputihkan', fg: Brand.muted, bg: Brand.line);
  if (t.sisa == 0) return (text: 'Lunas', fg: Brand.success, bg: Brand.successBg);
  if (t.terbayar > 0) return (text: 'Kurang', fg: Brand.warning, bg: Brand.warningBg);
  return (text: 'Belum', fg: Brand.danger, bg: Brand.dangerBg);
}

bool _transfer(Tagihan t) => t.raw['buku_lama_metode'] == 'transfer';

class _MonthTile extends StatelessWidget {
  const _MonthTile({required this.month, required this.tagihan});
  final String month;
  final Tagihan? tagihan;

  @override
  Widget build(BuildContext context) {
    final s = _state(tagihan);
    return Semantics(
      label: '$month: ${s.text}',
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
        decoration: BoxDecoration(color: s.bg, borderRadius: BorderRadius.circular(10)),
        child: Column(
          children: [
            Text(month, style: const TextStyle(fontWeight: FontWeight.w700)),
            FittedBox(
              child: Text(
                tagihan != null && _transfer(tagihan!) ? '${s.text} • TF' : s.text,
                style: TextStyle(color: s.fg, fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DuesCard extends StatelessWidget {
  const _DuesCard(this.t);
  final Tagihan t;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 8),
    // Wrap, bukan ListTile: label status turun ke baris berikutnya pada skala teks besar.
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
              Text(periode(t.periode), style: const TextStyle(fontWeight: FontWeight.w700)),
              Text(rupiah(t.nominal), style: const TextStyle(color: Brand.muted)),
              if (t.raw.containsKey('sisa') && t.label != 'Diputihkan') ...[
                Text('Dibayar ${rupiah(t.terbayar)}${_transfer(t) ? ' (transfer)' : ''}'),
                if (t.keringanan > ((t.raw['keringanan_sukarela'] as num?) ?? 0)) Text('Keringanan ${rupiah(t.keringanan)}'),
                if (t.sisa > 0) Text('Sisa ${rupiah(t.sisa)}'),
              ],
              if (t.label == 'Diputihkan') const Text('Tidak ditagih (pemutihan RT)', style: TextStyle(color: Brand.muted)),
            ],
          ),
          StatusChip(t.status, label: t.label),
        ],
      ),
    ),
  );
}
