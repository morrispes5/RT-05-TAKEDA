import 'dart:math';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/emergency_platform.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';

const emergencyTermsVersion = '2026-10-27';
const emergencyKinds = {'kebakaran': 'Kebakaran', 'kemalingan': 'Kemalingan', 'penyerangan': 'Penyerangan', 'lainnya': 'Darurat serius lain'};
const emergencyWarning = 'Tombol ini bukan pengganti 112, damkar, atau polisi. Jika bantuan diperlukan segera, hubungi bantuan darurat langsung.';

String emergencyDeliveryText(Object? state) => switch (state) {
  'antrean' || 'diproses' => 'Laporan tersimpan. Pemberitahuan masih diproses. Jangan menunggu alarm untuk menghubungi bantuan langsung.',
  'gagal' || 'sebagian_gagal' => 'Pengiriman pemberitahuan mengalami kegagalan. Hubungi bantuan darurat dan pengurus langsung.',
  'dilewati' => 'Pemberitahuan tidak dikirim atau sudah kedaluwarsa. Hubungi pengurus atau bantuan darurat langsung.',
  'provider_menerima' => 'Layanan notifikasi menerima pengiriman. Ini belum membuktikan HP penerima menerima atau membunyikan alarm.',
  _ => 'Laporan tersimpan. Status pemberitahuan belum diketahui. Hubungi bantuan langsung bila diperlukan.',
};

String _key() {
  final r = Random.secure();
  final b = List<int>.generate(16, (_) => r.nextInt(256));
  b[6] = (b[6] & 15) | 64;
  b[8] = (b[8] & 63) | 128;
  final h = b.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
  return '${h.substring(0, 8)}-${h.substring(8, 12)}-${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
}

final emergencyStatusProvider = FutureProvider.autoDispose<Json>((ref) async {
  final session = ref.watch(sessionProvider);
  if (!session.readyForServices) return {};
  return Json.from(await ref.read(apiProvider).get('/emergencies/status') as Map);
}, retry: (_, _) => null);

class EmergencyEntry extends ConsumerWidget {
  const EmergencyEntry({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => ref
      .watch(emergencyStatusProvider)
      .maybeWhen(
        data: (s) => s['tersedia'] == true
            ? ListTile(
                leading: const Icon(Icons.emergency_outlined, color: Colors.red),
                title: const Text('Tombol panik'),
                subtitle: const Text('Laporan darurat dan penanganan RT'),
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EmergencyPage())),
              )
            : const SizedBox.shrink(),
        orElse: () => const SizedBox.shrink(),
      );
}

/// The landing page is step one. Shortcuts open here, never submit a report.
class EmergencyPage extends ConsumerStatefulWidget {
  const EmergencyPage({super.key});
  @override
  ConsumerState<EmergencyPage> createState() => _EmergencyPageState();
}

class _EmergencyPageState extends ConsumerState<EmergencyPage> {
  late final String? _owner = ref.read(sessionProvider).akun?.id;
  int _step = 0;
  String _kind = 'kebakaran';
  bool _read = false;
  bool _busy = false;
  Json? _pending;
  String _requestKey = _key();
  String? _error;
  Json? _settingsPending;
  final String _settingsKey = _key();
  Future<Json>? _history;

