import 'dart:io' show File;

import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show PlatformException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import 'activity_proposal_actions.dart';
import 'providers.dart';
import 'report_actions.dart' show photoPickMessage;

/// Pemilih berkas yang dapat diganti pada tes (default: plugin file_picker).
typedef PickDocument = Future<({String name, String path, int bytes})?> Function();

Future<({String name, String path, int bytes})?> _pickDocumentWithPlugin() async {
  final files = await FilePicker.pickFiles(type: FileType.custom, allowedExtensions: const ['pdf', 'txt']);
  final f = files.firstOrNull;
  final path = f?.path;
  if (f == null || path == null) return null;
  return (name: f.name, path: path, bytes: await File(path).length());
}

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
      appBar: AppBar(title: Text(widget.admin ? 'Usulan kegiatan warga' : 'Usulan kegiatan saya')),
      floatingActionButton: widget.admin
          ? null
          : FloatingActionButton.extended(
              icon: const Icon(Icons.add),
              label: const Text('Ajukan kegiatan'),
              onPressed: () async {
                final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const ActivityProposalFormPage()));
                if (saved == true) ref.invalidate(activityProposalsProvider(_status));
              },
            ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(activityProposalsProvider(_status)),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            Text(
              widget.admin
                  ? 'Usulan yang Anda setujui langsung masuk agenda RT dan semua warga aktif diberi tahu.'
                  : 'Usulkan kegiatan warga seperti olahraga, kerja bakti, atau perayaan. Pengurus meninjau sebelum kegiatan masuk agenda RT.',
              style: const TextStyle(color: Brand.muted, height: 1.4),
            ),
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
            const SizedBox(height: 12),
            AsyncView(
              value: list,
              onRetry: () => ref.invalidate(activityProposalsProvider(_status)),
              builder: (items) => items.isEmpty
                  ? EmptyState(
                      icon: Icons.event_note_outlined,
                      title: widget.admin ? 'Belum ada usulan' : 'Belum ada usulan Anda',
                      message: widget.admin ? null : 'Tekan "Ajukan kegiatan" untuk mengusulkan kegiatan kepada pengurus RT.',
                    )
                  : Column(
                      children: [
                        for (final u in items)
                          Card(
                            margin: const EdgeInsets.only(bottom: 10),
                            child: ListTile(
                              contentPadding: const EdgeInsets.fromLTRB(16, 8, 12, 8),
                              title: Text('${u['nama']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              subtitle: Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  [
                                    proposalJenis['${u['jenis']}'] ?? '',
                                    tanggal(parseTime(u['mulai_at']), jam: true, hari: true),
                                    '${u['lokasi']}',
                                    if (widget.admin && u['pengusul'] != null) 'Pengusul: ${(u['pengusul'] as Map)['nama'] ?? '-'}',
                                  ].where((s) => s.isNotEmpty).join('\n'),
                                  style: const TextStyle(height: 1.4),
                                ),
                              ),
                              trailing: StatusChip('${u['status']}'),
                              onTap: () async {
                                await Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ActivityProposalDetailPage(id: '${u['id']}', admin: widget.admin),
                                  ),
                                );
                                ref.invalidate(activityProposalsProvider(_status));
                              },
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
}

/// Lampiran yang dipilih di perangkat dan belum dikirim.
class _Attachment {
  _Attachment(this.name, this.path, this.kind);
  final String name;
  final String path;
  final String kind;
}

/// Form pengajuan/perbaikan usulan (docs/USULAN_KEGIATAN.md §3). Validasi tampil di dekat field
/// sebelum mengirim; konfirmasi ringkasan sebelum kirim; dialog hasil setelah server menerima.
class ActivityProposalFormPage extends ConsumerStatefulWidget {
  const ActivityProposalFormPage({super.key, this.existing, this.picker, this.pickDocument, this.now});
  final Json? existing;
  final ImagePicker? picker;
  final PickDocument? pickDocument;

