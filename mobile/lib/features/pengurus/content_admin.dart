import 'dart:io';

import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';

/// M13: pengurus mengelola konten website (artikel, album, fasilitas, profil). Draf disimpan berversi;
/// pengunjung baru melihat perubahan setelah **Terbitkan**. Semua aturan (slug, foto siap, versi)
/// ditegakkan server; layar ini hanya membantu mengisi dan menampilkan hasilnya.

const contentTypes = [('articles', 'Artikel'), ('albums', 'Album'), ('facilities', 'Fasilitas')];
const articleArts = {'hands': 'Tangan bersih', 'waste': 'Pilah sampah', 'water': 'Air & saluran', 'home': 'Bertetangga', 'park': 'Fasilitas bersama'};

/// "Kerja Bakti Bulanan!" → "kerja-bakti-bulanan" (maks. 120, sesuai aturan slug server).
String slugify(String s) {
  final base = s.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), '-').replaceAll(RegExp(r'^-+|-+$'), '');
  return base.length > 120 ? base.substring(0, 120).replaceAll(RegExp(r'-+$'), '') : base;
}

String contentStatusLabel(Json k) => switch (k['status']) {
  'terbit' when k['ada_perubahan_belum_terbit'] == true => 'Terbit • ada draf baru',
  'terbit' => 'Terbit',
  'arsip' => 'Diarsipkan',
  _ => 'Draf',
};

final contentListProvider = FutureProvider.autoDispose.family<List<Json>, String>(
  (ref, type) async => ((await ref.read(apiProvider).page('/admin/$type'))['data'] as List).cast<Map>().map(Json.from).toList(),
);

final siteProfileProvider = FutureProvider.autoDispose<Json>((ref) async => Json.from(await ref.read(apiProvider).get('/admin/site-profile') as Map));

/// Lainnya → Konten website.
class ContentAdminPage extends ConsumerWidget {
  const ContentAdminPage({super.key, this.picker});
  final ImagePicker? picker;

  @override
  Widget build(BuildContext context, WidgetRef ref) => DefaultTabController(
    length: 4,
    child: Scaffold(
      appBar: AppBar(
        title: const Text('Konten website'),
        // Empat tab selalu terlihat di 320–360 dp: lebar dibagi rata, label dibatasi 1,3× seperti navigasi bawah.
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(kTextTabBarHeight),
          child: Builder(
            builder: (context) => MediaQuery.withClampedTextScaling(
              maxScaleFactor: 1.3,
              child: const TabBar(
                labelPadding: EdgeInsets.symmetric(horizontal: 4),
                tabs: [
                  Tab(text: 'Artikel'),
                  Tab(text: 'Album'),
                  Tab(text: 'Fasilitas'),
                  Tab(text: 'Profil'),
                ],
              ),
            ),
          ),
        ),
      ),
      body: TabBarView(
        children: [
          for (final (type, label) in contentTypes) _ContentList(type: type, label: label, picker: picker),
          const SiteProfileEditor(),
        ],
      ),
    ),
  );
}

class _ContentList extends ConsumerWidget {
  const _ContentList({required this.type, required this.label, this.picker});
  final String type;
  final String label;
  final ImagePicker? picker;

