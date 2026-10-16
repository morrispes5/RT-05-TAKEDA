import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show PlatformException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import 'activity_proposal_actions.dart';
import 'providers.dart';
import 'report_actions.dart' show photoPickMessage;

/// Daftar usulan kegiatan. Warga hanya melihat miliknya; pengurus meninjau semuanya.
class ActivityProposalListPage extends ConsumerStatefulWidget {
  const ActivityProposalListPage({super.key, required this.admin});
  final bool admin;

  @override
  ConsumerState<ActivityProposalListPage> createState() => _ActivityProposalListPageState();
}

class _ActivityProposalListPageState extends ConsumerState<ActivityProposalListPage> {
  String? _status;

  @override
  Widget build(BuildContext context) {
    final list = ref.watch(activityProposalsProvider(_status));
    return Scaffold(
      appBar: AppBar(title: Text(widget.admin ? 'Usulan kegiatan' : 'Usulan saya')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(activityProposalsProvider(_status)),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              widget.admin
                  ? 'Hanya usulan yang disetujui yang dapat dimasukkan ke agenda resmi. Agenda tetap jadwal satu-satunya.'
                  : 'Usulkan senam, kerja bakti, atau kegiatan warga lain. Pengurus meninjau sebelum kegiatan masuk agenda RT.',
              style: const TextStyle(color: Brand.muted, height: 1.4),
            ),
            if (widget.admin) ...[
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final item in [null, 'diajukan', 'ditinjau', 'perlu_revisi', 'disetujui', 'ditolak'])
                    ChoiceChip(
                      label: Text(item == null ? 'Semua' : statusLabel(item)),
                      selected: _status == item,
                      onSelected: (_) => setState(() => _status = item),
                    ),
                ],
              ),
            ],
            const SizedBox(height: 12),
            AsyncView(
              value: list,
              onRetry: () => ref.invalidate(activityProposalsProvider(_status)),
              builder: (items) => items.isEmpty
                  ? const EmptyState(icon: Icons.event_busy_outlined, title: 'Belum ada usulan')
                  : Column(
                      children: [
                        for (final u in items)
                          Card(
                            margin: const EdgeInsets.only(bottom: 10),
                            child: ListTile(
                              title: Text('${u['nama']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              subtitle: Text('${tanggal(parseTime(u['mulai_at']), jam: true, hari: true)}\n${u['lokasi']}'),
                              isThreeLine: true,
                              trailing: StatusChip('${u['status']}'),
                              onTap: () async {
                                await Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => ActivityProposalDetailPage(id: '${u['id']}', admin: widget.admin)),
                                );
                                ref.invalidate(activityProposalsProvider(_status));
                              },
                            ),
                          ),
                      ],
                    ),
            ),
            if (!widget.admin) ...[
              const SizedBox(height: 8),
              BusyButton(
                label: 'Ajukan usulan',
                icon: Icons.add,
                onPressed: () async {
                  final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const ActivityProposalFormPage()));
                  if (saved == true) ref.invalidate(activityProposalsProvider(_status));
                },
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class ActivityProposalFormPage extends ConsumerStatefulWidget {
  const ActivityProposalFormPage({super.key, this.existing, this.picker});
  final Json? existing;
  final ImagePicker? picker;

  @override
  ConsumerState<ActivityProposalFormPage> createState() => _ActivityProposalFormPageState();
}

class _ActivityProposalFormPageState extends ConsumerState<ActivityProposalFormPage> {
  late final TextEditingController _nama;
  late final TextEditingController _lokasi;
  late final TextEditingController _deskripsi;
  late DateTime _mulai;
  DateTime? _selesai;
  final _photos = <XFile>[];
  String? _formError;