  /// Jam dinding Jakarta "sekarang" (diganti pada tes).
  final DateTime Function()? now;

  @override
  ConsumerState<ActivityProposalFormPage> createState() => _ActivityProposalFormPageState();
}

class _ActivityProposalFormPageState extends ConsumerState<ActivityProposalFormPage> {
  late final TextEditingController _nama;
  late final TextEditingController _lokasi;
  late final TextEditingController _deskripsi;
  late final TextEditingController _peserta;
  late final TextEditingController _kebutuhan;
  String? _jenis;
  DateTime? _mulai;
  DateTime? _selesai;
  bool _pernyataan = false;
  final _attachments = <_Attachment>[];
  Map<String, String> _errors = const {};

  bool get _editing => widget.existing != null;
  int get _existingDocs => (widget.existing?['dokumen'] as List?)?.length ?? 0;
  DateTime _nowJakarta() => widget.now?.call() ?? toJakarta(DateTime.now().toUtc());

  @override
  void initState() {
    super.initState();
    final e = widget.existing;
    _nama = TextEditingController(text: e == null ? '' : '${e['nama']}');
    _lokasi = TextEditingController(text: e == null ? '' : '${e['lokasi']}');
    _deskripsi = TextEditingController(text: e == null ? '' : '${e['deskripsi']}');
    _peserta = TextEditingController(text: e?['perkiraan_peserta'] == null ? '' : '${e!['perkiraan_peserta']}');
    _kebutuhan = TextEditingController(text: e?['kebutuhan'] == null ? '' : '${e!['kebutuhan']}');
    _jenis = e?['jenis'] as String?;
    final mulai = e == null ? null : parseTime(e['mulai_at']);
    final selesai = e == null ? null : parseTime(e['selesai_at']);
    _mulai = mulai == null ? null : toJakarta(mulai);
    _selesai = selesai == null ? null : toJakarta(selesai);
  }

  @override
  void dispose() {
    for (final c in [_nama, _lokasi, _deskripsi, _peserta, _kebutuhan]) {
      c.dispose();
    }
    super.dispose();
  }

  ProposalDraft get _draft => ProposalDraft(
    jenis: _jenis,
    nama: _nama.text,
    deskripsi: _deskripsi.text,
    lokasi: _lokasi.text,
    mulai: _mulai,
    selesai: _selesai,
    peserta: _peserta.text,
    kebutuhan: _kebutuhan.text,
    pernyataan: _pernyataan,
  );

  void _addAttachment(String name, String path, int bytes) {
    final problem = documentProblem(name, bytes, _existingDocs + _attachments.length);
    if (problem != null) {
      showMessage(context, problem, kind: AlertKind.error, title: 'Dokumen tidak dapat ditambahkan');
      return;
    }
    setState(() => _attachments.add(_Attachment(name, path, documentKind(name)!)));
    showMessage(context, '$name ditambahkan.', kind: AlertKind.info, title: 'Dokumen siap dikirim');
  }

  Future<void> _pickFile() async {
    try {
      final f = await (widget.pickDocument ?? _pickDocumentWithPlugin)();
      if (f != null && mounted) _addAttachment(f.name, f.path, f.bytes);
    } on PlatformException {
      if (mounted) showMessage(context, 'Berkas tidak dapat dibuka. Coba pilih berkas lain.', kind: AlertKind.error);
    }
  }

  Future<void> _pickPhoto(ImageSource source) async {
    try {
      final file = await (widget.picker ?? ImagePicker()).pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
      if (file != null && mounted) _addAttachment(file.name.contains('.') ? file.name : 'foto.jpg', file.path, await file.length());
    } on PlatformException catch (e) {
      if (mounted) {
        showMessage(
          context,
          photoPickMessage(e.code, camera: source == ImageSource.camera) ?? 'Foto tidak dapat diambil. Anda tetap dapat mengirim tanpa dokumen.',
          kind: AlertKind.error,
        );
      }
    }
  }

