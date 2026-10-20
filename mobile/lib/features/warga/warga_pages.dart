import 'dart:io' show File;

import 'package:dio/dio.dart' show FormData, MultipartFile;
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
import '../auth/account_access.dart';
import '../household/family_actions.dart' show conflictKeptMessage, staleConflict;
import '../household/family_page.dart';
import 'activity_proposals.dart';
import 'notification_center.dart';
import 'providers.dart';
import 'report_actions.dart';
import 'report_draft.dart';

// ─────────────────────────────────────────── Beranda warga
class WargaHomePage extends ConsumerWidget {
  const WargaHomePage({super.key, required this.goTab});
  final void Function(int) goTab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final akun = ref.watch(sessionProvider).akun!;
    final dues = ref.watch(duesMyProvider);
    final agendas = ref.watch(agendasProvider);
    final news = ref.watch(announcementsProvider);
    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(duesMyProvider);
        ref.invalidate(agendasProvider);
        ref.invalidate(announcementsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _Hero(name: akun.nama, kode: akun.kodeRumah, alamat: akun.alamat),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Iuran bulan ini',
            trailing: TextButton(onPressed: () => goTab(3), child: const Text('Detail')),
            child: AsyncView(
              value: dues,
              onRetry: () => ref.invalidate(duesMyProvider),
              builder: (d) {
                final now = d['bulan_berjalan'] as Map?;
                final arrears = d['tunggakan'] as Map;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (now == null)
                      const Text('Belum ada tagihan bulan ini.')
                    else
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              '${periode('${now['periode']}')} • ${rupiah(now['nominal'] as num)}',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                          ),
                          StatusChip('${now['status']}'),
                        ],
                      ),
                    if ((arrears['jumlah_bulan'] as num) > 0) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Tunggakan ${arrears['jumlah_bulan']} bulan: ${rupiah(arrears['total'] as num)}',
                        style: const TextStyle(color: Brand.danger, fontWeight: FontWeight.w600),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Text('${d['catatan']}', style: const TextStyle(color: Brand.muted, fontSize: 13)),
                  ],
                );
              },
            ),
          ),
          const SizedBox(height: 16),
          ...switch (ref.watch(pendingReportDraftProvider(ref.watch(sessionProvider).akun?.id)).value) {
            null => const <Widget>[],
            _ => [
              Card(
                child: ListTile(
                  leading: const Icon(Icons.restore_page_outlined, color: Brand.navy),
                  title: const Text('Pengaduan belum terkirim'),
                  subtitle: const Text('Aplikasi sempat tertutup saat kamera terbuka. Isianmu masih tersimpan di HP ini.'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () async {
                    await Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportFormPage(aspirasi: false)));
                    ref.invalidate(pendingReportDraftProvider);
                  },
                ),
              ),
              const SizedBox(height: 16),
            ],
          },
          Row(
            children: [
              _QuickAction(
                icon: Icons.report_outlined,
                label: 'Pengaduan',
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportFormPage(aspirasi: false))),
              ),
              const SizedBox(width: 10),
              _QuickAction(
                icon: Icons.lightbulb_outline,
                label: 'Aspirasi',
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportFormPage(aspirasi: true))),
              ),
              const SizedBox(width: 10),
              _QuickAction(icon: Icons.account_balance_wallet_outlined, label: 'Kas RT', onTap: () => goTab(3)),
            ],
          ),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Agenda terdekat',
            trailing: TextButton(onPressed: () => goTab(2), child: const Text('Semua')),
            child: AsyncView(
              value: agendas,
              onRetry: () => ref.invalidate(agendasProvider),
              builder: (list) => list.isEmpty ? const Text('Belum ada agenda.') : Column(children: [for (final a in list.take(2)) _AgendaTile(a)]),
            ),
          ),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Pengumuman',
            child: AsyncView(
              value: news,
              onRetry: () => ref.invalidate(announcementsProvider),
              builder: (list) => list.isEmpty
                  ? const Text('Belum ada pengumuman.')
                  : Column(
                      children: [
                        for (final n in list.take(3))
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.campaign_outlined, color: Brand.navy),
                            title: Text('${n['judul']}', maxLines: 2, overflow: TextOverflow.ellipsis),
                            subtitle: Text(tanggal(parseTime(n['published_at']))),
                            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AnnouncementPage(n))),
                          ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.name, this.kode, this.alamat});
  final String name;
  final String? kode;
  final String? alamat;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      gradient: const LinearGradient(colors: [Brand.navy, Brand.navyDeep], begin: Alignment.topLeft, end: Alignment.bottomRight),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Selamat datang,', style: TextStyle(color: Colors.white70)),
              Text(name, style: Theme.of(context).textTheme.titleLarge?.copyWith(color: Colors.white)),
              const SizedBox(height: 8),
              Container(height: 3, width: 48, color: Brand.yellow),
              const SizedBox(height: 8),
              Text(kode == null ? 'Belum terhubung ke rumah' : 'Rumah $kode • ${alamat ?? ''}', style: const TextStyle(color: Colors.white)),
            ],
          ),
        ),
        const GapuraMark(size: 56, light: true),
      ],
    ),
  );
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Brand.line),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16),
          child: Column(
            children: [
              CircleAvatar(
                backgroundColor: Brand.yellow.withValues(alpha: .35),
                child: Icon(icon, color: Brand.navy),
              ),
              const SizedBox(height: 8),
              Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    ),
  );
}

// ─────────────────────────────────────────── Layanan (pengaduan & aspirasi terpisah)
class ServicesPage extends ConsumerWidget {
  const ServicesPage({super.key, this.admin = false});
  final bool admin;

