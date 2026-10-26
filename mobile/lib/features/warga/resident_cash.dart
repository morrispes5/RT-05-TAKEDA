import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import '../pengurus/dues_admin.dart' show currentPeriod, shiftMonth;

final residentCashProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, period) async => Json.from(await ref.read(apiProvider).get('/cash/summary', query: {'periode': period}) as Map),
);

class ResidentCashPage extends ConsumerStatefulWidget {
  const ResidentCashPage({super.key});
  @override
  ConsumerState<ResidentCashPage> createState() => _ResidentCashPageState();
}

class _ResidentCashPageState extends ConsumerState<ResidentCashPage> {
  String _period = currentPeriod(DateTime.now().toUtc());

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Ringkasan kas RT')),
    body: RefreshIndicator(
      onRefresh: () async => ref.invalidate(residentCashProvider(_period)),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Ringkasan uang kas RT per bulan. Identitas warga dan rincian transaksi tidak ditampilkan.'),
          Row(
            children: [
              IconButton(tooltip: 'Bulan sebelumnya', onPressed: () => setState(() => _period = shiftMonth(_period, -1)), icon: const Icon(Icons.chevron_left)),
              Expanded(
                child: Text(periode(_period), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
              ),
              IconButton(tooltip: 'Bulan berikutnya', onPressed: () => setState(() => _period = shiftMonth(_period, 1)), icon: const Icon(Icons.chevron_right)),
            ],
          ),
          AsyncView(
            value: ref.watch(residentCashProvider(_period)),
            onRetry: () => ref.invalidate(residentCashProvider(_period)),
            builder: (d) => SectionCard(
              title: 'Kas ${periode(_period)}',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final field in const [
                    ('saldo_awal', 'Saldo awal bulan'),
                    ('modal_awal', 'Penetapan saldo awal kas'),
                    ('pemasukan', 'Uang masuk'),
                    ('pengeluaran', 'Uang keluar'),
                    ('koreksi', 'Koreksi tercatat'),
                  ])
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(field.$2),
                          Text(rupiah(d[field.$1] as num), style: const TextStyle(fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  const Divider(),
                  const Text('Saldo akhir bulan'),
                  FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Text(rupiah(d['saldo_akhir'] as num), style: Theme.of(context).textTheme.headlineMedium),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Uang masuk mengikuti penerimaan yang dicatat pengurus. Keringanan bukan uang masuk. Koreksi tetap tercatat pada bulan pembalikannya.',
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
