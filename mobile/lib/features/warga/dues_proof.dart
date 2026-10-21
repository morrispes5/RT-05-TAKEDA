import 'dart:typed_data';

import 'package:dio/dio.dart' show FormData, MultipartFile;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart' show FilteringTextInputFormatter, PlatformException;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/widgets.dart';
import 'providers.dart';
import 'report_actions.dart';

/// Batas ukuran foto dari server (JPG/PNG/WebP, maks 5 MB).
const _maxFotoBytes = 5 * 1024 * 1024;

/// Kunci StatusChip untuk status bukti; label tetap memakai status_label dari server.
String duesProofChipStatus(String status) => switch (status) {
  'selesai' => 'aktif',
  'ditolak' => 'nonaktif',
  _ => 'menunggu',
};

/// Kirim bukti transfer iuran. Pembayaran tetap offline dan dicatat pengurus; foto hanya konfirmasi.
class DuesProofPage extends ConsumerStatefulWidget {
  const DuesProofPage({super.key, this.picker});

  /// Diganti pada tes; default ImagePicker plugin (kamera/galeri).
  final ImagePicker? picker;

  @override
  ConsumerState<DuesProofPage> createState() => _DuesProofPageState();
}

class _DuesProofPageState extends ConsumerState<DuesProofPage> {
  late final ImagePicker _picker = widget.picker ?? ImagePicker();
  Uint8List? _bytes;
  final Set<String> _selected = {};
  final _nominal = TextEditingController();
  final _catatan = TextEditingController();
  bool _nominalDiubah = false;
  String? _formError;

  @override
  void dispose() {
    _nominal.dispose();
    _catatan.dispose();
    super.dispose();
  }

  /// Nominal terisi otomatis dari jumlah bulan yang dicentang, sampai warga mengubahnya sendiri.
  void _centang(Tagihan t, bool on, List<Tagihan> tagihan) {
    setState(() {
      if (on) {
        _selected.add(t.periode);
      } else {
        _selected.remove(t.periode);
      }
      if (!_nominalDiubah) {
        final total = tagihan.where((x) => _selected.contains(x.periode)).fold<int>(0, (sum, x) => sum + x.nominal);
        _nominal.text = _selected.isEmpty ? '' : '$total';
      }
    });
  }

  Future<void> _pick(ImageSource source) async {
    try {
      final file = await _picker.pickImage(source: source, maxWidth: 1600, maxHeight: 1600, imageQuality: 80);
      if (file == null || !mounted) return;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      if (bytes.length > _maxFotoBytes) {
        showMessage(context, 'Foto lebih dari 5 MB. Pilih foto lain.', kind: AlertKind.warning, title: 'Foto terlalu besar');
        return;
      }
      setState(() {
        _bytes = bytes;
        _formError = null;
      });
    } on PlatformException catch (e) {
      if (mounted) {
        showMessage(context, photoPickMessage(e.code, camera: source == ImageSource.camera) ?? 'Foto tidak dapat diambil.', error: true);
      }
    } catch (_) {
      if (mounted) showMessage(context, 'Foto tidak dapat dibaca. Coba foto lain.', error: true);
    }
  }