  @override
  Widget build(BuildContext context, WidgetRef ref) => DefaultTabController(
    length: admin ? 2 : 4,
    child: Column(
      children: [
        Material(
          color: Colors.white,
          child: TabBar(
            isScrollable: !admin,
            labelColor: Brand.navy,
            indicatorColor: Brand.yellow,
            indicatorWeight: 3,
            tabs: [
              const Tab(text: 'Pengaduan'),
              const Tab(text: 'Aspirasi'),
              if (!admin) const Tab(text: 'Pengaduan saya'),
              if (!admin) const Tab(text: 'Aspirasi saya'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            children: [
              ReportList(aspirasi: false, admin: admin),
              ReportList(aspirasi: true, admin: admin),
              if (!admin) const ReportList(aspirasi: false, mine: true),
              if (!admin) const ReportList(aspirasi: true, mine: true),
            ],
          ),
        ),
      ],
    ),
  );
}

class ReportList extends ConsumerStatefulWidget {
  const ReportList({super.key, required this.aspirasi, this.mine = false, this.admin = false});
  final bool aspirasi;
  final bool mine;
  final bool admin;
  @override
  ConsumerState<ReportList> createState() => _ReportListState();
}

class _ReportListState extends ConsumerState<ReportList> {
  String? _status;
  bool _arsip = false;

  @override
  Widget build(BuildContext context) {
    final key = (widget.aspirasi, widget.mine, _status, widget.admin && _arsip);
    final reports = ref.watch(reportsProvider(key));
    final flow = widget.aspirasi ? ['diajukan', 'ditinjau', 'selesai'] : ['diajukan', 'diproses', 'selesai'];
    return Scaffold(
      backgroundColor: Brand.sky,
      floatingActionButton: widget.admin
          ? null
          : FloatingActionButton.extended(
              backgroundColor: Brand.yellow,
              foregroundColor: Brand.navy,
              onPressed: () async {
                await Navigator.push(context, MaterialPageRoute(builder: (_) => ReportFormPage(aspirasi: widget.aspirasi)));
                ref.invalidate(reportsProvider);
              },
              icon: const Icon(Icons.add),
              label: Text(widget.aspirasi ? 'Kirim aspirasi' : 'Buat pengaduan'),
            ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(reportsProvider(key)),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                ChoiceChip(label: const Text('Semua'), selected: _status == null, onSelected: (_) => setState(() => _status = null)),
                for (final s in flow) ChoiceChip(label: Text(statusLabel(s)), selected: _status == s, onSelected: (_) => setState(() => _status = s)),
                if (widget.admin) FilterChip(label: const Text('Termasuk arsip'), selected: _arsip, onSelected: (v) => setState(() => _arsip = v)),
              ],
            ),
            const SizedBox(height: 12),
            AsyncView(
              value: reports,
              onRetry: () => ref.invalidate(reportsProvider(key)),
              builder: (list) => list.isEmpty
                  ? EmptyState(
                      icon: widget.aspirasi ? Icons.lightbulb_outline : Icons.inbox_outlined,
                      title: widget.aspirasi ? 'Belum ada aspirasi' : 'Belum ada pengaduan',
                    )
                  : Column(
                      children: [
                        for (final r in list)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: Card(
                              child: ListTile(
                                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                                title: Text(r.judul, style: const TextStyle(fontWeight: FontWeight.w700)),
                                subtitle: Padding(
                                  padding: const EdgeInsets.only(top: 6),
                                  child: Text(
                                    [
                                      if (r.diarsipkan != null) 'Diarsipkan',
                                      if (r.kategori != null) r.kategori!,
                                      r.namaPelapor ?? 'Identitas disembunyikan',
                                      tanggal(r.dibuat),
                                    ].join(' • '),
                                    style: const TextStyle(color: Brand.muted),
                                  ),
                                ),
                                trailing: StatusChip(r.status),
                                onTap: () async {
                                  await Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => ReportDetailPage(aspirasi: widget.aspirasi, id: r.id, admin: widget.admin),
                                    ),
                                  );
                                  ref.invalidate(reportsProvider);
                                },
                              ),
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

/// Buat laporan baru, atau perbaiki laporan milik sendiri ([existing]) selama masih diajukan.
/// Kegagalan apa pun tidak menutup formulir: teks yang diketik tetap ada.
class ReportFormPage extends ConsumerStatefulWidget {
  const ReportFormPage({super.key, required this.aspirasi, this.existing, this.picker});
  final bool aspirasi;
  final Laporan? existing;

  /// Diganti pada tes; default ImagePicker plugin (kamera/galeri Android).
  final ImagePicker? picker;
  @override
  ConsumerState<ReportFormPage> createState() => _ReportFormPageState();
}

class _ReportFormPageState extends ConsumerState<ReportFormPage> {
  late final _judul = TextEditingController(text: widget.existing?.judul);
  late final _isi = TextEditingController(text: widget.existing?.isi);
  String? _kategori;
  late bool _anon = widget.existing?.anonim ?? false;
  late int? _versi = widget.existing?.versi;
  final List<XFile> _photos = [];

  /// UAT M15: foto pengaduan yang sudah tersimpan (mode edit); ditambah/dihapus langsung ke server.
  late List<Json> _saved = [...?widget.existing?.foto];
  bool _photoBusy = false;
  String? _formError;
  bool _locked = false;

  bool get _editing => widget.existing != null;

  /// Pengaduan baru saja yang punya foto/kamera; draf M15 hanya untuk alur ini.
  bool get _drafted => !_editing && !widget.aspirasi;

  late final ImagePicker _picker = widget.picker ?? ImagePicker();

  @override
  void initState() {
    super.initState();
    if (_drafted) _restoreDraft();
  }

  @override
  void dispose() {
    _judul.dispose();
    _isi.dispose();
    super.dispose();
  }

  /// Android sempat menutup aplikasi saat kamera terbuka: kembalikan isian dan foto.
  Future<void> _restoreDraft() async {
    final store = ref.read(reportDraftStoreProvider);
    final draft = await pendingDraftFor(store, ref.read(sessionProvider).akun?.id);
    if (draft == null) return;
    final lost = draft.kamera ? await ref.read(lostPhotosProvider)(_picker) : const <XFile>[];
    await store.clear();
    if (!mounted) return;
    final kept = [
      for (final p in draft.photos)
        if (File(p).existsSync()) XFile(p),
    ];
    setState(() {
      _kategori = draft.kategori;
      _judul.text = draft.judul;
      _isi.text = draft.isi;
      _anon = draft.anonim;
      _photos
        ..clear()
        ..addAll([...kept, ...lost].take(5));
    });
    showMessage(context, 'Isian pengaduan yang belum terkirim dipulihkan. Periksa lalu kirim.');
  }

  ReportDraft? _currentDraft({bool kamera = false}) {
    final akun = ref.read(sessionProvider).akun?.id;
    if (akun == null) return null;
    return ReportDraft(
      akunId: akun,
      kategori: _kategori,
      judul: _judul.text,
      isi: _isi.text,
      anonim: _anon,
      photos: [for (final p in _photos) p.path],
      kamera: kamera,
    );
  }

  /// Edit pengaduan: unggah satu foto baru lalu perbarui versi dan daftar foto dari respons server.
  Future<void> _addSaved(ImageSource source) async {
    final XFile? file;
    try {
      file = await _picker.pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
    } on PlatformException catch (e) {
      if (mounted) {
        showMessage(context, photoPickMessage(e.code, camera: source == ImageSource.camera) ?? 'Foto tidak dapat diambil.', error: true);
      }
      return;
    }
    if (file == null || !mounted) return;
    setState(() => _photoBusy = true);
    try {
      final form = FormData()
        ..fields.add(MapEntry('versi', '$_versi'))
        ..files.add(MapEntry('foto[]', MultipartFile.fromBytes(await file.readAsBytes(), filename: 'foto.jpg')));
      final res = Laporan(Json.from(await ref.read(apiProvider).post('/complaints/${widget.existing!.id}/photos', data: form) as Map), aspirasi: false);
      if (!mounted) return;
      setState(() {
        _versi = res.versi;
        _saved = res.foto;
      });
      showMessage(context, 'Foto ditambahkan. Foto sedang diproses server.');
    } on ApiException catch (e) {
      if (!mounted) return;
      if (staleConflict(e)) await _reloadVersi();
      if (mounted) showMessage(context, e.display, error: true);
    } catch (_) {
      if (mounted) showMessage(context, 'Foto tidak dapat dibaca atau diunggah. Coba foto lain.', error: true);
    } finally {
      if (mounted) setState(() => _photoBusy = false);
    }
  }

  Future<void> _removeSaved(Json foto, int index) async {
    if (!await confirm(
      context,
      title: 'Hapus foto ${index + 1}?',
      message: 'Foto dihapus dari laporan ini dan tidak dapat dikembalikan.',
      yes: 'Hapus',
      danger: true,
    )) {
      return;
    }
    setState(() => _photoBusy = true);
    try {
      final res = Laporan(
        Json.from(await ref.read(apiProvider).delete('/complaints/${widget.existing!.id}/photos/${foto['id']}?versi=$_versi') as Map),
        aspirasi: false,
      );
      if (!mounted) return;
      setState(() {
        _versi = res.versi;
        _saved = res.foto;
      });
      showMessage(context, 'Foto dihapus.');
    } on ApiException catch (e) {
      if (!mounted) return;
      if (staleConflict(e)) await _reloadVersi();
      if (mounted) showMessage(context, e.display, error: true);
    } finally {
      if (mounted) setState(() => _photoBusy = false);
    }
  }

  Future<void> _pick(ImageSource source) async {
    final store = ref.read(reportDraftStoreProvider);
    final camera = _drafted && source == ImageSource.camera;
    final draft = camera ? _currentDraft(kamera: true) : null;
    if (draft != null) await store.write(draft);
    try {
      final file = await _picker.pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
      if (file != null && mounted) setState(() => _photos.add(file));
    } on PlatformException catch (e) {
      if (mounted) {
        showMessage(
          context,
          photoPickMessage(e.code, camera: source == ImageSource.camera) ?? 'Foto tidak dapat diambil. Anda tetap dapat mengirim tanpa foto.',
          error: true,
        );
      }
    } finally {
      // Aplikasi tetap hidup: isian ada di memori, draf pengaman tidak diperlukan lagi.
      if (draft != null) await store.clear();
    }
  }

  Future<void> _submit() async {
    if (_judul.text.trim().isEmpty || _isi.text.trim().isEmpty || (!_editing && !widget.aspirasi && _kategori == null)) {
      setState(() => _formError = widget.aspirasi || _editing ? 'Lengkapi judul dan isi.' : 'Lengkapi judul, isi, dan kategori.');
      return;
    }
    setState(() => _formError = null);
    final api = ref.read(apiProvider);
    try {
      if (_editing) {
        final base = widget.aspirasi ? '/aspirations' : '/complaints';
        await api.patch(
          '$base/${widget.existing!.id}',
          data: reportUpdateBody(aspirasi: widget.aspirasi, judul: _judul.text, isi: _isi.text, anonim: _anon, versi: _versi!),
        );
      } else if (widget.aspirasi) {
        await api.post('/aspirations', data: {'judul': _judul.text.trim(), 'isi': _isi.text.trim(), 'sembunyikan_identitas': _anon});
      } else {
        final form = FormData()
          ..fields.addAll([
            MapEntry('kategori_id', _kategori!),
            MapEntry('judul', _judul.text.trim()),
            MapEntry('deskripsi', _isi.text.trim()),
            MapEntry('sembunyikan_identitas', _anon ? '1' : '0'),
          ]);
        for (final p in _photos) {
          form.files.add(MapEntry('foto[]', await MultipartFile.fromFile(p.path, filename: 'foto.jpg')));
        }
        await api.post('/complaints', data: form);
        await ref.read(reportDraftStoreProvider).clear();
      }
      if (!mounted) return;
      showMessage(
        context,
        _editing
            ? 'Perubahan tersimpan.'
            : widget.aspirasi
            ? 'Aspirasi terkirim.'
            : 'Pengaduan terkirim. Foto diproses server; pantau statusnya di menu Layanan.',
      );
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (_editing && staleConflict(e)) {
        setState(() => _formError = conflictKeptMessage(e.message));
        await _reloadVersi();
        return;
      }
      if (_editing && reportLocked(e.code)) {
        setState(() {
          _locked = true;
          _formError = '${e.message} $reportLockedTail';
        });
        return;
      }
      setState(() => _formError = e.display);
    }
  }

  /// Versi terbaru dari server; teks di formulir tidak ditimpa.
  Future<void> _reloadVersi() async {
    try {
      final data = await ref.read(apiProvider).get('${widget.aspirasi ? '/aspirations' : '/complaints'}/${widget.existing!.id}');
      final latest = Laporan(Json.from(data as Map), aspirasi: widget.aspirasi);
      if (!mounted) return;
      setState(() {
        _versi = latest.versi;
        if (latest.status != 'diajukan') {
          _locked = true;
          _formError =
              '${widget.aspirasi ? 'Aspirasi' : 'Laporan'} sudah ${statusLabel(latest.status).toLowerCase()} oleh pengurus sehingga tidak dapat diubah lagi. $reportLockedTail';
        }
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _formError = '${_formError ?? ''} ${e.display}'.trim());
    }
  }

  @override
  Widget build(BuildContext context) {
    final cats = ref.watch(categoriesProvider);
    final title = _editing ? (widget.aspirasi ? 'Perbaiki aspirasi' : 'Perbaiki laporan') : (widget.aspirasi ? 'Kirim aspirasi' : 'Buat pengaduan');
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (!widget.aspirasi && !_editing)
            AsyncView(
              value: cats,
              onRetry: () => ref.invalidate(categoriesProvider),
              builder: (list) => DropdownButtonFormField<String>(
                initialValue: _kategori,
                decoration: const InputDecoration(labelText: 'Kategori'),
                items: [for (final c in list) DropdownMenuItem(value: '${c['id']}', child: Text('${c['nama']}'))],
                onChanged: (v) => setState(() => _kategori = v),
              ),
            ),
          if (_editing)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                widget.aspirasi
                    ? 'Judul, isi, dan pilihan identitas dapat diperbaiki selama aspirasi masih Diajukan.'
                    : 'Judul, isi, pilihan identitas, dan foto dapat diperbaiki selama laporan masih Diajukan. Kategori tetap.',
                style: const TextStyle(color: Brand.muted),
              ),
            ),
          const SizedBox(height: 14),
          TextField(
            controller: _judul,
            maxLength: 150,
            decoration: const InputDecoration(labelText: 'Judul'),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: _isi,
            maxLines: 6,
            maxLength: 5000,
            decoration: InputDecoration(labelText: widget.aspirasi ? 'Isi kritik, saran, atau usulan' : 'Deskripsi masalah', alignLabelWithHint: true),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _anon,
            onChanged: (v) => setState(() => _anon = v),
            title: const Text('Sembunyikan identitas dari warga lain'),
            subtitle: Text(
              widget.aspirasi
                  ? 'Pengurus RT tetap dapat melihat pengirim untuk tindak lanjut. Isi aspirasi tetap dapat mengungkap identitas.'
                  : 'Pengurus RT tetap dapat melihat pelapor untuk tindak lanjut. Foto/isi tetap dapat mengungkap lokasi.',
            ),
          ),
          if (!widget.aspirasi && !_editing) ...[
            const SizedBox(height: 8),
            Text('Foto (opsional, maks. 5)', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final (i, _) in _photos.indexed)
                  InputChip(label: Text('Foto ${i + 1}'), avatar: const Icon(Icons.image_outlined), onDeleted: () => setState(() => _photos.removeAt(i))),
                if (_photos.length < 5) ...[
                  ActionChip(avatar: const Icon(Icons.photo_camera_outlined), label: const Text('Kamera'), onPressed: () => _pick(ImageSource.camera)),
                  ActionChip(avatar: const Icon(Icons.photo_library_outlined), label: const Text('Galeri'), onPressed: () => _pick(ImageSource.gallery)),
                ],
              ],
            ),
            const SizedBox(height: 6),
            const Text(photoLimitNote, style: TextStyle(color: Brand.muted, fontSize: 13)),
          ],
          if (!widget.aspirasi && _editing) ...[
            const SizedBox(height: 8),
            Text('Foto (${_saved.length} dari 5)', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final (i, f) in _saved.indexed)
                  InputChip(
                    key: ValueKey('foto-${f['id']}'),
                    label: Text('Foto ${i + 1}${f['status'] == 'siap' ? '' : ' (${photoStatusLabel('${f['status']}').toLowerCase()})'}'),
                    avatar: const Icon(Icons.image_outlined),
                    deleteButtonTooltipMessage: 'Hapus foto ${i + 1}',
                    onDeleted: _photoBusy || _locked ? null : () => _removeSaved(f, i),
                  ),
                if (_saved.length < 5 && !_locked) ...[
                  ActionChip(
                    avatar: const Icon(Icons.photo_camera_outlined),
                    label: const Text('Kamera'),
                    onPressed: _photoBusy ? null : () => _addSaved(ImageSource.camera),
                  ),
                  ActionChip(
                    avatar: const Icon(Icons.photo_library_outlined),
                    label: const Text('Galeri'),
                    onPressed: _photoBusy ? null : () => _addSaved(ImageSource.gallery),
                  ),
                ],
              ],
            ),
            if (_photoBusy) const Padding(padding: EdgeInsets.only(top: 8), child: LinearProgressIndicator()),
            const SizedBox(height: 6),
            const Text('Tambah/hapus foto langsung tersimpan. $photoLimitNote', style: TextStyle(color: Brand.muted, fontSize: 13)),
          ],
          if (_formError != null) ...[const SizedBox(height: 12), Text(_formError!, style: const TextStyle(color: Brand.danger, height: 1.35))],
          const SizedBox(height: 24),
          BusyButton(label: _editing ? 'Simpan perubahan' : 'Kirim', icon: _editing ? Icons.save_outlined : Icons.send, onPressed: _locked ? null : _submit),
        ],
      ),
    );
  }
}

