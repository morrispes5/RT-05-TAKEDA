import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show FilteringTextInputFormatter;
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import 'admin_links.dart';
import 'birth_date.dart';

/// Saringan daftar warga: kelompok umur, rentang umur bebas, atau yang belum mengisi tanggal lahir.
typedef AgeQuery = ({String? kelompok, int? min, int? max, bool belumDiisi});

const _semua = (kelompok: null, min: null, max: null, belumDiisi: false);

final ageStatsProvider = FutureProvider.autoDispose.family<Json, AgeQuery>((ref, q) async {
  final data = await ref
      .read(apiProvider)
      .get('/admin/age-stats', query: {'kelompok': ?q.kelompok, 'min': ?q.min, 'max': ?q.max, if (q.belumDiisi) 'belum_diisi': 1});
  return Json.from(data as Map);
});

/// ADR42: statistik kelompok umur warga untuk pengurus (tab Warga → Statistik usia).
class AgeStatsTab extends ConsumerStatefulWidget {
  const AgeStatsTab({super.key});

  @override
  ConsumerState<AgeStatsTab> createState() => _AgeStatsTabState();
}

class _AgeStatsTabState extends ConsumerState<AgeStatsTab> {
  AgeQuery _q = _semua;
  bool _perRumah = false;
  final _min = TextEditingController();
  final _max = TextEditingController();
  String? _rangeError;

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  void _pilih(AgeQuery q) => setState(() {
    _q = q;
    _rangeError = null;
    if (q.min == null && q.max == null) {
      _min.clear();
      _max.clear();
    }
  });

  void _terapkanRentang() {
    final min = int.tryParse(_min.text.trim());
    final max = int.tryParse(_max.text.trim());
    if (min == null && max == null) {
      setState(() => _rangeError = 'Isi umur awal atau akhir.');
      return;
    }
    if (min != null && max != null && min > max) {
      setState(() => _rangeError = 'Umur awal tidak boleh lebih besar dari umur akhir.');
      return;
    }
    FocusScope.of(context).unfocus();
    _pilih((kelompok: null, min: min, max: max, belumDiisi: false));
  }

  String get _judulDaftar {
    if (_q.belumDiisi) return 'Belum mengisi tanggal lahir';
    if (_q.kelompok != null) return '${kelompokUsiaLabel[_q.kelompok]} (${kelompokUsiaRentang[_q.kelompok]})';
    if (_q.min != null || _q.max != null) return 'Umur ${_q.min ?? 0}–${_q.max != null ? '${_q.max}' : '…'} tahun';
    return 'Semua warga';
  }