  bool get _editing => widget.existing != null;
  int get _existingDocs => (widget.existing?['dokumen'] as List?)?.length ?? 0;

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _nama = TextEditingController(text: e == null ? '' : '${e['nama']}');
    _lokasi = TextEditingController(text: e == null ? '' : '${e['lokasi']}');
    _deskripsi = TextEditingController(text: e == null ? '' : '${e['deskripsi']}');
    _mulai = e == null ? toJakarta(DateTime.now().toUtc()).add(const Duration(days: 1)) : toJakarta(parseTime(e['mulai_at']) ?? DateTime.now().toUtc());
    final selesai = e == null ? null : parseTime(e['selesai_at']);
    _selesai = selesai == null ? null : toJakarta(selesai);
  }

  @override
  void dispose() {
    _nama.dispose();
    _lokasi.dispose();
    _deskripsi.dispose();
    super.dispose();
  }

  Future<void> _pick(ImageSource source) async {
    try {
      final file = await (widget.picker ?? ImagePicker()).pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
      if (file != null && mounted) setState(() => _photos.add(file));
    } on PlatformException catch (e) {
      if (mounted) {
        showMessage(
          context,
          photoPickMessage(e.code, camera: source == ImageSource.camera) ?? 'Gambar tidak dapat diambil. Anda tetap dapat mengirim tanpa dokumen.',
          error: true,
        );
      }
    }
  }

  Future<void> _submit() async {
    if (_nama.text.trim().isEmpty || _lokasi.text.trim().isEmpty || _deskripsi.text.trim().isEmpty) {
      setState(() => _formError = 'Lengkapi nama, lokasi, dan deskripsi.');
      return;
    }
    if (_selesai != null && _selesai!.isBefore(_mulai)) {
      setState(() => _formError = 'Waktu selesai tidak boleh sebelum waktu mulai.');
      return;
    }
    setState(() => _formError = null);
    final body = proposalBody(
      nama: _nama.text,
      deskripsi: _deskripsi.text,
      lokasi: _lokasi.text,
      mulaiUtc: jakartaWallToUtc(_mulai),
      selesaiUtc: _selesai == null ? null : jakartaWallToUtc(_selesai!),
      versi: _editing ? widget.existing!['versi'] as int : null,
    );
    final api = ref.read(apiProvider);
    try {
      if (_editing && _photos.isEmpty) {
        await api.patch('/activity-proposals/${widget.existing!['id']}', data: body);
      } else {
        final form = FormData()..fields.addAll([for (final e in body.entries) MapEntry(e.key, '${e.value}')]);
        if (_editing) form.fields.add(const MapEntry('_method', 'PATCH'));
        for (final p in _photos) {
          form.files.add(MapEntry('dokumen[]', await MultipartFile.fromFile(p.path, filename: 'dokumen.jpg')));
        }
        final path = _editing ? '/activity-proposals/${widget.existing!['id']}' : '/activity-proposals';
        await api.post(path, data: form);
      }
      if (!mounted) return;
      showMessage(context, _editing ? 'Perubahan tersimpan.' : 'Usulan terkirim. Pengurus akan meninjau.');
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) setState(() => _formError = e.display);
    }
  }

  @override
  Widget build(BuildContext context) {
    final room = 3 - _existingDocs - _photos.length;
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'Perbaiki usulan' : 'Usulan kegiatan')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_editing)
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: Text('Perbaikan mengembalikan status ke Diajukan. Dokumen yang sudah ada tetap tersimpan.', style: TextStyle(color: Brand.muted)),
            ),
          TextField(controller: _nama, maxLength: 160, decoration: const InputDecoration(labelText: 'Nama kegiatan')),
          const SizedBox(height: 8),
          TextField(controller: _lokasi, maxLength: 200, decoration: const InputDecoration(labelText: 'Lokasi')),
          const SizedBox(height: 8),
          _WhenTile(
            label: 'Mulai',
            value: _mulai,
            onPick: (v) => setState(() => _mulai = v),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _selesai != null,
            title: const Text('Ada waktu selesai'),
            onChanged: (v) => setState(() => _selesai = v ? _mulai.add(const Duration(hours: 1)) : null),
          ),
          if (_selesai != null) _WhenTile(label: 'Selesai', value: _selesai!, onPick: (v) => setState(() => _selesai = v)),
          TextField(
            controller: _deskripsi,
            maxLines: 5,
            maxLength: 5000,
            decoration: const InputDecoration(labelText: 'Deskripsi kegiatan', alignLabelWithHint: true),
          ),
          const SizedBox(height: 8),
          Text('Gambar pendukung (opsional, maks. 3)', style: Theme.of(context).textTheme.titleMedium),
          if (_existingDocs > 0)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text('$_existingDocs dokumen sudah tersimpan.', style: const TextStyle(color: Brand.muted)),
            ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final (i, _) in _photos.indexed)
                InputChip(
                  label: Text('Gambar ${i + 1}'),
                  avatar: const Icon(Icons.image_outlined),
                  onDeleted: () => setState(() => _photos.removeAt(i)),
                ),
              if (room > 0) ...[
                ActionChip(avatar: const Icon(Icons.photo_camera_outlined), label: const Text('Kamera'), onPressed: () => _pick(ImageSource.camera)),
                ActionChip(avatar: const Icon(Icons.photo_library_outlined), label: const Text('Galeri'), onPressed: () => _pick(ImageSource.gallery)),
              ],
            ],
          ),
          const SizedBox(height: 6),
          const Text(
            'JPG, PNG, atau WebP. Server menyimpan ulang sebagai JPEG tanpa metadata lokasi. Berkas tidak tampil di agenda publik.',
            style: TextStyle(color: Brand.muted, fontSize: 13),
          ),
          if (_formError != null) ...[const SizedBox(height: 12), Text(_formError!, style: const TextStyle(color: Brand.danger, height: 1.35))],
          const SizedBox(height: 24),
          BusyButton(label: _editing ? 'Kirim perbaikan' : 'Kirim usulan', icon: Icons.send, onPressed: _submit),
        ],
      ),
    );
  }
}