class ReportDetailPage extends ConsumerWidget {
  const ReportDetailPage({super.key, required this.aspirasi, required this.id, this.admin = false});
  final bool aspirasi;
  final String id;
  final bool admin;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(reportDetailProvider((aspirasi, id)));
    final api = ref.read(apiProvider);
    return Scaffold(
      appBar: AppBar(title: Text(aspirasi ? 'Detail aspirasi' : 'Detail pengaduan')),
      body: AsyncView(
        value: detail,
        onRetry: () => ref.invalidate(reportDetailProvider((aspirasi, id))),
        builder: (d) {
          final (r, history) = d;
          final archived = r.diarsipkan != null;
          final noun = aspirasi ? 'aspirasi' : 'laporan';
          final next = archived
              ? null
              : aspirasi
              ? {'diajukan': 'ditinjau', 'ditinjau': 'selesai', 'selesai': 'ditinjau'}[r.status]
              : {'diajukan': 'diproses', 'diproses': 'selesai', 'selesai': 'diproses'}[r.status];
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(reportDetailProvider((aspirasi, id))),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (archived)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      '${aspirasi ? 'Aspirasi' : 'Laporan'} diarsipkan: tidak tampil untuk warga. Riwayat tetap tersimpan.',
                      style: const TextStyle(color: Brand.warning),
                    ),
                  ),
                SectionCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Pada teks besar, chip di samping judul menyempitkannya sampai kata terpecah; pindahkan ke bawah.
                      if (MediaQuery.textScalerOf(context).scale(16) / 16 > 1.3)
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(r.judul, style: Theme.of(context).textTheme.titleLarge),
                            const SizedBox(height: 8),
                            StatusChip(r.status),
                          ],
                        )
                      else
                        Row(
                          children: [
                            Expanded(child: Text(r.judul, style: Theme.of(context).textTheme.titleLarge)),
                            StatusChip(r.status),
                          ],
                        ),
                      const SizedBox(height: 8),
                      Text(
                        [if (r.kategori != null) r.kategori!, r.namaPelapor ?? 'Identitas disembunyikan', tanggal(r.dibuat, jam: true)].join(' • '),
                        style: const TextStyle(color: Brand.muted),
                      ),
                      if (r.anonim && (r.milikSaya || admin))
                        const Padding(
                          padding: EdgeInsets.only(top: 6),
                          child: Text('Identitas disembunyikan dari warga lain.', style: TextStyle(color: Brand.warning, fontSize: 13)),
                        ),
                      const Divider(height: 24),
                      Text(r.isi, style: const TextStyle(height: 1.5)),
                      if (r.foto.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        SizedBox(
                          height: 140,
                          child: ListView(
                            scrollDirection: Axis.horizontal,
                            children: [
                              for (final f in r.foto)
                                Padding(
                                  padding: const EdgeInsets.only(right: 8),
                                  child: ClipRRect(
                                    borderRadius: BorderRadius.circular(12),
                                    child: f['status'] == 'siap' && f['url'] != null
                                        ? Image.network(
                                            api.absolute('${f['url']}'),
                                            headers: api.authHeaders,
                                            height: 140,
                                            fit: BoxFit.cover,
                                            semanticLabel: 'Foto laporan',
                                            errorBuilder: (_, _, _) => const SizedBox(width: 140, child: Icon(Icons.broken_image_outlined)),
                                          )
                                        : _PhotoPlaceholder(status: '${f['status']}'),
                                  ),
                                ),
                            ],
                          ),
                        ),
                        if (r.foto.any((f) => f['status'] == 'diproses'))
                          const Padding(
                            padding: EdgeInsets.only(top: 6),
                            child: Text('Foto sedang diproses server. Tarik ke bawah untuk memuat ulang.', style: TextStyle(color: Brand.muted, fontSize: 13)),
                          ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                SectionCard(
                  title: 'Riwayat status',
                  child: Column(
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
                if (admin && next != null) ...[
                  const SizedBox(height: 16),
                  BusyButton(
                    label: r.status == 'selesai' ? 'Buka ulang (${statusLabel(next)})' : 'Ubah ke ${statusLabel(next)}',
                    icon: Icons.sync_alt,
                    onPressed: () async {
                      final note = await askText(
                        context,
                        title: 'Catatan tindak lanjut',
                        label: r.status == 'selesai' ? 'Alasan buka ulang (wajib)' : 'Catatan (opsional)',
                      );
                      if (r.status == 'selesai' && note == null) return;
                      try {
                        await api.post(
                          '/admin/${aspirasi ? 'aspirations' : 'complaints'}/$id/status',
                          data: {'status': next, 'catatan': note, 'expected_version': r.versi},
                        );
                        ref.invalidate(reportDetailProvider((aspirasi, id)));
                        if (context.mounted) showMessage(context, 'Status diperbarui; ${aspirasi ? 'pengirim' : 'pelapor'} menerima notifikasi.');
                      } on ApiException catch (e) {
                        if (e.isConflict) ref.invalidate(reportDetailProvider((aspirasi, id)));
                        if (context.mounted) showMessage(context, e.display, error: true);
                      }
                    },
                  ),
                ],
                if (admin && !archived) ...[
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    icon: const Icon(Icons.archive_outlined),
                    label: Text('Arsipkan $noun'),
                    style: OutlinedButton.styleFrom(foregroundColor: Brand.danger, minimumSize: const Size.fromHeight(48)),
                    onPressed: () async {
                      final reason = await askText(context, title: 'Arsipkan $noun', label: 'Alasan arsip (wajib)', hint: 'Mis. duplikat $noun lain');
                      if (reason == null) return;
                      try {
                        await api.post('/admin/${aspirasi ? 'aspirations' : 'complaints'}/$id/archive', data: {'alasan': reason});
                        ref.invalidate(reportDetailProvider((aspirasi, id)));
                        if (context.mounted) showMessage(context, '${aspirasi ? 'Aspirasi' : 'Laporan'} diarsipkan. Riwayat tetap tersimpan.');
                      } on ApiException catch (e) {
                        ref.invalidate(reportDetailProvider((aspirasi, id)));
                        if (context.mounted) showMessage(context, e.display, error: true);
                      }
                    },
                  ),
                ],
                if (!admin && r.milikSaya && r.status == 'diajukan') ...[
                  const SizedBox(height: 16),
                  OutlinedButton.icon(
                    icon: const Icon(Icons.edit_outlined),
                    label: Text('Perbaiki $noun'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                    onPressed: () async {
                      await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ReportFormPage(aspirasi: aspirasi, existing: r),
                        ),
                      );
                      // Foto bisa berubah tanpa menekan Simpan (tambah/hapus langsung tersimpan).
                      ref.invalidate(reportDetailProvider((aspirasi, id)));
                    },
                  ),
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Text(
                      '${aspirasi ? 'Aspirasi' : 'Laporan'} masih dapat diperbaiki selama berstatus Diajukan.',
                      style: const TextStyle(color: Brand.muted),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Batas unggah yang sama dengan server (UAT M15): jumlah, ukuran, dan kuota harian per akun.
const photoLimitNote =
    'JPG/PNG/WebP, maks. 5 foto per laporan dan 25 foto per hari; foto diperkecil otomatis (maks. 5 MB). Metadata lokasi (GPS) dihapus server.';

class _PhotoPlaceholder extends StatelessWidget {
  const _PhotoPlaceholder({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) => Container(
    width: 140,
    height: 140,
    color: Brand.sky,
    padding: const EdgeInsets.all(8),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(status == 'gagal' ? Icons.error_outline : Icons.hourglass_top, color: status == 'gagal' ? Brand.danger : Brand.navy),
        const SizedBox(height: 6),
        Text(photoStatusLabel(status), textAlign: TextAlign.center, style: const TextStyle(fontSize: 13)),
      ],
    ),
  );
}

// ─────────────────────────────────────────── Agenda & pengumuman
class AgendaPage extends ConsumerWidget {
  const AgendaPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final agendas = ref.watch(agendasProvider);
    final news = ref.watch(announcementsProvider);
    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(agendasProvider);
        ref.invalidate(announcementsProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Agenda RT', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          const Text(
            'Agenda ini jadwal resmi RT. Ingin mengadakan kegiatan? Ajukan ke pengurus; bila disetujui, kegiatan masuk ke sini dan semua warga diberi tahu.',
            style: TextStyle(color: Brand.muted, height: 1.4),
          ),
          const SizedBox(height: 12),
          BusyButton(
            label: 'Ajukan kegiatan',
            icon: Icons.add_circle_outline,
            onPressed: () async {
              final saved = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const ActivityProposalFormPage()));
              if (saved == true) ref.invalidate(activityProposalsProvider(null));
            },
          ),
          const SizedBox(height: 8),
          BusyButton(
            label: 'Usulan kegiatan saya',
            icon: Icons.event_note_outlined,
            outlined: true,
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ActivityProposalListPage(admin: false))),
          ),
          const SizedBox(height: 16),
          AsyncView(
            value: agendas,
            onRetry: () => ref.invalidate(agendasProvider),
            builder: (list) => list.isEmpty
                ? const EmptyState(icon: Icons.event_busy_outlined, title: 'Belum ada agenda mendatang')
                : Column(
                    children: [
                      for (final a in list)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Card(
                            child: Padding(padding: const EdgeInsets.all(8), child: _AgendaTile(a)),
                          ),
                        ),
                    ],
                  ),
          ),
          const SizedBox(height: 20),
          Text('Pengumuman', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 10),
          AsyncView(
            value: news,
            onRetry: () => ref.invalidate(announcementsProvider),
            builder: (list) => list.isEmpty
                ? const EmptyState(icon: Icons.campaign_outlined, title: 'Belum ada pengumuman')
                : Column(
                    children: [
                      for (final n in list)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Card(
                            child: ListTile(
                              title: Text('${n['judul']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              subtitle: Text('${tanggal(parseTime(n['published_at']))}\n${n['isi']}', maxLines: 3, overflow: TextOverflow.ellipsis),
                              isThreeLine: true,
                              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AnnouncementPage(n))),
                            ),
                          ),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _AgendaTile extends StatelessWidget {
  const _AgendaTile(this.a);
  final Json a;

  @override
  Widget build(BuildContext context) {
    final start = parseTime(a['mulai_at']);
    final local = start == null ? null : toJakarta(start);
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 8),
      leading: Container(
        width: 52,
        padding: const EdgeInsets.symmetric(vertical: 6),
        decoration: BoxDecoration(color: Brand.yellow.withValues(alpha: .4), borderRadius: BorderRadius.circular(12)),
        // ListTile membatasi tinggi leading 56: tanggal diperkecil bila skala teks besar, bukan overflow.
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Column(
            children: [
              Text(
                '${local?.day ?? '-'}',
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: Brand.navy),
              ),
              Text(
                local == null ? '' : const ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][local.month - 1],
                style: const TextStyle(fontSize: 12, color: Brand.navy),
              ),
            ],
          ),
        ),
      ),
      title: Text('${a['nama']}', style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text('${tanggal(start, jam: true, hari: true)}\n${a['lokasi']}'),
      isThreeLine: true,
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AgendaDetailPage(a))),
    );
  }
}