  bool _current(SessionState s, int epoch) => mounted && ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch);

  Future<void> _send() async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    if (!_current(s, epoch) || _busy) return;
    _pending ??= {'jenis': _kind, 'konfirmasi': true, 'versi_ketentuan': emergencyTermsVersion};
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = Json.from(
        await ref.read(apiProvider).post('/emergencies', data: _pending, idempotencyKey: _requestKey, bearer: s.token, sessionEpoch: epoch) as Map,
      );
      if (!mounted || !_current(s, epoch)) return;
      _pending = null;
      _requestKey = _key();
      _step = 0;
      _history = null;
      ref.invalidate(emergencyStatusProvider);
      await Navigator.push(context, MaterialPageRoute(builder: (_) => EmergencyDetailPage(id: '${result['id']}')));
    } on ApiException catch (e) {
      if (!mounted || !_current(s, epoch)) return;
      if (e.status > 0 && e.status < 500) _pending = null;
      setState(() => _error = _pending == null ? e.display : 'Hasil kirim belum diketahui. Coba kirim ulang dengan permintaan yang sama. $emergencyWarning');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _configure(Json status) async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    if (!_current(s, epoch)) return;
    _settingsPending ??= {'versi': status['versi'], 'aktif': status['aktif'] != true};
    try {
      await ref
          .read(apiProvider)
          .post('/admin/emergencies/settings', data: _settingsPending, idempotencyKey: _settingsKey, bearer: s.token, sessionEpoch: epoch);
      if (!mounted || !_current(s, epoch)) return;
      // A fresh widget/key for the next independent change.
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const EmergencyPage()));
      ref.invalidate(emergencyStatusProvider);
    } on ApiException catch (e) {
      if (!mounted || !_current(s, epoch)) return;
      if (e.status > 0 && e.status < 500) _settingsPending = null;
      showApiError(context, e);
      if (e.isConflict) ref.invalidate(emergencyStatusProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    if (!session.readyForServices || session.akun?.id != _owner) return const Scaffold(body: Center(child: Text('Sesi berubah. Buka kembali dari beranda.')));
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(title: const Text('Tombol panik')),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(emergencyWarning),
            TextButton(onPressed: () => ref.read(emergencyPlatformProvider).settings(), child: const Text('Periksa izin dan bunyi darurat di HP')),
            const SizedBox(height: 16),
            AsyncView(
              value: ref.watch(emergencyStatusProvider),
              onRetry: () => ref.invalidate(emergencyStatusProvider),
              builder: (status) {
                final blocked = (status['penyalahgunaan'] as num? ?? 0) >= 3;
                final allowed = status['tersedia'] == true && status['aktif'] == true && !blocked && status['versi_ketentuan'] == emergencyTermsVersion;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (status['uji_terbatas'] == true) const Text('Uji terbatas: pemberitahuan hanya untuk akun uji yang ditetapkan.'),
                    if (!allowed)
                      Text(
                        blocked
                            ? 'Tombol akun ini dinonaktifkan setelah tiga penyalahgunaan. Hubungi pengurus.'
                            : 'Tombol panik belum aktif. Hubungi pengurus jika membutuhkan bantuan.',
                      ),
                    if (allowed && _step == 0)
                      FilledButton.icon(
                        onPressed: () => setState(() {
                          _step = 1;
                          _read = false;
                        }),
                        icon: const Icon(Icons.emergency_outlined),
                        label: const Text('Mulai laporan darurat'),
                      ),
                    if (_step == 1 && allowed) ...[
                      const Text('Baca ketentuan dan pilih jenis darurat', style: TextStyle(fontWeight: FontWeight.bold)),
                      const Text(
                        'Nama dan rumah Anda beserta jenis darurat akan terlihat oleh semua warga dan pengurus penerima. Laporan tidak anonim. Penyalahgunaan diberi peringatan; tiga kali membuat tombol akun dinonaktifkan.',
                      ),
                      DropdownButtonFormField<String>(
                        initialValue: _kind,
                        isExpanded: true,
                        decoration: const InputDecoration(labelText: 'Jenis darurat'),
                        items: [for (final e in emergencyKinds.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
                        onChanged: (v) => setState(() => _kind = v!),
                      ),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _read,
                        title: const Text('Saya memahami ketentuan dan penyebaran identitas laporan darurat.'),
                        onChanged: (v) => setState(() => _read = v == true),
                      ),
                      FilledButton(onPressed: _read ? () => setState(() => _step = 2) : null, child: const Text('Lanjut ke konfirmasi')),
                      TextButton(onPressed: () => setState(() => _step = 0), child: const Text('Batal')),
                    ],
                    if (_step == 2) ...[
                      const Text('Kirim laporan darurat ini?', style: TextStyle(fontWeight: FontWeight.bold)),
                      Text('${emergencyKinds[_kind]}\n${session.akun!.nama}\n${session.akun!.alamat ?? 'Rumah diperiksa server'}'),
                      const Text(
                        'Server akan menyimpan laporan dan memproses pemberitahuan. Bunyi dan penerimaan bergantung pada jaringan, izin, dan pengaturan HP penerima.',
                      ),
                      if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                      BusyButton(label: _pending != null ? 'Coba kirim ulang' : 'Ya, kirim laporan', onPressed: allowed || _pending != null ? _send : null),
                      if (_pending == null) TextButton(onPressed: _busy ? null : () => setState(() => _step = 0), child: const Text('Batal')),
                    ],
                    if (session.pengurus && status['tersedia'] == true) ...[
                      const Divider(),
                      BusyButton(
                        label: _settingsPending != null
                            ? 'Coba ulang perubahan pengaturan'
                            : status['aktif'] == true
                            ? 'Nonaktifkan tombol panik RT'
                            : 'Aktifkan tombol panik RT',
                        onPressed: () => _configure(status),
                      ),
                    ],
                    const Divider(),
                    TextButton(
                      onPressed: () => setState(() => _history = ref.read(apiProvider).page('/emergencies')),
                      child: const Text('Muat laporan darurat'),
                    ),
                    if (_history != null)
                      FutureBuilder<Json>(
                        future: _history,
                        builder: (context, snap) {
                          if (snap.hasError) return Text('${snap.error}');
                          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
                          final rows = snap.data!['data'] as List;
                          if (rows.isEmpty) return const Text('Belum ada laporan darurat.');
                          return Column(
                            children: [
                              for (final row in rows)
                                ListTile(
                                  title: Text('${emergencyKinds[row['jenis']]} · ${row['status']}'),
                                  subtitle: Text('${row['pelapor']} · ${row['rumah']}'),
                                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EmergencyDetailPage(id: '${row['id']}'))),
                                ),
                            ],
                          );
                        },
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class EmergencyDetailPage extends ConsumerStatefulWidget {
  const EmergencyDetailPage({super.key, required this.id});
  final String id;
  @override
  ConsumerState<EmergencyDetailPage> createState() => _EmergencyDetailPageState();
}

class _EmergencyDetailPageState extends ConsumerState<EmergencyDetailPage> {
  late final String? _owner = ref.read(sessionProvider).akun?.id;
  late Future<dynamic> _detail = ref.read(apiProvider).get('/emergencies/${widget.id}');
  final _reason = TextEditingController();
  final _requestKey = _key();
  String _status = 'selesai';
  bool _misuse = false;
  Json? _pending;
  String? _error;
  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _resolve(Json e) async {
    final s = ref.read(sessionProvider);
    final epoch = ref.read(sessionProvider.notifier).epoch;
    bool current() => mounted && ref.read(sessionProvider.notifier).isCurrentActive(s, epoch: epoch);
    if (!current()) return;
    if (_reason.text.trim().length < 3) {
      setState(() => _error = 'Isi alasan penanganan, minimal tiga karakter.');
      return;
    }
    _pending ??= {'versi': e['versi'], 'status': _status, 'penyalahgunaan': _misuse, 'alasan': _reason.text.trim()};
    setState(() => _error = null);
    try {
      final updated = await ref
          .read(apiProvider)
          .post('/admin/emergencies/${widget.id}/resolve', data: _pending, idempotencyKey: _requestKey, bearer: s.token, sessionEpoch: epoch);
      if (!current()) return;
      setState(() {
        _pending = null;
        _detail = Future.value(updated);
      });
      ref.invalidate(emergencyStatusProvider);
    } on ApiException catch (error) {
      if (!current()) return;
      if (error.status > 0 && error.status < 500) _pending = null;
      setState(() {
        _error = error.display;
        if (error.isConflict) _detail = ref.read(apiProvider).get('/emergencies/${widget.id}');
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = ref.watch(sessionProvider);
    if (!s.readyForServices || s.akun?.id != _owner) return const Scaffold(body: Center(child: Text('Sesi berubah. Buka kembali dari beranda.')));
    return Scaffold(
      appBar: AppBar(title: const Text('Laporan darurat')),
      body: FutureBuilder<dynamic>(
        future: _detail,
        builder: (context, snap) {
          if (snap.hasError) {
            return ErrorState(message: '${snap.error}', onRetry: () => setState(() => _detail = ref.read(apiProvider).get('/emergencies/${widget.id}')));
          }
          if (!snap.hasData) return const Center(child: CircularProgressIndicator());
          final e = Json.from(snap.data as Map);
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text('${emergencyKinds[e['jenis']]}', style: Theme.of(context).textTheme.headlineSmall),
              Text('Pelapor: ${e['pelapor']}\nRumah: ${e['rumah']}\nStatus: ${e['status']}'),
              Text(emergencyDeliveryText(e['pemberitahuan'])),
              const SizedBox(height: 12),
              const Text(emergencyWarning),
              if (e['catatan_pengurus'] != null) Text('Catatan pengurus: ${e['catatan_pengurus']}'),
              if (s.pengurus && e['status'] == 'aktif') ...[
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: const InputDecoration(labelText: 'Hasil penanganan'),
                  items: const [
                    DropdownMenuItem(value: 'selesai', child: Text('Selesai')),
                    DropdownMenuItem(value: 'palsu', child: Text('Laporan palsu')),
                  ],
                  onChanged: _pending != null
                      ? null
                      : (v) => setState(() {
                          _status = v!;
                          if (v != 'palsu') _misuse = false;
                        }),
                ),
                if (_status == 'palsu')
                  CheckboxListTile(
                    value: _misuse,
                    title: const Text('Catat sebagai penyalahgunaan (menambah hitungan akun)'),
                    onChanged: _pending != null ? null : (v) => setState(() => _misuse = v == true),
                  ),
                TextField(
                  controller: _reason,
                  enabled: _pending == null,
                  maxLength: 1000,
                  decoration: const InputDecoration(labelText: 'Alasan penanganan (wajib)'),
                ),
                if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                BusyButton(label: _pending == null ? 'Simpan penanganan' : 'Coba ulang penanganan', onPressed: () => _resolve(e)),
              ],
            ],
          );
        },
      ),
    );
  }
}