  Future<void> _pickWhen({required bool start}) async {
    final now = _nowJakarta();
    final first = DateTime(now.year, now.month, now.day);
    final last = now.add(const Duration(days: 365));
    final base = (start ? _mulai : _selesai) ?? _mulai ?? now.add(const Duration(days: 2));
    // UAT M15: usulan lama yang tanggalnya sudah lewat atau terlalu jauh tidak boleh membuat pemilih tanggal crash.
    final initial = base.isBefore(first) ? first : (base.isAfter(last) ? last : base);
    final day = start || _mulai == null
        ? await showDatePicker(
            context: context,
            initialDate: initial,
            firstDate: first,
            lastDate: last,
            helpText: start ? 'Tanggal kegiatan' : 'Tanggal selesai',
          )
        : _mulai;
    if (day == null || !mounted) return;
    // Jam selesai dipilih sebelum jam mulai: pakai tanggal yang dipilih, bukan _mulai yang masih kosong.
    final fallback = _mulai?.add(const Duration(hours: 2)) ?? base;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(start ? base : (_selesai ?? fallback)),
      helpText: start ? 'Jam mulai (WIB)' : 'Jam selesai (WIB)',
    );
    if (time == null || !mounted) return;
    setState(() {
      final picked = DateTime(day.year, day.month, day.day, time.hour, time.minute);
      if (start) {
        final keep = _selesai == null || _mulai == null ? const Duration(hours: 2) : _selesai!.difference(_mulai!);
        _mulai = picked;
        _selesai = picked.add(keep.isNegative ? const Duration(hours: 2) : keep);
      } else {
        _selesai = picked;
      }
      _errors = {..._errors}
        ..remove('mulai_at')
        ..remove('selesai_at');
    });
  }

  Future<void> _submit() async {
    final draft = _draft;
    final errors = validateProposal(draft, _nowJakarta());
    setState(() => _errors = errors);
    if (errors.isNotEmpty) {
      showMessage(context, 'Periksa ${errors.length} isian yang ditandai merah.', kind: AlertKind.error, title: 'Usulan belum dapat dikirim');
      return;
    }
    final docs = _existingDocs + _attachments.length;
    final ok = await confirm(
      context,
      icon: Icons.send_outlined,
      title: _editing ? 'Kirim perbaikan usulan?' : 'Kirim usulan ke pengurus?',
      message: [
        '${draft.nama.trim()} (${proposalJenis[draft.jenis]})',
        '${tanggal(jakartaWallToUtc(draft.mulai!), jam: true, hari: true)} – ${_hm(draft.selesai!)} WIB',
        draft.lokasi.trim(),
        docs == 0 ? 'Tanpa dokumen pendukung' : '$docs dokumen pendukung',
        '',
        'Pengurus RT akan meninjau. Kegiatan masuk agenda hanya setelah disetujui.',
      ].join('\n'),
      yes: _editing ? 'Kirim perbaikan' : 'Kirim usulan',
    );
    if (!ok || !mounted) return;
    final body = proposalBody(draft, versi: _editing ? widget.existing!['versi'] as int : null);
    final api = ref.read(apiProvider);
    try {
      if (_editing && _attachments.isEmpty) {
        await api.patch('/activity-proposals/${widget.existing!['id']}', data: body);
      } else {
        final form = FormData()..fields.addAll([for (final e in body.entries) MapEntry(e.key, e.value is bool ? (e.value as bool ? '1' : '0') : '${e.value}')]);
        if (_editing) form.fields.add(const MapEntry('_method', 'PATCH'));
        for (final a in _attachments) {
          form.files.add(MapEntry('dokumen[]', await MultipartFile.fromFile(a.path, filename: a.name)));
        }
        await api.post(_editing ? '/activity-proposals/${widget.existing!['id']}' : '/activity-proposals', data: form);
      }
      if (!mounted) return;
      await showResultDialog(
        context,
        title: _editing ? 'Perbaikan terkirim' : 'Usulan terkirim',
        message: _editing ? 'Usulan kembali berstatus Diajukan dan pengurus RT diberi tahu untuk meninjau ulang.' : 'Pengurus RT akan meninjau usulan Anda. Anda menerima notifikasi setiap ada keputusan. Bila disetujui, kegiatan langsung masuk agenda dan semua warga diberi tahu.',
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = {for (final f in e.fields.entries) f.key.split('.').first: f.value.first});
      showApiError(context, e);
    } catch (_) {
      if (mounted) {
        showMessage(context, 'Berkas lampiran tidak dapat dibaca. Hapus lampiran itu lalu coba lagi.', kind: AlertKind.error, title: 'Usulan belum terkirim');
      }
    }
  }

  static String _hm(DateTime t) => '${t.hour.toString().padLeft(2, '0')}.${t.minute.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final akun = ref.watch(sessionProvider).akun;
    final room = proposalMaxDocuments - _existingDocs - _attachments.length;
    InputDecoration deco(String label, String key, {String? hint, String? helper}) =>
        InputDecoration(labelText: label, hintText: hint, helperText: helper, errorText: _errors[key], errorMaxLines: 4, helperMaxLines: 3);
    return Scaffold(
      appBar: AppBar(title: Text(_editing ? 'Perbaiki usulan' : 'Ajukan kegiatan')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SectionCard(
            child: Row(
              children: [
                const Icon(Icons.person_outline, color: Brand.navy),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Diajukan atas nama ${akun?.nama ?? '-'}${akun?.kodeRumah == null ? '' : ' (rumah ${akun!.kodeRumah})'}. Identitas hanya terlihat oleh pengurus RT.',
                    style: const TextStyle(height: 1.4),
                  ),
                ),
              ],
            ),
          ),
          if (_editing)
            const Padding(
              padding: EdgeInsets.only(top: 12),
              child: Text('Perbaikan mengembalikan status ke Diajukan. Dokumen yang sudah ada tetap tersimpan.', style: TextStyle(color: Brand.muted)),
            ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            // Tanpa isExpanded, label terpanjang meluber pada layar 360 dp.
            isExpanded: true,
            initialValue: _jenis,
            decoration: deco('Jenis kegiatan', 'jenis'),
            items: [for (final e in proposalJenis.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
            onChanged: (v) => setState(() => _jenis = v),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nama,
            maxLength: 160,
            decoration: deco('Nama kegiatan', 'nama', hint: 'Mis. Badminton bersama di lapangan RT 05'),
          ),
          const SizedBox(height: 4),
          _WhenField(
            label: 'Tanggal dan jam mulai',
            value: _mulai,
            error: _errors['mulai_at'],
            helper: 'Paling cepat 24 jam dari sekarang.',
            onTap: () => _pickWhen(start: true),
          ),
          const SizedBox(height: 12),
          _WhenField(
            label: 'Jam selesai',
            value: _selesai,
            error: _errors['selesai_at'],
            helper: 'Maksimal 12 jam setelah mulai.',
            onTap: () => _pickWhen(start: false),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _lokasi,
            maxLength: 200,
            decoration: deco('Lokasi', 'lokasi', hint: 'Mis. Lapangan RT 05'),
          ),
          const SizedBox(height: 4),
          TextField(
            controller: _deskripsi,
            maxLines: 6,
            maxLength: 5000,
            decoration: deco(
              'Deskripsi dan susunan acara',
              'deskripsi',
              hint: 'Tujuan, siapa yang boleh ikut, dan susunan acara',
            ).copyWith(alignLabelWithHint: true),
          ),
          const SizedBox(height: 4),
          TextField(
            controller: _peserta,
            keyboardType: TextInputType.number,
            decoration: deco('Perkiraan peserta (opsional)', 'perkiraan_peserta', hint: 'Mis. 20'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _kebutuhan,
            maxLines: 3,
            maxLength: 1000,
            decoration: deco('Kebutuhan dari RT (opsional)', 'kebutuhan', hint: 'Mis. izin memakai lapangan, pengumuman ke warga'),
          ),
          const SizedBox(height: 8),
          Text('Dokumen pendukung (opsional, maks. $proposalMaxDocuments)', style: Theme.of(context).textTheme.titleMedium),
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
              for (final (i, a) in _attachments.indexed)
                InputChip(
                  label: Text(a.name, overflow: TextOverflow.ellipsis),
                  avatar: Icon(_docIcon(a.kind)),
                  onDeleted: () {
                    setState(() => _attachments.removeAt(i));
                    showMessage(context, '${a.name} dihapus dari lampiran.', kind: AlertKind.info);
                  },
                ),
              if (room > 0) ...[
                ActionChip(avatar: const Icon(Icons.attach_file), label: const Text('Pilih file (PDF/TXT)'), onPressed: _pickFile),
                ActionChip(avatar: const Icon(Icons.photo_camera_outlined), label: const Text('Kamera'), onPressed: () => _pickPhoto(ImageSource.camera)),
                ActionChip(avatar: const Icon(Icons.photo_library_outlined), label: const Text('Galeri'), onPressed: () => _pickPhoto(ImageSource.gallery)),
              ],
            ],
          ),
          const SizedBox(height: 6),
          const Text(
            'PDF, TXT (maks. 1 MB), atau foto JPG/PNG/WebP; maks. 8 MB per berkas. Dokumen Word simpan sebagai PDF dulu. Hanya pengurus RT yang dapat membuka dokumen.',
            style: TextStyle(color: Brand.muted, fontSize: 13, height: 1.4),
          ),
          const SizedBox(height: 12),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            controlAffinity: ListTileControlAffinity.leading,
            value: _pernyataan,
            onChanged: (v) => setState(() {
              _pernyataan = v ?? false;
              _errors = {..._errors}..remove('pernyataan');
            }),
            title: const Text(proposalStatement, style: TextStyle(height: 1.4)),
            subtitle: _errors['pernyataan'] == null ? null : Text(_errors['pernyataan']!, style: const TextStyle(color: Brand.danger)),
          ),
          const SizedBox(height: 16),
          BusyButton(label: _editing ? 'Kirim perbaikan' : 'Kirim usulan', icon: Icons.send, onPressed: _submit),
        ],
      ),
    );
  }
}