class AgendaDetailPage extends StatelessWidget {
  const AgendaDetailPage(this.a, {super.key});
  final Json a;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Agenda')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SectionCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${a['nama']}', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              _InfoRow(Icons.schedule, tanggal(parseTime(a['mulai_at']), jam: true, hari: true)),
              if (a['selesai_at'] != null) _InfoRow(Icons.timer_off_outlined, 'Selesai ${tanggal(parseTime(a['selesai_at']), jam: true)}'),
              _InfoRow(Icons.place_outlined, '${a['lokasi']}'),
              const Divider(height: 24),
              Text('${a['deskripsi']}', style: const TextStyle(height: 1.5)),
            ],
          ),
        ),
        const SizedBox(height: 16),
        BusyButton(
          label: 'Tambahkan ke Google Calendar',
          icon: Icons.event_available,
          onPressed: () async {
            final ok = await launchUrl(Uri.parse('${a['google_calendar_url']}'), mode: LaunchMode.externalApplication);
            if (!ok && context.mounted) showMessage(context, 'Tidak dapat membuka aplikasi kalender.', error: true);
          },
        ),
      ],
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(this.icon, this.text);
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 20, color: Brand.navy),
        const SizedBox(width: 10),
        Expanded(child: Text(text)),
      ],
    ),
  );
}