  Future<void> _open(BuildContext context, WidgetRef ref, [Json? item]) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ContentEditorPage(type: type, initial: item, picker: picker),
      ),
    );
    ref.invalidate(contentListProvider(type));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    backgroundColor: Brand.sky,
    floatingActionButton: FloatingActionButton.extended(
      heroTag: 'baru-$type',
      backgroundColor: Brand.yellow,
      foregroundColor: Brand.navy,
      icon: const Icon(Icons.add),
      label: Text('$label baru'),
      onPressed: () => _open(context, ref),
    ),
    body: RefreshIndicator(
      onRefresh: () async => ref.invalidate(contentListProvider(type)),
      child: AsyncView(
        value: ref.watch(contentListProvider(type)),
        onRetry: () => ref.invalidate(contentListProvider(type)),
        builder: (list) => ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
          children: [
            const Text(
              'Perubahan baru tampil di website setelah diterbitkan. Draf tidak pernah terlihat pengunjung.',
              style: TextStyle(color: Brand.muted, height: 1.4),
            ),
            const SizedBox(height: 12),
            if (list.isEmpty) EmptyState(icon: Icons.article_outlined, title: 'Belum ada $label'),
            for (final k in list)
              Card(
                key: ValueKey('konten-${k['id']}'),
                margin: const EdgeInsets.only(bottom: 8),
                child: InkWell(
                  onTap: () => _open(context, ref, k),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
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
                            Text('${(k['draf'] as Map?)?['title'] ?? k['slug']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                            Text('/${k['slug']}', style: const TextStyle(color: Brand.muted, fontSize: 13)),
                          ],
                        ),
                        StatusChip(k['status'] == 'terbit' ? 'aktif' : (k['status'] == 'arsip' ? 'nonaktif' : 'diajukan'), label: contentStatusLabel(k)),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    ),
  );
}

/// Foto konten: unggah ke server (diproses ulang, EXIF dibuang) lalu simpan ID-nya di draf.
class _Photo {
  _Photo({required this.mediaId, this.local, this.alt = '', this.caption = ''});
  String mediaId;
  File? local;
  String alt;
  String caption;
}

class ContentEditorPage extends ConsumerStatefulWidget {
  const ContentEditorPage({super.key, required this.type, this.initial, this.picker});
  final String type;
  final Json? initial;
  final ImagePicker? picker;
  @override
  ConsumerState<ContentEditorPage> createState() => _ContentEditorPageState();
}

class _ContentEditorPageState extends ConsumerState<ContentEditorPage> {
  late Json? _item = widget.initial;
  late final Map _d = Map.of((widget.initial?['draf'] as Map?) ?? {});
  late final _slug = TextEditingController(text: '${widget.initial?['slug'] ?? ''}');
  late final _title = TextEditingController(text: '${_d['title'] ?? ''}');
  late final _desc = TextEditingController(text: '${_d['description'] ?? _d['summary'] ?? ''}');
  late final _category = TextEditingController(text: '${_d['category'] ?? ''}');
  late final _intro = TextEditingController(text: '${_d['intro'] ?? ''}');
  late final _note = TextEditingController(text: '${_d['note'] ?? ''}');
  late final _sourceLabel = TextEditingController(text: '${(_d['source'] as Map?)?['label'] ?? ''}');
  late final _sourceUrl = TextEditingController(text: '${(_d['source'] as Map?)?['url'] ?? ''}');
  late final _date = TextEditingController(text: '${_d['date'] ?? ''}');
  late String? _art = _d['art'] as String?;
  late final List<(TextEditingController, TextEditingController)> _sections = [
    for (final s in (_d['sections'] as List? ?? []).cast<Map>()) (TextEditingController(text: '${s['title']}'), TextEditingController(text: '${s['body']}')),
  ];
  late final List<_Photo> _photos = [
    if (widget.type == 'albums')
      for (final p in (_d['photos'] as List? ?? []).cast<Map>())
        _Photo(mediaId: '${p['media_id']}', alt: '${p['alt'] ?? ''}', caption: '${p['caption'] ?? ''}'),
    if (widget.type == 'facilities' && _d['media_id'] != null) _Photo(mediaId: '${_d['media_id']}', alt: '${_d['alt'] ?? ''}'),
    if (widget.type == 'articles' && _d['cover_media_id'] != null) _Photo(mediaId: '${_d['cover_media_id']}', alt: '${_d['cover_alt'] ?? ''}'),
  ];
  late String? _cover = _d['cover_media_id'] as String?;
  Map<String, String> _errors = {};
  bool _dirty = false;

  bool get _archived => _item?['status'] == 'arsip';
  String get _label => contentTypes.firstWhere((t) => t.$1 == widget.type).$2;

  Map<String, dynamic> _payload() => switch (widget.type) {
    'articles' => {
      'title': _title.text.trim(),
      'category': _category.text.trim(),
      'art': _art,
      'summary': _desc.text.trim(),
      'intro': _intro.text.trim(),
      'sections': [
        for (final (t, b) in _sections) {'title': t.text.trim(), 'body': b.text.trim()},
      ],
      'note': _note.text.trim().isEmpty ? null : _note.text.trim(),
      'source': _sourceLabel.text.trim().isEmpty && _sourceUrl.text.trim().isEmpty
          ? null
          : {'label': _sourceLabel.text.trim(), 'url': _sourceUrl.text.trim().isEmpty ? null : _sourceUrl.text.trim()},
      if (_photos.isNotEmpty) 'cover_media_id': _photos.first.mediaId,
      if (_photos.isNotEmpty) 'cover_alt': _photos.first.alt.trim(),
    },
    'albums' => {
      'title': _title.text.trim(),
      'description': _desc.text.trim(),
      'date': _date.text.trim().isEmpty ? null : _date.text.trim(),
      'cover_media_id': _photos.any((p) => p.mediaId == _cover) ? _cover : null,
      'photos': [
        for (final p in _photos) {'media_id': p.mediaId, 'alt': p.alt.trim(), 'caption': p.caption.trim().isEmpty ? null : p.caption.trim()},
      ],
    },
    _ => {
      'title': _title.text.trim(),
      'description': _desc.text.trim(),
      if (_photos.isNotEmpty) 'media_id': _photos.first.mediaId,
      if (_photos.isNotEmpty) 'alt': _photos.first.alt.trim(),
    },
  };

  void _changed() => _dirty = true;

  Future<bool> _save({bool quiet = false}) async {
    final api = ref.read(apiProvider);
    final body = {'slug': _slug.text.trim(), 'draf': _payload(), if (_item != null) 'versi': _item!['versi']};
    try {
      final res = _item == null ? await api.post('/admin/${widget.type}', data: body) : await api.put('/admin/${widget.type}/${_item!['id']}', data: body);
      setState(() {
        _item = Json.from(res as Map);
        _errors = {};
        _dirty = false;
      });
      if (!quiet && mounted) showMessage(context, 'Draf tersimpan. Pengunjung belum melihat perubahan ini.');
      return true;
    } on ApiException catch (e) {
      if (!mounted) return false;
      setState(() => _errors = {for (final f in e.fields.entries) f.key.replaceFirst('draf.', ''): f.value.first});
      showApiError(context, e);
      return false;
    }
  }

  Future<void> _publish() async {
    final ok = await confirm(
      context,
      title: 'Terbitkan $_label?',
      message: 'Isi draf ini akan tampil di website untuk semua pengunjung. Ubah slug membuat alamat lama dialihkan ke alamat baru.',
      yes: 'Terbitkan',
      icon: Icons.public,
    );
    if (!ok || !mounted) return;
    if ((_item == null || _dirty) && !await _save(quiet: true)) return;
    try {
      final res = await ref.read(apiProvider).post('/admin/${widget.type}/${_item!['id']}/publish', data: {'versi': _item!['versi']});
      setState(() => _item = Json.from(res as Map));
      if (mounted) await showResultDialog(context, title: '$_label diterbitkan', message: 'Tampil di website pada alamat /${_item!['slug']}.');
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _archive() async {
    final ok = await confirm(
      context,
      title: 'Arsipkan $_label?',
      message: 'Konten hilang dari website dan tidak dapat diubah lagi. Riwayat tetap tersimpan.',
      yes: 'Arsipkan',
      danger: true,
      icon: Icons.archive_outlined,
    );
    if (!ok || !mounted) return;
    try {
      final res = await ref.read(apiProvider).post('/admin/${widget.type}/${_item!['id']}/archive', data: {'versi': _item!['versi']});
      setState(() => _item = Json.from(res as Map));
      if (mounted) await showResultDialog(context, title: '$_label diarsipkan', message: 'Tidak lagi tampil di website.');
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _restore() async {
    final ok = await confirm(
      context,
      title: 'Pulihkan $_label?',
      message: 'Kembali menjadi draf dan belum tampil di website. Periksa isinya lalu tekan Terbitkan.',
      yes: 'Pulihkan',
      icon: Icons.unarchive_outlined,
    );
    if (!ok || !mounted) return;
    try {
      final res = await ref.read(apiProvider).post('/admin/${widget.type}/${_item!['id']}/restore', data: {'versi': _item!['versi']});
      setState(() => _item = Json.from(res as Map));
      if (mounted) await showResultDialog(context, title: '$_label dipulihkan', message: 'Sekarang berstatus draf. Tekan Terbitkan agar tampil di website.');
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _addPhoto() async {
    final picked = await (widget.picker ?? ImagePicker()).pickImage(source: ImageSource.gallery, maxWidth: 2400, maxHeight: 2400, imageQuality: 85);
    if (picked == null || !mounted) return;
    try {
      final form = FormData()..files.add(MapEntry('foto', await MultipartFile.fromFile(picked.path, filename: 'foto.jpg')));
      final res = Json.from(await ref.read(apiProvider).post('/admin/content-media', data: form) as Map);
      setState(() {
        final photo = _Photo(mediaId: '${res['id']}', local: File(picked.path));
        if (widget.type == 'albums') {
          _photos.add(photo);
          _cover ??= photo.mediaId;
        } else {
          _photos
            ..clear()
            ..add(photo);
        }
        _dirty = true;
      });
      if (mounted) showMessage(context, 'Foto diunggah. Isi teks alternatif (deskripsi foto) sebelum menerbitkan.');
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Widget _field(TextEditingController c, String label, String key, {int lines = 1, int? max, String? hint}) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextField(
      key: ValueKey('f-$key'),
      controller: c,
      maxLines: lines,
      maxLength: max,
      enabled: !_archived,
      onChanged: (_) => _changed(),
      decoration: InputDecoration(labelText: label, hintText: hint, errorText: _errors[key], alignLabelWithHint: lines > 1),
    ),
  );

  Widget _photoTile(int i) {
    final p = _photos[i];
    return Card(
      key: ValueKey('foto-${p.mediaId}'),
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                SizedBox(
                  width: 64,
                  height: 64,
                  child: p.local != null
                      ? Image.file(p.local!, fit: BoxFit.cover, errorBuilder: (_, _, _) => const Icon(Icons.image_outlined))
                      : const DecoratedBox(
                          decoration: BoxDecoration(color: Brand.sky),
                          child: Icon(Icons.image_outlined, color: Brand.navy),
                        ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: widget.type == 'albums'
                      ? Wrap(
                          spacing: 4,
                          children: [
                            ChoiceChip(
                              label: const Text('Sampul'),
                              selected: _cover == p.mediaId,
                              onSelected: _archived
                                  ? null
                                  : (_) => setState(() {
                                      _cover = p.mediaId;
                                      _dirty = true;
                                    }),
                            ),
                            IconButton(
                              tooltip: 'Naikkan',
                              onPressed: i == 0 || _archived
                                  ? null
                                  : () => setState(() {
                                      _photos.insert(i - 1, _photos.removeAt(i));
                                      _dirty = true;
                                    }),
                              icon: const Icon(Icons.arrow_upward),
                            ),
                          ],
                        )
                      : const Text('Foto utama'),
                ),
                IconButton(
                  tooltip: 'Hapus foto dari draf',
                  onPressed: _archived
                      ? null
                      : () => setState(() {
                          _photos.removeAt(i);
                          _dirty = true;
                        }),
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ),
            const SizedBox(height: 8),
            TextFormField(
              key: ValueKey('alt-${p.mediaId}'),
              initialValue: p.alt,
              enabled: !_archived,
              onChanged: (v) {
                p.alt = v;
                _dirty = true;
              },
              decoration: InputDecoration(
                labelText: 'Deskripsi foto untuk pembaca layar (wajib)',
                errorText: _errors['photos.$i.alt'] ?? (widget.type == 'facilities' ? _errors['alt'] : _errors['cover_alt']),
              ),
            ),
            if (widget.type == 'albums')
              TextFormField(
                initialValue: p.caption,
                enabled: !_archived,
                onChanged: (v) {
                  p.caption = v;
                  _dirty = true;
                },
                decoration: const InputDecoration(labelText: 'Keterangan (opsional)'),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_item == null ? '$_label baru' : 'Ubah $_label')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_item != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: StatusChip(_item!['status'] == 'terbit' ? 'aktif' : (_archived ? 'nonaktif' : 'diajukan'), label: contentStatusLabel(_item!)),
          ),
        _field(_title, 'Judul', 'title', max: widget.type == 'facilities' ? 120 : 160),
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: TextField(
            key: const ValueKey('f-slug'),
            controller: _slug,
            enabled: !_archived,
            onChanged: (_) => _changed(),
            decoration: InputDecoration(
              labelText: 'Alamat (slug)',
              helperText: 'Huruf kecil, angka, tanda hubung. Contoh: kerja-bakti-bulanan',
              errorText: _errors['slug'],
              suffixIcon: IconButton(
                tooltip: 'Buat dari judul',
                onPressed: _archived
                    ? null
                    : () => setState(() {
                        _slug.text = slugify(_title.text);
                        _dirty = true;
                      }),
                icon: const Icon(Icons.auto_fix_high),
              ),
            ),
          ),
        ),
        if (widget.type == 'articles') ...[
          _field(_category, 'Kategori', 'category', hint: 'mis. Lingkungan, Kesehatan'),
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: DropdownButtonFormField<String>(
              isExpanded: true,
              initialValue: articleArts.containsKey(_art) ? _art : null,
              decoration: const InputDecoration(labelText: 'Ilustrasi'),
              items: [for (final e in articleArts.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
              onChanged: _archived
                  ? null
                  : (v) => setState(() {
                      _art = v;
                      _dirty = true;
                    }),
            ),
          ),
          _field(_desc, 'Ringkasan', 'summary', lines: 2, max: 300),
          _field(_intro, 'Pembuka', 'intro', lines: 3, max: 2000),
          Text('Bagian', style: Theme.of(context).textTheme.titleMedium),
          if (_errors['sections'] != null) Text(_errors['sections']!, style: const TextStyle(color: Brand.danger)),
          for (var i = 0; i < _sections.length; i++)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  children: [
                    _field(_sections[i].$1, 'Judul bagian ${i + 1}', 'sections.$i.title'),
                    _field(_sections[i].$2, 'Isi bagian ${i + 1}', 'sections.$i.body', lines: 4),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton.icon(
                        onPressed: _archived
                            ? null
                            : () => setState(() {
                                _sections.removeAt(i);
                                _dirty = true;
                              }),
                        icon: const Icon(Icons.remove_circle_outline),
                        label: const Text('Hapus bagian'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          OutlinedButton.icon(
            key: const ValueKey('tambah-bagian'),
            onPressed: _archived || _sections.length >= 12
                ? null
                : () => setState(() {
                    _sections.add((TextEditingController(), TextEditingController()));
                    _dirty = true;
                  }),
            icon: const Icon(Icons.add),
            label: const Text('Tambah bagian'),
          ),
          const SizedBox(height: 10),
          _field(_note, 'Catatan (opsional)', 'note', lines: 2, max: 500),
          _field(_sourceLabel, 'Nama sumber (opsional)', 'source.label'),
          _field(_sourceUrl, 'Tautan sumber https:// (opsional)', 'source.url'),
        ] else ...[
          _field(_desc, 'Deskripsi', 'description', lines: 4, max: widget.type == 'albums' ? 500 : 2000),
          if (widget.type == 'albums') _field(_date, 'Tanggal kegiatan YYYY-MM-DD (opsional; kosongkan bila tidak diketahui)', 'date'),
        ],
        const SizedBox(height: 8),
        Text(widget.type == 'albums' ? 'Foto album' : 'Foto (opsional)', style: Theme.of(context).textTheme.titleMedium),
        if (_errors['photos'] != null) Text(_errors['photos']!, style: const TextStyle(color: Brand.danger)),
        for (var i = 0; i < _photos.length; i++) _photoTile(i),
        if (widget.type == 'albums' || _photos.isEmpty)
          OutlinedButton.icon(
            key: const ValueKey('tambah-foto'),
            onPressed: _archived ? null : _addPhoto,
            icon: const Icon(Icons.add_photo_alternate_outlined),
            label: const Text('Pilih foto dari galeri'),
          ),
        const SizedBox(height: 20),
        if (!_archived) ...[
          BusyButton(label: 'Simpan draf', icon: Icons.save_outlined, outlined: true, onPressed: () => _save()),
          const SizedBox(height: 8),
          BusyButton(label: 'Terbitkan', icon: Icons.public, onPressed: _publish),
          if (_item != null) ...[
            const SizedBox(height: 8),
            TextButton.icon(
              key: const ValueKey('arsipkan'),
              onPressed: _archive,
              icon: const Icon(Icons.archive_outlined, color: Brand.danger),
              label: const Text('Arsipkan', style: TextStyle(color: Brand.danger)),
            ),
          ],
        ] else ...[
          const Text('Konten diarsipkan: tidak tampil di website dan tidak dapat diubah sampai dipulihkan.', style: TextStyle(color: Brand.muted)),
          const SizedBox(height: 8),
          OutlinedButton.icon(key: const ValueKey('pulihkan'), onPressed: _restore, icon: const Icon(Icons.unarchive_outlined), label: const Text('Pulihkan')),
        ],
      ],
    ),
  );
}

/// Profil situs: fakta RT (wilayah, ringkasan, pengurus). Simpan = terbit (satu langkah, versi).
class SiteProfileEditor extends ConsumerStatefulWidget {
  const SiteProfileEditor({super.key});
  @override
  ConsumerState<SiteProfileEditor> createState() => _SiteProfileEditorState();
}

class _SiteProfileEditorState extends ConsumerState<SiteProfileEditor> {
  Json? _loaded;
  final _wilayah = TextEditingController();
  final _ringkasan = TextEditingController();
  final List<(TextEditingController, TextEditingController, TextEditingController)> _people = [];
  Map<String, String> _errors = {};

  void _fill(Json p) {
    _loaded = p;
    final d = p['draf'] as Map;
    _wilayah.text = '${d['wilayah'] ?? ''}';
    _ringkasan.text = '${d['ringkasan'] ?? ''}';
    _people
      ..clear()
      ..addAll([
        for (final x in (d['pengurus'] as List).cast<Map>())
          (TextEditingController(text: '${x['jabatan']}'), TextEditingController(text: '${x['nama']}'), TextEditingController(text: '${x['catatan'] ?? ''}')),
      ]);
  }

  Future<void> _save() async {
    final ok = await confirm(
      context,
      title: 'Simpan dan terbitkan profil?',
      message: 'Fakta profil langsung tampil di website. Jangan menulis nama atau kontak yang belum disetujui untuk dipublikasikan.',
      yes: 'Terbitkan',
      icon: Icons.public,
    );
    if (!ok || !mounted) return;
    final d = Map.of(_loaded!['draf'] as Map)
      ..['wilayah'] = _wilayah.text.trim()
      ..['ringkasan'] = _ringkasan.text.trim()
      ..['pengurus'] = [
        for (final (j, n, c) in _people) {'jabatan': j.text.trim(), 'nama': n.text.trim(), 'catatan': c.text.trim().isEmpty ? null : c.text.trim()},
      ];
    try {
      final res = await ref.read(apiProvider).put('/admin/site-profile', data: {'versi': _loaded!['versi'], 'draf': d});
      setState(() {
        _fill(Json.from(res as Map));
        _errors = {};
      });
      if (mounted) await showResultDialog(context, title: 'Profil diterbitkan', message: 'Website menampilkan fakta profil terbaru.');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _errors = {for (final f in e.fields.entries) f.key: f.value.first});
      showApiError(context, e);
      if (e.isConflict) ref.invalidate(siteProfileProvider);
    }
  }

  @override
  Widget build(BuildContext context) => AsyncView(
    value: ref.watch(siteProfileProvider),
    onRetry: () => ref.invalidate(siteProfileProvider),
    builder: (p) {
      if (_loaded == null || (_loaded!['versi'] as int) < (p['versi'] as int)) _fill(p);
      return ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _wilayah,
            decoration: InputDecoration(labelText: 'Wilayah', errorText: _errors['wilayah']),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _ringkasan,
            maxLines: 3,
            decoration: InputDecoration(labelText: 'Ringkasan', errorText: _errors['ringkasan']),
          ),
          const SizedBox(height: 16),
          Text('Pengurus', style: Theme.of(context).textTheme.titleMedium),
          const Text('Tulis "Nama belum dipublikasikan" bila belum ada persetujuan.', style: TextStyle(color: Brand.muted, fontSize: 13)),
          for (var i = 0; i < _people.length; i++)
            Card(
              margin: const EdgeInsets.only(top: 8),
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  children: [
                    TextField(
                      controller: _people[i].$1,
                      decoration: InputDecoration(labelText: 'Jabatan', errorText: _errors['pengurus.$i.jabatan']),
                    ),
                    TextField(
                      controller: _people[i].$2,
                      decoration: InputDecoration(labelText: 'Nama', errorText: _errors['pengurus.$i.nama']),
                    ),
                    TextField(
                      controller: _people[i].$3,
                      decoration: const InputDecoration(labelText: 'Catatan (opsional)'),
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 16),
          BusyButton(label: 'Simpan dan terbitkan', icon: Icons.public, onPressed: _save),
        ],
      );
    },
  );
}