  Future<void> _kirim(List<Tagihan> tagihan) async {
    final bytes = _bytes;
    final periodes = [
      for (final t in tagihan)
        if (_selected.contains(t.periode)) t.periode,
    ];
    final nominalText = _nominal.text.trim();
    final nominal = nominalText.isEmpty ? null : int.tryParse(nominalText);
    final catatan = _catatan.text.trim();
    if (bytes == null) {
      setState(() => _formError = 'Pilih foto bukti transfer dulu.');
      return;
    }
    if (periodes.isEmpty) {
      setState(() => _formError = 'Centang minimal satu bulan yang sudah dibayar.');
      return;
    }
    if (nominalText.isNotEmpty && (nominal == null || nominal <= 0)) {
      setState(() => _formError = 'Nominal harus angka lebih dari nol.');
      return;
    }
    setState(() => _formError = null);
    final ok = await confirm(
      context,
      title: 'Kirim bukti transfer?',
      message: 'Pengurus akan memeriksa foto ini untuk ${periodes.length} bulan. Foto hanya bukti; pengurus tetap mencatat pembayaran.',
      yes: 'Kirim',
      icon: Icons.send_outlined,
    );
    if (!ok || !mounted) return;
    try {
      final form = FormData()
        ..files.add(MapEntry('foto', MultipartFile.fromBytes(bytes, filename: 'bukti.jpg')))
        ..fields.addAll([
          for (final p in periodes) MapEntry('periode[]', p),
          if (nominal != null) MapEntry('nominal', '$nominal'),
          if (catatan.isNotEmpty) MapEntry('catatan', catatan),
        ]);
      await ref.read(apiProvider).post('/dues/confirmations', data: form);
      if (!mounted) return;
      await showResultDialog(
        context,
        title: 'Bukti terkirim',
        message: 'Pengurus akan memeriksa dan mencatat pembayaran Anda. Status tagihan berubah setelah dicatat.',
      );
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showApiError(context, e);
    } catch (_) {
      if (mounted) showMessage(context, 'Bukti tidak dapat dikirim. Periksa koneksi lalu coba lagi.', error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final dues = ref.watch(duesMyProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Kirim bukti transfer')),
      body: AsyncView(
        value: dues,
        onRetry: () => ref.invalidate(duesMyProvider),
        builder: (d) {
          final tagihan = (d['tagihan'] as List).cast<Map>().map((m) => Tagihan(Json.from(m))).where((t) => t.status == 'belum_bayar').toList();
          final bisaKirim = tagihan.isNotEmpty;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Brand.sky, borderRadius: BorderRadius.circular(12)),
                child: const Text(
                  'Kirim foto transfer atau kuitansi sebagai bukti. Foto ini hanya konfirmasi; pembayaran tetap dicatat pengurus.',
                  style: TextStyle(fontSize: 13, height: 1.4),
                ),
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Foto bukti',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_bytes != null)
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.memory(
                          _bytes!,
                          height: 220,
                          fit: BoxFit.contain,
                          semanticLabel: 'Pratinjau bukti transfer',
                          errorBuilder: (_, _, _) => const SizedBox(height: 120, child: Center(child: Icon(Icons.broken_image_outlined))),
                        ),
                      )
                    else
                      const Text('Belum ada foto dipilih.', style: TextStyle(color: Brand.muted)),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        OutlinedButton.icon(
                          onPressed: () => _pick(ImageSource.camera),
                          icon: const Icon(Icons.photo_camera_outlined),
                          label: const Text('Kamera'),
                        ),
                        OutlinedButton.icon(
                          onPressed: () => _pick(ImageSource.gallery),
                          icon: const Icon(Icons.photo_library_outlined),
                          label: const Text('Galeri'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Untuk bulan apa?',
                child: bisaKirim
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          for (final t in tagihan)
                            CheckboxListTile(
                              key: ValueKey('bulan-${t.periode}'),
                              contentPadding: EdgeInsets.zero,
                              value: _selected.contains(t.periode),
                              onChanged: (v) => _centang(t, v ?? false, tagihan),
                              title: Text(periode(t.periode)),
                              subtitle: Text(rupiah(t.nominal)),
                            ),
                        ],
                      )
                    : const Text('Tidak ada tagihan yang belum dibayar, jadi bukti tidak perlu dikirim.', style: TextStyle(color: Brand.muted)),
              ),
              const SizedBox(height: 16),
              SectionCard(
                title: 'Nominal dan catatan',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextField(
                      key: const ValueKey('nominal-bukti'),
                      controller: _nominal,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: const InputDecoration(
                        labelText: 'Nominal yang ditransfer',
                        prefixText: 'Rp ',
                        helperText: 'Terisi dari bulan yang dicentang. Boleh diubah.',
                      ),
                      onChanged: (_) {
                        _nominalDiubah = true;
                      },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      key: const ValueKey('catatan-bukti'),
                      controller: _catatan,
                      maxLength: 300,
                      maxLines: 2,
                      decoration: const InputDecoration(labelText: 'Catatan (opsional)', hintText: 'mis. transfer lewat bank tanggal 5'),
                    ),
                  ],
                ),
              ),
              if (_formError != null)
                Padding(
                  padding: const EdgeInsets.only(top: 12),
                  child: Text(_formError!, style: const TextStyle(color: Brand.danger)),
                ),
              const SizedBox(height: 16),
              BusyButton(label: 'Kirim bukti', icon: Icons.send_outlined, onPressed: bisaKirim ? () => _kirim(tagihan) : null),
            ],
          );
        },
      ),
    );
  }
}