class AnnouncementPage extends StatelessWidget {
  const AnnouncementPage(this.n, {super.key});
  final Json n;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Pengumuman')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SectionCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${n['judul']}', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 6),
              Text(tanggal(parseTime(n['published_at']), jam: true), style: const TextStyle(color: Brand.muted)),
              const Divider(height: 24),
              Text('${n['isi']}', style: const TextStyle(height: 1.5)),
            ],
          ),
        ),
      ],
    ),
  );
}

// ─────────────────────────────────────────── Iuran & kas
/// Tab Iuran warga (keputusan pemilik 17 Oktober 2026, ADR26): iuran rumah sendiri sebagai prioritas;
/// transparansi rumah lain disembunyikan secara bawaan dan baru dimuat setelah warga memilih melihatnya;
/// kas RT tidak tampil untuk warga (server menolak dengan 403).
class DuesPage extends ConsumerStatefulWidget {
  const DuesPage({super.key});
  @override
  ConsumerState<DuesPage> createState() => _DuesPageState();
}

class _DuesPageState extends ConsumerState<DuesPage> {
  bool _showTransparency = false;

  Future<void> _reveal() async {
    final ok = await confirm(
      context,
      title: 'Lihat status iuran rumah lain?',
      message:
          'Yang tampil hanya kode rumah, nominal, dan status bulan ini, tanpa nama atau kontak. '
          'Mohon tidak disebarkan atau dipakai untuk menagih tetangga.',
      yes: 'Tampilkan',
      icon: Icons.visibility_outlined,
    );
    if (ok && mounted) setState(() => _showTransparency = true);
  }

