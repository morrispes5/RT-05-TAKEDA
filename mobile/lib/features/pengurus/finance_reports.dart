import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import 'finance_actions.dart';

final financeReportProvider = FutureProvider.autoDispose.family<Json, int>(
  (ref, year) async => Json.from(await ref.read(apiProvider).get('/admin/finance-reports', query: {'tahun': year}) as Map),
);

class FinanceReportsPage extends ConsumerStatefulWidget {
  const FinanceReportsPage({super.key});
  @override
  ConsumerState<FinanceReportsPage> createState() => _FinanceReportsPageState();
}

class _FinanceReportsPageState extends ConsumerState<FinanceReportsPage> {
  int _year = toJakarta(DateTime.now().toUtc()).year;

  Future<void> _export(String extension) async {
    final name = 'rt05-keuangan-$_year.$extension';
    try {
      final bytes = await ref.read(apiProvider).download('/admin/exports/keuangan.$extension', query: {'tahun': _year});
      final saved = await ref.read(saveBytesProvider)(name, bytes);
      if (mounted && saved) showMessage(context, 'Laporan tersimpan sebagai $name.', title: 'Ekspor berhasil');
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    } catch (_) {
      if (mounted) showMessage(context, 'Berkas tidak dapat disimpan. Coba pilih folder lain.', error: true);
    }
  }

  Widget _figures(Map data) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      for (final entry in const [
        ('saldo_awal', 'Saldo awal'),
        ('modal_awal', 'Modal awal'),
        ('pemasukan', 'Uang masuk'),
        ('pengeluaran', 'Uang keluar'),
        ('koreksi', 'Koreksi kas'),
        ('saldo_akhir', 'Saldo akhir'),
      ])
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Wrap(
            alignment: WrapAlignment.spaceBetween,
            spacing: 12,
            children: [
              Text(entry.$2),
              Text(rupiah(data[entry.$1] as num), style: const TextStyle(fontWeight: FontWeight.w700)),
            ],
          ),
        ),
    ],
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Laporan keuangan')),
    body: RefreshIndicator(
      onRefresh: () async => ref.invalidate(financeReportProvider(_year)),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField<int>(
            initialValue: _year,
            decoration: const InputDecoration(labelText: 'Tahun kalender'),
            items: [for (var year = 2000; year <= toJakarta(DateTime.now().toUtc()).year + 1; year++) DropdownMenuItem(value: year, child: Text('$year'))],
            onChanged: (value) => setState(() => _year = value!),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              BusyButton(label: 'Unduh Excel', icon: Icons.table_chart_outlined, onPressed: () => _export('xlsx')),
              BusyButton(label: 'Unduh CSV', icon: Icons.download_outlined, outlined: true, onPressed: () => _export('csv')),
            ],
          ),
          const SizedBox(height: 16),
          AsyncView(
            value: ref.watch(financeReportProvider(_year)),
            onRetry: () => ref.invalidate(financeReportProvider(_year)),
            builder: (d) => Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SectionCard(title: 'Ringkasan $_year', child: _figures(d['total'] as Map)),
                const SizedBox(height: 12),
                Text('${d['catatan']}\nDibuat ${tanggal(parseTime(d['posisi_pelunasan']), jam: true)}', style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 12),
                for (final raw in (d['bulan'] as List).cast<Map>())
                  Card(
                    child: ExpansionTile(
                      title: Text(periode('${raw['periode']}')),
                      subtitle: Text('Saldo akhir ${rupiah((raw['kas'] as Map)['saldo_akhir'] as num)}'),
                      children: [
                        Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _figures(raw['kas'] as Map),
                              const Divider(),
                              Text('Bagian iuran neto: ${rupiah(raw['iuran_diterima'] as num)}'),
                              Text('Sumbangan neto: ${rupiah(raw['sumbangan_diterima'] as num)}'),
                              Text('Titipan diterima neto: ${rupiah(raw['titipan_diterima'] as num)}'),
                              Text('Titipan dipakai neto: ${rupiah(raw['titipan_dipakai'] as num)}'),
                              const Divider(),
                              Text('Tagihan efektif: ${rupiah((raw['tagihan'] as Map)['nominal_efektif'] as num)}'),
                              Text('Terbayar saat ini: ${rupiah((raw['tagihan'] as Map)['terbayar'] as num)}'),
                              Text('Keringanan: ${rupiah((raw['tagihan'] as Map)['keringanan'] as num)}'),
                              Text('Sisa: ${rupiah((raw['tagihan'] as Map)['sisa'] as num)}'),
                              const SizedBox(height: 12),
                              for (final cash in (raw['mutasi_kas'] as List).cast<Map>())
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: Text('${cash['tanggal']} • ${rupiah(cash['nominal'] as num)}'),
                                  subtitle: Text('${cash['kategori']} • ${cash['keterangan']}'),
                                ),
                              for (final bill in (raw['rumah'] as List).cast<Map>())
                                ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: Text('${bill['kode_rumah']} • ${bill['status_label']}'),
                                  subtitle: Text(
                                    'Terbayar ${rupiah(bill['terbayar'] as num)} • Keringanan ${rupiah(bill['keringanan'] as num)} • Sisa ${rupiah(bill['sisa'] as num)}',
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