class _WhenTile extends StatelessWidget {
  const _WhenTile({required this.label, required this.value, required this.onPick});
  final String label;
  final DateTime value;
  final ValueChanged<DateTime> onPick;

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: const Icon(Icons.schedule, color: Brand.navy),
    title: Text(
      '$label: ${value.day}/${value.month}/${value.year} ${value.hour.toString().padLeft(2, '0')}.${value.minute.toString().padLeft(2, '0')} WIB',
    ),
    onTap: () async {
      final d = await showDatePicker(
        context: context,
        initialDate: value,
        firstDate: DateTime.now().subtract(const Duration(days: 1)),
        lastDate: DateTime.now().add(const Duration(days: 365)),
      );
      if (d == null || !context.mounted) return;
      final t = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(value));
      onPick(DateTime(d.year, d.month, d.day, t?.hour ?? value.hour, t?.minute ?? value.minute));
    },
  );
}

class ActivityProposalDetailPage extends ConsumerWidget {
  const ActivityProposalDetailPage({super.key, required this.id, required this.admin});
  final String id;
  final bool admin;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(activityProposalDetailProvider(id));
    final api = ref.read(apiProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Detail usulan')),
      body: AsyncView(
        value: detail,
        onRetry: () => ref.invalidate(activityProposalDetailProvider(id)),
        builder: (d) {
          final (u, history) = d;
          final status = '${u['status']}';
          final versi = u['versi'] as int;
          final agendaId = u['agenda_id'];
          final mine = (u['pengusul'] as Map?)?['id'] == ref.watch(sessionProvider).akun?.id;
          final docs = (u['dokumen'] as List?)?.cast<Map>() ?? const [];
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(activityProposalDetailProvider(id)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                SectionCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${u['nama']}', style: Theme.of(context).textTheme.headlineSmall),
                      const SizedBox(height: 8),
                      StatusChip(status),
                      const SizedBox(height: 12),
                      Text(tanggal(parseTime(u['mulai_at']), jam: true, hari: true)),
                      if (u['selesai_at'] != null) Text('Selesai ${tanggal(parseTime(u['selesai_at']), jam: true)}'),
                      Text('${u['lokasi']}'),
                      if (admin && u['pengusul'] != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text('Pengusul: ${(u['pengusul'] as Map)['nama'] ?? '-'}', style: const TextStyle(color: Brand.muted)),
                        ),
                      const Divider(height: 24),
                      Text('${u['deskripsi']}', style: const TextStyle(height: 1.5)),
                      if (agendaId != null)
                        const Padding(
                          padding: EdgeInsets.only(top: 12),
                          child: Text('Usulan ini sudah masuk agenda resmi. Perubahan agenda tidak mengubah pengajuan.', style: TextStyle(color: Brand.navy)),
                        ),
                      if (docs.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Text('Dokumen pendukung', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        for (final doc in docs)
                          if ('${doc['mime']}'.startsWith('image/'))
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: Image.network(
                                  api.absolute('${doc['url']}'),
                                  headers: api.authHeaders,
                                  height: 180,
                                  width: double.infinity,
                                  fit: BoxFit.cover,
                                  semanticLabel: 'Dokumen pendukung usulan',
                                  errorBuilder: (_, _, _) => const ListTile(
                                    leading: Icon(Icons.broken_image_outlined),
                                    title: Text('Gambar tidak dapat ditampilkan'),
                                  ),
                                ),
                              ),
                            )
                          else
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              leading: const Icon(Icons.picture_as_pdf_outlined, color: Brand.navy),
                              title: Text('Dokumen PDF ${doc['urutan']}'),
                              subtitle: const Text('Berkas privat. Hanya pengusul dan pengurus yang dapat mengunduh.'),
                            ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                SectionCard(
                  title: 'Riwayat keputusan',
                  child: history.isEmpty
                      ? const Text('Belum ada riwayat.')
                      : Column(
                          children: [
                            for (final h in history)
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                leading: const Icon(Icons.history, color: Brand.navy),
                                title: Text(statusLabel('${h['status_ke']}'), style: const TextStyle(fontWeight: FontWeight.w700)),
                                subtitle: Text(
                                  [
                                    '${(h['aktor'] as Map)['nama']}',
                                    tanggal(parseTime(h['created_at']), jam: true),
                                    if (h['catatan'] != null) '"${h['catatan']}"',
                                  ].join(' • '),
                                ),
                              ),
                          ],
                        ),
                ),
                if (admin && agendaId == null && (status == 'diajukan' || status == 'ditinjau')) ...[
                  const SizedBox(height: 16),
                  if (status == 'diajukan')
                    BusyButton(label: 'Tandai ditinjau', icon: Icons.timelapse, outlined: true, onPressed: () => _decide(context, ref, api, status: 'ditinjau', versi: versi)),
                  const SizedBox(height: 8),
                  BusyButton(label: 'Minta revisi', icon: Icons.edit_note, outlined: true, onPressed: () => _decide(context, ref, api, status: 'perlu_revisi', versi: versi)),
                  const SizedBox(height: 8),
                  BusyButton(label: 'Setujui', icon: Icons.check, onPressed: () => _decide(context, ref, api, status: 'disetujui', versi: versi)),
                  const SizedBox(height: 8),
                  BusyButton(label: 'Tolak', icon: Icons.close, outlined: true, onPressed: () => _decide(context, ref, api, status: 'ditolak', versi: versi)),
                ],
                if (admin && proposalSchedulable(status, agendaId)) ...[
                  const SizedBox(height: 16),
                  BusyButton(label: 'Masukkan ke agenda resmi', icon: Icons.event_available, onPressed: () => _schedule(context, ref, api, versi)),
                ],
                if (mine && proposalEditable(status)) ...[
                  const SizedBox(height: 16),
                  BusyButton(
                    label: 'Perbaiki usulan',
                    icon: Icons.edit_outlined,
                    outlined: true,
                    onPressed: () async {
                      final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => ActivityProposalFormPage(existing: u)));
                      if (saved == true) ref.invalidate(activityProposalDetailProvider(id));
                    },
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _decide(BuildContext context, WidgetRef ref, Api api, {required String status, required int versi}) async {
    String? note;
    if (decisionNoteRequired(status)) {
      note = await askText(context, title: 'Catatan keputusan', label: 'Catatan wajib', hint: 'Jelaskan revisi atau alasan penolakan');
      if (note == null || !context.mounted) return;
    } else if (status == 'disetujui') {
      final ok = await confirm(context, title: 'Setujui usulan', message: 'Usulan yang disetujui dapat dimasukkan ke agenda resmi.', yes: 'Setujui');
      if (!ok || !context.mounted) return;
    }
    try {
      await api.post('/admin/activity-proposals/$id/status', data: {'status': status, 'catatan': note, 'expected_version': versi});
      ref.invalidate(activityProposalDetailProvider(id));
      if (context.mounted) showMessage(context, 'Keputusan tersimpan. Pengusul menerima notifikasi.');
    } on ApiException catch (e) {
      ref.invalidate(activityProposalDetailProvider(id));
      if (context.mounted) showMessage(context, e.display, error: true);
    }
  }

  Future<void> _schedule(BuildContext context, WidgetRef ref, Api api, int versi) async {
    final ok = await confirm(
      context,
      title: 'Masukkan ke agenda resmi',
      message: 'Nama, waktu, lokasi, dan deskripsi disalin sekali ke agenda. Suntingan agenda kemudian tidak mengubah usulan ini.',
      yes: 'Masukkan',
    );
    if (!ok || !context.mounted) return;
    try {
      await api.post('/admin/activity-proposals/$id/schedule', data: {'expected_version': versi});
      ref.invalidate(activityProposalDetailProvider(id));
      ref.invalidate(agendasProvider);
      if (context.mounted) showMessage(context, 'Usulan masuk agenda resmi. Warga menerima pemberitahuan agenda.');
    } on ApiException catch (e) {
      ref.invalidate(activityProposalDetailProvider(id));
      if (context.mounted) showMessage(context, e.display, error: true);
    }
  }
}