  @override
  Widget build(BuildContext context) => _MyDues(
    footer: SectionCard(
      key: const ValueKey('transparansi-warga'),
      title: 'Transparansi iuran warga',
      trailing: _showTransparency
          ? TextButton.icon(
              key: const ValueKey('sembunyikan-transparansi'),
              onPressed: () => setState(() => _showTransparency = false),
              icon: const Icon(Icons.visibility_off_outlined, size: 18),
              label: const Text('Sembunyikan'),
            )
          : null,
      child: _showTransparency
          ? const _TransparencyBody()
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Status iuran rumah lain per kode rumah. Disembunyikan secara bawaan untuk menjaga privasi tetangga.',
                  style: TextStyle(color: Brand.muted, height: 1.4),
                ),
                const SizedBox(height: 12),
                BusyButton(label: 'Lihat transparansi iuran', icon: Icons.visibility_outlined, outlined: true, onPressed: _reveal),
              ],
            ),
    ),
  );
}

class _MyDues extends ConsumerWidget {
  const _MyDues({this.footer});
  final Widget? footer;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dues = ref.watch(duesMyProvider);
    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(duesMyProvider);
        ref.invalidate(transparencyProvider(null));
      },
      child: AsyncView(
        value: dues,
        onRetry: () => ref.invalidate(duesMyProvider),
        builder: (d) {
          final list = (d['tagihan'] as List).cast<Map>().map((m) => Tagihan(Json.from(m))).toList();
          final arrears = d['tunggakan'] as Map;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              SectionCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Rumah ${(d['rumah'] as Map)['kode_rumah']}', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 4),
                    Text('Tarif berlaku: ${rupiah(d['tarif_berlaku'] as num? ?? 0)} per rumah per bulan'),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(child: _Stat('Tunggakan', '${arrears['jumlah_bulan']} bulan', danger: (arrears['jumlah_bulan'] as num) > 0)),
                        Expanded(child: _Stat('Total tunggakan', rupiah(arrears['total'] as num), danger: (arrears['total'] as num) > 0)),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(color: Brand.sky, borderRadius: BorderRadius.circular(12)),
                      child: Row(
                        children: [
                          const Icon(Icons.payments_outlined, color: Brand.navy),
                          const SizedBox(width: 10),
                          Expanded(child: Text('${d['catatan']}', style: const TextStyle(fontSize: 13))),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Text('Riwayat tagihan', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (list.isEmpty) const EmptyState(icon: Icons.receipt_long_outlined, title: 'Belum ada tagihan'),
              for (final t in list)
                Card(
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
                          ],
                        ),
                        StatusChip(t.status, label: t.label),
                      ],
                    ),
                  ),
                ),
              if (footer != null) ...[const SizedBox(height: 16), footer!],
            ],
          );
        },
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat(this.label, this.value, {this.danger = false});
  final String label;
  final String value;
  final bool danger;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(color: Brand.muted, fontSize: 13)),
      Text(
        value,
        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: danger ? Brand.danger : Brand.ink),
      ),
    ],
  );
}