  @override
  Widget build(BuildContext context) {
    final stats = ref.watch(ageStatsProvider(_q));
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(ageStatsProvider(_q)),
      child: AsyncView(
        value: stats,
        onRetry: () => ref.invalidate(ageStatsProvider(_q)),
        builder: (data) {
          final groups = ((data['kelompok'] as List?) ?? const []).whereType<Map>().toList();
          final warga = ((data['warga'] as List?) ?? const []).whereType<Map>().toList();
          final rumah = ((data['rumah'] as List?) ?? const []).whereType<Map>().toList();
          final dihitung = parseTanggalLahir(data['dihitung_pada']);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                'Total ${data['total_warga'] ?? 0} warga aktif • ${data['belum_diisi'] ?? 0} belum mengisi tanggal lahir'
                '${dihitung == null ? '' : '\nDihitung per ${tanggalLahirText(dihitung)}'}',
                style: const TextStyle(color: Brand.muted, height: 1.4),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final g in groups)
                    ChoiceChip(
                      key: ValueKey('kelompok-${g['kode']}'),
                      selected: _q.kelompok == g['kode'],
                      label: Text('${g['label']} (${kelompokUsiaRentang[g['kode']] ?? ''}): ${g['jumlah']}'),
                      onSelected: (on) => _pilih(on ? (kelompok: '${g['kode']}', min: null, max: null, belumDiisi: false) : _semua),
                    ),
                  ChoiceChip(
                    key: const ValueKey('kelompok-belum'),
                    selected: _q.belumDiisi,
                    label: Text('Belum diisi: ${data['belum_diisi'] ?? 0}'),
                    onSelected: (on) => _pilih(on ? (kelompok: null, min: null, max: null, belumDiisi: true) : _semua),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Rentang umur bebas',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            key: const ValueKey('umur-min'),
                            controller: _min,
                            keyboardType: TextInputType.number,
                            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                            maxLength: 3,
                            decoration: const InputDecoration(labelText: 'Dari umur', counterText: ''),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextField(
                            key: const ValueKey('umur-max'),
                            controller: _max,
                            keyboardType: TextInputType.number,
                            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                            maxLength: 3,
                            decoration: const InputDecoration(labelText: 'Sampai umur', counterText: ''),
                          ),
                        ),
                      ],
                    ),
                    if (_rangeError != null) ...[const SizedBox(height: 6), Text(_rangeError!, style: const TextStyle(color: Brand.danger, height: 1.35))],
                    const SizedBox(height: 8),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: OutlinedButton(key: const ValueKey('terapkan-rentang'), onPressed: _terapkanRentang, child: const Text('Tampilkan')),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              SegmentedButton<bool>(
                segments: const [
                  ButtonSegment(value: false, label: Text('Per warga')),
                  ButtonSegment(value: true, label: Text('Per rumah')),
                ],
                selected: {_perRumah},
                onSelectionChanged: (v) => setState(() => _perRumah = v.first),
              ),
              const SizedBox(height: 12),
              if (!_perRumah) ...[
                Text('$_judulDaftar • ${warga.length} orang', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (warga.isEmpty)
                  const Text('Tidak ada warga pada saringan ini.', style: TextStyle(color: Brand.muted))
                else
                  for (final w in warga) _WargaRow(warga: w, onChanged: () => ref.invalidate(ageStatsProvider(_q))),
              ] else ...[
                Text('${rumah.length} rumah', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                for (final r in rumah) _RumahRow(rumah: r),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _WargaRow extends StatelessWidget {
  const _WargaRow({required this.warga, required this.onChanged});

  final Map warga;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final umur = warga['umur'];
    final kelompok = kelompokUsiaLabel['${warga['kelompok_usia']}'];
    final rumah = (warga['rumah'] as Map?)?['kode_rumah'];
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text('${warga['nama']}'),
        subtitle: Text(
          [
            umur is num ? '$umur th • $kelompok' : 'Tanggal lahir belum diisi',
            if (rumah != null) 'Rumah $rumah',
            '${warga['hubungan_keluarga'] ?? ''}',
          ].where((s) => s.isNotEmpty).join(' • '),
        ),
        trailing: const Icon(Icons.edit_outlined),
        onTap: () async {
          final changed = await Navigator.push<bool>(
            context,
            MaterialPageRoute(
              builder: (_) => ResidentAdminPage(id: '${warga['id']}', familyId: '${warga['keluarga_id']}', versi: (warga['versi_keluarga'] as num?)?.toInt()),
            ),
          );
          if (changed == true) onChanged();
        },
      ),
    );
  }
}

class _RumahRow extends StatelessWidget {
  const _RumahRow({required this.rumah});

  final Map rumah;

  @override
  Widget build(BuildContext context) {
    final counts = (rumah['kelompok'] as Map?) ?? const {};
    final info = (rumah['rumah'] as Map?) ?? const {};
    final isi = [
      for (final kode in kelompokUsiaLabel.keys)
        if ((counts[kode] as num? ?? 0) > 0) '${kelompokUsiaLabel[kode]} ${counts[kode]}',
      if ((rumah['belum_diisi'] as num? ?? 0) > 0) 'Belum diisi ${rumah['belum_diisi']}',
    ];
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text('Rumah ${info['kode_rumah'] ?? '-'} • ${rumah['jumlah_anggota']} orang'),
        subtitle: Text(isi.join(' • '), style: const TextStyle(height: 1.35)),
      ),
    );
  }
}