IconData _docIcon(String kind) => switch (kind) {
  'pdf' => Icons.picture_as_pdf_outlined,
  'txt' => Icons.description_outlined,
  _ => Icons.image_outlined,
};

class _WhenField extends StatelessWidget {
  const _WhenField({required this.label, required this.value, required this.onTap, this.error, this.helper});
  final String label;
  final DateTime? value;
  final VoidCallback onTap;
  final String? error;
  final String? helper;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        helperText: helper,
        errorText: error,
        errorMaxLines: 3,
        prefixIcon: const Icon(Icons.schedule, color: Brand.navy),
      ),
      child: Text(
        value == null
            ? 'Pilih'
            : '${tanggal(jakartaWallToUtc(value!), hari: true)}, ${value!.hour.toString().padLeft(2, '0')}.${value!.minute.toString().padLeft(2, '0')} WIB',
      ),
    ),
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
          final mine = u['milik_saya'] == true;
          final docs = (u['dokumen'] as List?)?.cast<Map>() ?? const [];
          final clashes = (u['agenda_bentrok'] as List?)?.cast<Map>() ?? const [];
          final pengusul = u['pengusul'] as Map?;
          final open = admin && u['agenda_id'] == null && (status == 'diajukan' || status == 'ditinjau');
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
                      if (!admin && proposalStatusHint(status).isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(proposalStatusHint(status), style: const TextStyle(color: Brand.muted, height: 1.4)),
                      ],
                      const Divider(height: 24),
                      _Info(Icons.category_outlined, proposalJenis['${u['jenis']}'] ?? '${u['jenis']}'),
                      _Info(
                        Icons.schedule,
                        '${tanggal(parseTime(u['mulai_at']), jam: true, hari: true)}'
                        '${u['selesai_at'] == null ? '' : ' – ${tanggal(parseTime(u['selesai_at']), jam: true)}'} WIB',
                      ),
                      _Info(Icons.place_outlined, '${u['lokasi']}'),
                      if (u['perkiraan_peserta'] != null) _Info(Icons.groups_outlined, 'Perkiraan ${u['perkiraan_peserta']} peserta'),
                      if (pengusul != null && admin)
                        _Info(Icons.person_outline, 'Pengusul: ${pengusul['nama'] ?? '-'}${pengusul['rumah'] == null ? '' : ' (rumah ${pengusul['rumah']})'}'),
                      const Divider(height: 24),
                      Text('${u['deskripsi']}', style: const TextStyle(height: 1.5)),
                      if (u['kebutuhan'] != null) ...[
                        const SizedBox(height: 12),
                        Text('Kebutuhan dari RT', style: Theme.of(context).textTheme.titleSmall),
                        const SizedBox(height: 4),
                        Text('${u['kebutuhan']}', style: const TextStyle(height: 1.5)),
                      ],
                    ],
                  ),
                ),
                if (admin && clashes.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(color: Brand.warningBg, borderRadius: BorderRadius.circular(14)),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Row(
                          children: [
                            Icon(Icons.warning_amber_rounded, color: Brand.warning),
                            SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                'Jadwal bentrok dengan agenda lain',
                                style: TextStyle(color: Brand.warning, fontWeight: FontWeight.w800),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        for (final c in clashes)
                          Text('• ${c['nama']} — ${tanggal(parseTime(c['mulai_at']), jam: true)} WIB, ${c['lokasi']}', style: const TextStyle(height: 1.4)),
                        const SizedBox(height: 6),
                        const Text('Bukan pemblokir. Pertimbangkan meminta revisi waktu atau lokasi.', style: TextStyle(color: Brand.muted)),
                      ],
                    ),
                  ),
                ],
                if (docs.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  SectionCard(
                    title: 'Dokumen pendukung',
                    child: Column(
                      children: [
                        for (final doc in docs)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: Icon(_docIcon(_mimeKind('${doc['mime']}')), color: Brand.navy),
                            title: Text('Dokumen ${doc['urutan']} (${_mimeLabel('${doc['mime']}')})'),
                            subtitle: Text('${((doc['bytes'] as num) / 1024).ceil()} KB • privat'),
                            trailing: const Icon(Icons.open_in_new),
                            onTap: () => _openDocument(context, api, '${doc['id']}'),
                          ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 12),
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
                if (open) ...[
                  const SizedBox(height: 16),
                  BusyButton(label: 'Setujui dan masukkan ke agenda', icon: Icons.event_available, onPressed: () => _approve(context, ref, api, versi)),
                  const SizedBox(height: 8),
                  if (status == 'diajukan') ...[
                    BusyButton(
                      label: 'Tandai sedang ditinjau',
                      icon: Icons.timelapse,
                      outlined: true,
                      onPressed: () => _decide(context, ref, api, 'ditinjau', versi),
                    ),
                    const SizedBox(height: 8),
                  ],
                  BusyButton(label: 'Minta revisi', icon: Icons.edit_note, outlined: true, onPressed: () => _decide(context, ref, api, 'perlu_revisi', versi)),
                  const SizedBox(height: 8),
                  BusyButton(label: 'Tolak usulan', icon: Icons.block, outlined: true, onPressed: () => _decide(context, ref, api, 'ditolak', versi)),
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

  Future<void> _openDocument(BuildContext context, Api api, String documentId) async {
    try {
      final link = await api.post('/activity-proposals/$id/documents/$documentId/link') as Map;
      final ok = await launchUrl(Uri.parse(api.absolute('${link['url']}')), mode: LaunchMode.externalApplication);
      if (!context.mounted) return;
      ok
          ? showMessage(context, 'Dokumen dibuka di browser. Tautan berlaku 5 menit.', kind: AlertKind.info, title: 'Membuka dokumen')
          : showMessage(context, 'Tidak ada aplikasi untuk membuka dokumen.', kind: AlertKind.error);
    } on ApiException catch (e) {
      if (context.mounted) showApiError(context, e);
    }
  }

  Future<void> _approve(BuildContext context, WidgetRef ref, Api api, int versi) async {
    final ok = await confirm(
      context,
      icon: Icons.event_available,
      title: 'Setujui usulan ini?',
      message: 'Kegiatan langsung masuk agenda resmi RT dan SEMUA warga aktif menerima notifikasi. Keputusan ini tidak dapat dibatalkan dari usulan.',
      yes: 'Setujui',
    );
    if (!ok || !context.mounted) return;
    try {
      await api.post('/admin/activity-proposals/$id/status', data: {'status': 'disetujui', 'expected_version': versi});
      ref.invalidate(activityProposalDetailProvider(id));
      ref.invalidate(agendasProvider);
      if (context.mounted) {
        await showResultDialog(
          context,
          title: 'Usulan disetujui',
          message: 'Kegiatan sudah masuk agenda RT. Semua warga aktif dan pengusul menerima notifikasi.',
        );
      }
    } on ApiException catch (e) {
      ref.invalidate(activityProposalDetailProvider(id));
      if (context.mounted) showApiError(context, e);
    }
  }

  Future<void> _decide(BuildContext context, WidgetRef ref, Api api, String status, int versi) async {
    String? note;
    if (decisionNoteRequired(status)) {
      note = await askText(
        context,
        title: status == 'ditolak' ? 'Alasan penolakan' : 'Catatan revisi',
        label: 'Wajib diisi',
        hint: status == 'ditolak' ? 'Mis. bentrok dengan kerja bakti' : 'Apa yang perlu diperbaiki pengusul?',
      );
      if (!context.mounted) return;
      if (note == null) {
        showMessage(context, 'Catatan wajib diisi agar pengusul tahu alasannya.', kind: AlertKind.warning, title: 'Belum disimpan');
        return;
      }
      if (status == 'ditolak') {
        final ok = await confirm(
          context,
          icon: Icons.block,
          danger: true,
          title: 'Tolak usulan?',
          message: 'Pengusul menerima notifikasi beserta alasan ini. Usulan yang ditolak tidak dapat diajukan ulang.',
          yes: 'Tolak',
        );
        if (!ok || !context.mounted) return;
      }
    }
    try {
      await api.post('/admin/activity-proposals/$id/status', data: {'status': status, 'catatan': note, 'expected_version': versi});
      ref.invalidate(activityProposalDetailProvider(id));
      if (context.mounted) {
        showMessage(context, switch (status) {
          'ditinjau' => 'Usulan ditandai sedang ditinjau. Pengusul diberi tahu.',
          'perlu_revisi' => 'Permintaan revisi terkirim ke pengusul.',
          _ => 'Usulan ditolak. Pengusul menerima alasannya.',
        });
      }
    } on ApiException catch (e) {
      ref.invalidate(activityProposalDetailProvider(id));
      if (context.mounted) showApiError(context, e);
    }
  }
}

String _mimeKind(String mime) => mime == 'application/pdf' ? 'pdf' : (mime == 'text/plain' ? 'txt' : 'image');
String _mimeLabel(String mime) => mime == 'application/pdf' ? 'PDF' : (mime == 'text/plain' ? 'TXT' : 'Foto');

class _Info extends StatelessWidget {
  const _Info(this.icon, this.text);
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 20, color: Brand.navy),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(height: 1.4))),
      ],
    ),
  );
}