/// Status iuran per kode rumah tanpa nama/kontak (BR15). Data baru diminta saat widget ini tampil.
class _TransparencyBody extends ConsumerWidget {
  const _TransparencyBody();
  @override
  Widget build(BuildContext context, WidgetRef ref) => AsyncView(
    value: ref.watch(transparencyProvider(null)),
    onRetry: () => ref.invalidate(transparencyProvider(null)),
    builder: (d) => Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Status iuran ${periode('${d['periode']}')}', style: Theme.of(context).textTheme.titleSmall),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _Stat('Lunas', '${d['lunas']} rumah')),
            Expanded(child: _Stat('Belum dibayar', '${d['belum_bayar']} rumah', danger: (d['belum_bayar'] as num) > 0)),
          ],
        ),
        const SizedBox(height: 8),
        const Text('Ditampilkan per kode rumah tanpa nama atau kontak warga.', style: TextStyle(color: Brand.muted, fontSize: 13)),
        const SizedBox(height: 8),
        for (final r in (d['rumah'] as List).cast<Map>())
          Card(
            margin: const EdgeInsets.only(bottom: 6),
            // Wrap, bukan ListTile: pada skala teks besar label status turun ke baris berikutnya.
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
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
                      Text('${r['kode_rumah']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                      Text(rupiah(r['nominal'] as num), style: const TextStyle(color: Brand.muted)),
                    ],
                  ),
                  StatusChip('${r['status']}', label: '${r['status_label']}'),
                ],
              ),
            ),
          ),
      ],
    ),
  );
}

/// Ringkasan kas RT, khusus pengurus (ADR26). Dipakai di tab Keuangan → Kas.
class CashView extends ConsumerWidget {
  const CashView({super.key, this.onReverse, this.action});

  /// Pengurus: aksi di atas daftar mutasi (mis. ekspor CSV).
  final Widget? action;

