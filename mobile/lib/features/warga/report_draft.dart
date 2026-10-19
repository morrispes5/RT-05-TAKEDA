import 'dart:convert';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:image_picker/image_picker.dart';

/// M15: draf pengaduan baru yang disimpan tepat sebelum kamera dibuka. Android dapat menutup
/// aplikasi saat kamera aktif (RAM rendah); saat aplikasi dibuka lagi, isian dan foto (lewat
/// `ImagePicker.retrieveLostData`) dipulihkan. Draf terenkripsi di perangkat, terikat akun, tidak
/// pernah dikirim ke server sebelum pengguna menekan Kirim.
class ReportDraft {
  const ReportDraft({required this.akunId, this.kategori, this.judul = '', this.isi = '', this.anonim = false, this.photos = const [], this.kamera = false});

  final String akunId;
  final String? kategori;
  final String judul;
  final String isi;
  final bool anonim;

  /// Path foto yang sudah dipilih sebelum kamera dibuka (cache aplikasi).
  final List<String> photos;

  /// Kamera sedang dibuka saat draf ditulis; foto hasil kamera mungkin tertinggal di plugin.
  final bool kamera;

  bool get empty => kategori == null && judul.trim().isEmpty && isi.trim().isEmpty && photos.isEmpty;

  Map<String, Object?> toJson() => {
    'v': 1,
    'akun': akunId,
    'kategori': kategori,
    'judul': judul,
    'isi': isi,
    'anonim': anonim,
    'foto': photos,
    'kamera': kamera,
  };

  static ReportDraft? fromJson(Object? raw) {
    if (raw is! Map || raw['v'] != 1 || raw['akun'] is! String) return null;
    return ReportDraft(
      akunId: raw['akun'] as String,
      kategori: raw['kategori'] as String?,
      judul: '${raw['judul'] ?? ''}',
      isi: '${raw['isi'] ?? ''}',
      anonim: raw['anonim'] == true,
      photos: [for (final p in (raw['foto'] as List? ?? const [])) '$p'],
      kamera: raw['kamera'] == true,
    );
  }
}

abstract interface class ReportDraftStore {
  Future<ReportDraft?> read();
  Future<void> write(ReportDraft draft);
  Future<void> clear();
}

/// Kegagalan storage tidak pernah mengganggu formulir: draf hanya jaring pengaman.
class SecureReportDraftStore implements ReportDraftStore {
  const SecureReportDraftStore();
  static const _key = 'rt05_report_draft_v1';
  static const _storage = FlutterSecureStorage();

  @override
  Future<ReportDraft?> read() async {
    try {
      final text = await _storage.read(key: _key);
      return text == null ? null : ReportDraft.fromJson(jsonDecode(text));
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> write(ReportDraft draft) async {
    try {
      await _storage.write(key: _key, value: jsonEncode(draft.toJson()));
    } catch (_) {}
  }

  @override
  Future<void> clear() async {
    try {
      await _storage.delete(key: _key);
    } catch (_) {}
  }
}

final reportDraftStoreProvider = Provider<ReportDraftStore>((ref) => const SecureReportDraftStore());

/// Foto kamera yang tertinggal ketika Android menutup aplikasi (hanya Android; platform lain kosong).
typedef LostPhotos = Future<List<XFile>> Function(ImagePicker picker);

Future<List<XFile>> retrieveLostPhotos(ImagePicker picker) async {
  if (!Platform.isAndroid) return const [];
  try {
    final lost = await picker.retrieveLostData();
    if (lost.isEmpty) return const [];
    return lost.files ?? (lost.file == null ? const [] : [lost.file!]);
  } catch (_) {
    return const [];
  }
}

final lostPhotosProvider = Provider<LostPhotos>((ref) => retrieveLostPhotos);

/// Draf milik akun yang sedang masuk (draf akun lain di HP yang sama dihapus, tidak ditampilkan).
Future<ReportDraft?> pendingDraftFor(ReportDraftStore store, String? akunId) async {
  final draft = await store.read();
  if (draft == null) return null;
  if (akunId == null || draft.akunId != akunId || (draft.empty && !draft.kamera)) {
    if (akunId != null) await store.clear();
    return null;
  }
  return draft;
}

/// Kartu "lanjutkan pengaduan" di beranda warga setelah aplikasi ditutup Android.
final pendingReportDraftProvider = FutureProvider.autoDispose.family<ReportDraft?, String?>(
  (ref, akunId) => ref.read(reportDraftStoreProvider).read().then((d) => d != null && d.akunId == akunId && (!d.empty || d.kamera) ? d : null),
);