  /// Pengurus: balik kas manual yang `dapat_dibalik` (kas iuran dibatalkan lewat pembayaran).
  final Future<void> Function(Json entry)? onReverse;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(cashProvider);
    return RefreshIndicator(
      onRefresh: () async => ref.invalidate(cashProvider),
      child: AsyncView(
        value: data,
        onRetry: () => ref.invalidate(cashProvider),
        builder: (d) {
          final month = d['bulan_ini'] as Map;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(color: Brand.navy, borderRadius: BorderRadius.circular(20)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Saldo kas RT', style: TextStyle(color: Colors.white70)),
                    Text(rupiah(d['saldo'] as num), style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Colors.white)),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: Text('Masuk bulan ini\n${rupiah(month['pemasukan'] as num)}', style: const TextStyle(color: Colors.white)),
                        ),
                        Expanded(
                          child: Text('Keluar bulan ini\n${rupiah(month['pengeluaran'] as num)}', style: const TextStyle(color: Brand.yellow)),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              ?action,
              Text('Mutasi terakhir', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              for (final k in (d['mutasi_terakhir'] as List).cast<Map>())
                Card(
                  key: ValueKey('kas-${k['id']}'),
                  margin: const EdgeInsets.only(bottom: 6),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Padding(
                          padding: const EdgeInsets.only(top: 2, right: 12),
                          child: Icon(
                            (k['nominal'] as num) >= 0 ? Icons.south_west : Icons.north_east,
                            color: (k['nominal'] as num) >= 0 ? Brand.success : Brand.danger,
                          ),
                        ),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${k['keterangan']}', maxLines: 2, overflow: TextOverflow.ellipsis),
                              Text('${k['tanggal']} • ${k['kategori'] ?? k['jenis']}', style: const TextStyle(color: Brand.muted, fontSize: 13)),
                              Text(
                                rupiah(k['nominal'] as num),
                                style: TextStyle(fontWeight: FontWeight.w700, color: (k['nominal'] as num) >= 0 ? Brand.success : Brand.danger),
                              ),
                              if (onReverse != null && k['dapat_dibalik'] == true)
                                Align(
                                  alignment: Alignment.centerRight,
                                  child: TextButton.icon(
                                    key: ValueKey('balik-${k['id']}'),
                                    onPressed: () => onReverse!(Json.from(k)),
                                    icon: const Icon(Icons.undo),
                                    label: const Text('Balik'),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

// ─────────────────────────────────────────── Notifikasi
class NotificationsPage extends ConsumerWidget {
  const NotificationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(notificationsProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifikasi'),
        actions: [
          IconButton(
            tooltip: 'Tandai semua dibaca',
            icon: const Icon(Icons.done_all),
            onPressed: () async {
              try {
                final r = await ref.read(apiProvider).post('/notifications/read-all');
                ref.invalidate(notificationsProvider);
                final n = (r is Map ? r['ditandai'] : null) ?? 0;
                if (context.mounted) showMessage(context, n == 0 ? 'Semua notifikasi sudah dibaca.' : '$n notifikasi ditandai dibaca.');
              } on ApiException catch (e) {
                if (context.mounted) showApiError(context, e);
              }
            },
          ),
          IconButton(
            tooltip: 'Pengaturan notifikasi',
            icon: const Icon(Icons.tune),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationSettingsPage())),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(notificationsProvider),
        child: AsyncView(
          value: data,
          onRetry: () => ref.invalidate(notificationsProvider),
          builder: (d) {
            final list = (d['data'] as List).cast<Map>();
            if (list.isEmpty) {
              return ListView(
                children: const [EmptyState(icon: Icons.notifications_none, title: 'Belum ada notifikasi')],
              );
            }
            return ListView(
              padding: const EdgeInsets.all(12),
              children: [
                for (final n in list)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    color: n['dibaca_at'] == null ? Colors.white : Brand.sky,
                    child: ListTile(
                      leading: Icon(
                        const {
                              'pengaduan': Icons.report_outlined,
                              'aspirasi': Icons.lightbulb_outline,
                              'usulan': Icons.event_note_outlined,
                              'agenda': Icons.event,
                              'pengumuman': Icons.campaign_outlined,
                              'iuran': Icons.payments_outlined,
                            }[n['jenis']] ??
                            Icons.notifications_outlined,
                        color: Brand.navy,
                      ),
                      title: Text('${n['judul']}', style: TextStyle(fontWeight: n['dibaca_at'] == null ? FontWeight.w800 : FontWeight.w500)),
                      subtitle: Text('${n['isi']}\n${tanggal(parseTime(n['created_at']), jam: true)}'),
                      isThreeLine: true,
                      onTap: () async {
                        if (n['dibaca_at'] == null) {
                          try {
                            await ref.read(apiProvider).patch('/notifications/${n['id']}/read');
                          } on ApiException {
                            // Status baca gagal tersimpan tidak menghalangi membuka detail.
                          }
                          ref.invalidate(notificationsProvider);
                        }
                        if (!context.mounted) return;
                        // Detail dimuat ulang dari API (policy server), bukan dari isi notifikasi.
                        final r = n['referensi'] as Map?;
                        openReference(context, type: r?['tipe'] as String?, id: r?['id'] as String?, admin: ref.read(sessionProvider).pengurus);
                      },
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────── Akun & keluarga
class AccountPage extends ConsumerWidget {
  const AccountPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final akun = ref.watch(sessionProvider).akun!;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        SectionCard(
          child: Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: Brand.navy,
                child: Text(akun.nama.isEmpty ? '?' : akun.nama[0].toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 22)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(akun.nama, style: Theme.of(context).textTheme.titleMedium),
                    Text(akun.email, style: const TextStyle(color: Brand.muted)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      children: [
                        StatusChip('aktif', label: akun.peran == 'pengurus' ? 'Pengurus' : 'Warga'),
                        StatusChip('x', label: akun.jenis == 'utama' ? 'Akun utama' : 'Akun tambahan'),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (akun.rumah == null) ...[
          const SizedBox(height: 16),
          const SectionCard(
            title: 'Keluarga',
            child: Text('Belum terhubung ke rumah.', style: TextStyle(color: Brand.muted, height: 1.35)),
          ),
        ] else ...[
          const SizedBox(height: 16),
          const FamilyPanel(),
        ],
        const SizedBox(height: 16),
        SectionCard(
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.notifications_outlined, color: Brand.navy),
            title: const Text('Pengaturan notifikasi'),
            subtitle: const Text('Izin push di HP ini dan jenis yang dikirim'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationSettingsPage())),
          ),
        ),
        const SizedBox(height: 16),
        const _AccountSecurity(),
        const SizedBox(height: 16),
        const Center(
          child: Text('RT05 TAKEDA • data warga hanya untuk administrasi RT', style: TextStyle(color: Brand.muted, fontSize: 12)),
        ),
      ],
    );
  }
}

class _AccountSecurity extends ConsumerStatefulWidget {
  const _AccountSecurity();

  @override
  ConsumerState<_AccountSecurity> createState() => _AccountSecurityState();
}

class _AccountSecurityState extends ConsumerState<_AccountSecurity> {
  bool _busy = false;

  Future<void> _guard(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _logout() => _guard(() async {
    try {
      await revokeServerSession(ref, path: '/auth/logout');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, keptSessionMessage(error), error: true);
    }
  });

  Future<void> _logoutAll() => _guard(() async {
    final password = await askCurrentPassword(context);
    if (password == null || password.isEmpty || !mounted) return;
    try {
      await revokeServerSession(ref, path: '/auth/logout-all', data: {'password': password});
    } on ApiException catch (error) {
      if (mounted) showMessage(context, keptSessionMessage(error), error: true);
    }
  });

  @override
  Widget build(BuildContext context) => SectionCard(
    title: 'Keamanan',
    child: Column(
      children: [
        _SecurityTile(
          icon: Icons.password,
          title: 'Ganti kata sandi',
          onTap: _busy ? null : () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ChangePasswordPage())),
        ),
        _SecurityTile(
          icon: Icons.devices,
          title: 'Keluar dari semua perangkat',
          subtitle: 'Mencabut semua token setelah kata sandi dikonfirmasi.',
          onTap: _busy ? null : _logoutAll,
        ),
        _SecurityTile(
          icon: Icons.logout,
          iconColor: Brand.danger,
          title: 'Keluar',
          titleColor: Brand.danger,
          subtitle: 'Mencabut token perangkat ini di server, lalu menghapusnya dari perangkat.',
          onTap: _busy ? null : _logout,
        ),
      ],
    ),
  );
}

class _SecurityTile extends StatelessWidget {
  const _SecurityTile({required this.icon, required this.title, required this.onTap, this.subtitle, this.iconColor = Brand.navy, this.titleColor});

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? onTap;
  final Color iconColor;
  final Color? titleColor;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: iconColor),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(color: titleColor, fontWeight: FontWeight.w700),
                ),
                if (subtitle != null) Text(subtitle!, style: const TextStyle(color: Brand.muted)),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class ChangePasswordPage extends ConsumerStatefulWidget {
  const ChangePasswordPage({super.key});
  @override
  ConsumerState<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends ConsumerState<ChangePasswordPage> {
  final _form = GlobalKey<FormState>();
  final _old = TextEditingController();
  final _new = TextEditingController();
  final _again = TextEditingController();
  var _busy = false;
  var _fields = <String, List<String>>{};
  String? _error;

  @override
  void dispose() {
    _old.dispose();
    _new.dispose();
    _again.dispose();
    super.dispose();
  }

  String? _err(String key) => _fields[key]?.first;

  Future<void> _submit() async {
    if (_busy) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _busy = true;
      _fields = {};
      _error = null;
    });
    if (!_form.currentState!.validate()) {
      if (mounted) setState(() => _busy = false);
      return;
    }
    final session = ref.read(sessionProvider.notifier);
    final epoch = session.epoch;
    final token = ref.read(sessionProvider).token;
    try {
      await ref.read(apiProvider).patch('/auth/password', data: {'current_password': _old.text, 'password': _new.text, 'password_confirmation': _again.text});
      if (!mounted || session.epoch != epoch || ref.read(sessionProvider).token != token) return;
      showMessage(context, 'Kata sandi diperbarui. Perangkat lain telah dikeluarkan.');
      Navigator.pop(context);
    } on ApiException catch (error) {
      if (!mounted || session.epoch != epoch || ref.read(sessionProvider).token != token) return;
      setState(() {
        _fields = error.fields;
        _error = error.fields.isEmpty ? error.display : 'Periksa kembali isian yang ditandai.';
      });
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Ganti kata sandi')),
    body: Form(
      key: _form,
      child: ListView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        padding: const EdgeInsets.all(16),
        children: [
          TextFormField(
            key: const ValueKey('field-current-password'),
            controller: _old,
            obscureText: true,
            decoration: InputDecoration(labelText: 'Kata sandi saat ini', errorText: _err('current_password'), errorMaxLines: 16),
            validator: (value) => (value == null || value.isEmpty) ? 'Wajib diisi' : null,
          ),
          const SizedBox(height: 14),
          TextFormField(
            key: const ValueKey('field-new-password'),
            controller: _new,
            obscureText: true,
            decoration: passwordRuleDecoration(label: 'Kata sandi baru', errorText: _err('password')),
            validator: validateNewPassword,
          ),
          const SizedBox(height: 14),
          TextFormField(
            key: const ValueKey('field-new-password2'),
            controller: _again,
            obscureText: true,
            decoration: InputDecoration(labelText: 'Ulangi kata sandi baru', errorText: _err('password_confirmation'), errorMaxLines: 16),
            validator: (value) {
              final match = validatePasswordConfirmation(value, _new.text);
              if (match != null) return match;
              if (value == _old.text) return 'Kata sandi baru harus berbeda.';
              return null;
            },
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(_error!, style: const TextStyle(color: Brand.danger)),
            ),
          const SizedBox(height: 20),
          BusyButton(label: 'Simpan', onPressed: _busy ? null : _submit),
        ],
      ),
    ),
  );
}
