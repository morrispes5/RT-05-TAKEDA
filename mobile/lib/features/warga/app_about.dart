import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/config.dart';
import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/app_update_platform.dart';
import '../../core/widgets.dart';

/// Satu APK per ABI di `/unduh/versi.json` (dibuat workflow Mobile release APK).
class ReleaseFile {
  const ReleaseFile(this.abi, this.url, this.ukuran, this.sha256);
  final String abi;
  final String url;
  final int ukuran;
  final String sha256;
}

/// Rilis terbaru dari `/unduh/versi.json` di situs resmi (dibuat workflow Mobile release APK, M16).
class AppRelease {
  const AppRelease(this.versi, this.build, {this.catatan = const [], this.berkas = const []});
  final String versi;
  final int build;
  final List<String> catatan;
  final List<ReleaseFile> berkas;

  static final _url = RegExp(r'^/unduh/[A-Za-z0-9._-]+\.apk$');
  static final _sha = RegExp(r'^[0-9a-f]{64}$');

  static AppRelease? fromJson(Object? json) {
    if (json is! Map) return null;
    final versi = json['versi'];
    final build = json['build'];
    if (versi is! String || build is! int) return null;
    final catatan = json['catatan'];
    final berkas = json['berkas'];
    return AppRelease(
      versi,
      build,
      catatan: catatan is List
          ? [
              for (final c in catatan.take(10))
                if (c is String && c.trim().isNotEmpty) c.trim(),
            ]
          : const [],
      // ADR46: hanya berkas /unduh/*.apk di situs yang sama dengan SHA-256 lengkap; entri lain diabaikan.
      berkas: berkas is List
          ? [
              for (final f in berkas)
                if (f is Map &&
                    f['abi'] is String &&
                    f['url'] is String &&
                    _url.hasMatch(f['url'] as String) &&
                    f['ukuran'] is int &&
                    (f['ukuran'] as int) > 0 &&
                    f['sha256'] is String &&
                    _sha.hasMatch(f['sha256'] as String))
                  ReleaseFile(f['abi'] as String, f['url'] as String, f['ukuran'] as int, f['sha256'] as String),
            ]
          : const [],
    );
  }

  /// APK untuk HP ini: ABI pertama yang didukung HP (urutan Android) dan tersedia di rilis.
  ReleaseFile? fileFor(List<String> abis) {
    for (final abi in abis) {
      for (final f in berkas) {
        if (f.abi == abi) return f;
      }
    }
    return null;
  }
}

/// Build lokal/debug (0) tidak pernah disarankan memperbarui.
bool isNewerRelease(AppRelease? latest, int installed) => latest != null && installed > 0 && latest.build > installed;

/// Nomor build APK terpasang (0 = build lokal/debug tanpa nomor). Provider agar tes dapat menggantinya.
final installedBuildProvider = Provider<int>((ref) => AppConfig.buildNumber);

/// Gagal mengambil (offline, berkas belum ada) = tidak ada info rilis; tidak pernah menampilkan error.
final latestReleaseProvider = FutureProvider.autoDispose<AppRelease?>((ref) async {
  try {
    return AppRelease.fromJson(await ref.read(apiProvider).publicJson('/unduh/versi.json'));
  } on Object {
    return null;
  }
});

typedef ApkDownloader = Future<void> Function(String url, String savePath, void Function(int received, int total) onProgress, CancelToken cancel);

final apkDownloaderProvider = Provider<ApkDownloader>(
  (ref) =>
      (url, savePath, onProgress, cancel) => ref.read(apiProvider).downloadPublic(url, savePath, onProgress: onProgress, cancel: cancel),
);

/// Pop-up versi baru cukup sekali setiap aplikasi dibuka; tombol di kartu Tentang aplikasi tetap tersedia.
class UpdateOffer {
  bool shown = false;
}

final updateOfferProvider = Provider<UpdateOffer>((ref) => UpdateOffer());

Future<void> openDownloadPage(BuildContext context, WidgetRef ref) async {
  final download = Uri.parse('${ref.read(appConfigProvider).apiBaseUrl}/aplikasi');
  final ok = await launchUrl(download, mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) showMessage(context, 'Tidak dapat membuka ${download.host}/aplikasi', error: true);
}

Future<void> showAppUpdateDialog(BuildContext context, AppRelease release) => showDialog<void>(
  context: context,
  barrierDismissible: false,
  builder: (_) => AppUpdateDialog(release: release),
);

enum _Tahap { siap, unduh, izin, pasang, gagal }

/// ADR46: unduh APK dari situs resmi, Android mencocokkan SHA-256 dan versi, lalu layar Install Android terbuka.
class AppUpdateDialog extends ConsumerStatefulWidget {
  const AppUpdateDialog({super.key, required this.release});
  final AppRelease release;
  @override
  ConsumerState<AppUpdateDialog> createState() => _AppUpdateDialogState();
}

class _AppUpdateDialogState extends ConsumerState<AppUpdateDialog> {
  _Tahap _tahap = _Tahap.siap;
  int _diterima = 0;
  int _total = 0;
  String _pesan = '';
  ReleaseFile? _file;
  String? _path;
  CancelToken? _cancel;
  StreamSubscription<String>? _status;

  @override
  void initState() {
    super.initState();
    _status = ref.read(appUpdatePlatformProvider).statuses.listen(_onStatus);
  }

  @override
  void dispose() {
    _cancel?.cancel();
    _status?.cancel();
    super.dispose();
  }

  void _gagal(String pesan) {
    if (!mounted) return;
    setState(() {
      _tahap = _Tahap.gagal;
      _pesan = pesan;
    });
  }

  // SUCCESS: Android menutup aplikasi lama dan memasang versi baru; tidak ada yang perlu ditampilkan.
  void _onStatus(String status) {
    if (status == 'SUCCESS') return;
    _gagal(switch (status) {
      'ABORTED' => 'Pemasangan dibatalkan. Tekan Coba lagi bila ingin memperbarui sekarang.',
      'STORAGE' => 'Ruang penyimpanan HP tidak cukup. Kosongkan ruang lalu tekan Coba lagi.',
      'CONFLICT' => 'Android menolak pembaruan karena tidak cocok dengan aplikasi terpasang. Pasang dari halaman unduh resmi.',
      _ => 'Pemasangan gagal. Tekan Coba lagi atau pasang dari halaman unduh resmi.',
    });
  }

  Future<void> _unduh() async {
    final platform = ref.read(appUpdatePlatformProvider);
    final file = widget.release.fileFor(await platform.abis());
    if (file == null) {
      _gagal('Belum ada APK untuk jenis HP ini. Pasang dari halaman unduh resmi.');
      return;
    }
    final path = '${await platform.directory()}/${file.url.split('/').last}';
    final cancel = CancelToken();
    setState(() {
      _tahap = _Tahap.unduh;
      _file = file;
      _path = null;
      _cancel = cancel;
      _diterima = 0;
      _total = file.ukuran;
    });
    try {
      await ref.read(apkDownloaderProvider)(file.url, path, (r, t) {
        if (!mounted) return;
        setState(() {
          _diterima = r;
          _total = t > 0 ? t : file.ukuran;
        });
      }, cancel);
    } on Object {
      if (cancel.isCancelled) {
        if (mounted) setState(() => _tahap = _Tahap.siap);
        return;
      }
      _gagal('Unduhan gagal. Periksa koneksi internet lalu tekan Coba lagi.');
      return;
    }
    _path = path;
    await _pasang();
  }

  Future<void> _pasang() async {
    final (file, path) = (_file, _path);
    if (file == null || path == null) return _unduh();
    try {
      final start = await ref.read(appUpdatePlatformProvider).install(path, file.sha256);
      if (mounted) setState(() => _tahap = start == InstallStart.needsPermission ? _Tahap.izin : _Tahap.pasang);
    } on PlatformException catch (e) {
      // HASH/INVALID: berkas sudah dihapus Android; Coba lagi mengunduh ulang.
      if (e.code == 'HASH' || e.code == 'INVALID') _path = null;
      _gagal(switch (e.code) {
        'HASH' => 'Berkas unduhan tidak sama dengan versi resmi, jadi tidak dipasang dan sudah dihapus. Tekan Coba lagi.',
        'INVALID' => 'Berkas ini bukan versi yang lebih baru dari aplikasi di HP, jadi tidak dipasang.',
        _ => 'Pemasangan gagal dimulai. Tekan Coba lagi atau pasang dari halaman unduh resmi.',
      });
    }
  }

  String _mb(int bytes) => (bytes / (1024 * 1024)).toStringAsFixed(1).replaceAll('.', ',');

  @override
  Widget build(BuildContext context) {
    final r = widget.release;
    final close = TextButton(
      key: const ValueKey('pembaruan-nanti'),
      onPressed: () => Navigator.pop(context),
      child: Text(_tahap == _Tahap.pasang ? 'Tutup' : 'Nanti'),
    );
    final body = <Widget>[
      Text('Versi ${r.versi} (build ${r.build})', style: const TextStyle(fontWeight: FontWeight.w700)),
      const SizedBox(height: 8),
      ...switch (_tahap) {
        _Tahap.siap => [
          if (r.catatan.isNotEmpty) ...[
            const Text('Yang baru:', style: TextStyle(fontWeight: FontWeight.w600)),
            for (final c in r.catatan)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text('• $c', style: const TextStyle(height: 1.35)),
              ),
            const SizedBox(height: 12),
          ],
          const Text('Data dan akunmu tetap aman. Android akan meminta konfirmasi Install.', style: TextStyle(color: Brand.muted, height: 1.35)),
        ],
        _Tahap.unduh => [
          LinearProgressIndicator(key: const ValueKey('pembaruan-progres'), value: _total > 0 ? (_diterima / _total).clamp(0, 1).toDouble() : null),
          const SizedBox(height: 8),
          Text(_total > 0 ? 'Mengunduh ${_mb(_diterima)} dari ${_mb(_total)} MB' : 'Mengunduh…'),
        ],
        _Tahap.izin => [
          const Text(
            'Sekali saja: izinkan RT05 TAKEDA memasang pembaruan. Tekan Buka pengaturan, aktifkan "Izinkan dari sumber ini", '
            'kembali ke aplikasi, lalu tekan Pasang.',
            style: TextStyle(height: 1.4),
          ),
        ],
        _Tahap.pasang => [
          const Text(
            'Ikuti layar pemasangan Android lalu tekan Install/Perbarui. Aplikasi akan tertutup dan versi baru siap dibuka.',
            style: TextStyle(height: 1.4),
          ),
        ],
        _Tahap.gagal => [
          Text(
            _pesan,
            key: const ValueKey('pembaruan-gagal'),
            style: const TextStyle(color: Brand.danger, height: 1.4),
          ),
        ],
      },
    ];
    final actions = switch (_tahap) {
      _Tahap.siap => [close, FilledButton(key: const ValueKey('pembaruan-mulai'), onPressed: _unduh, child: const Text('Unduh dan pasang'))],
      _Tahap.unduh => [TextButton(key: const ValueKey('pembaruan-batal'), onPressed: () => _cancel?.cancel(), child: const Text('Batal'))],
      _Tahap.izin => [
        close,
        OutlinedButton(onPressed: () => ref.read(appUpdatePlatformProvider).openInstallPermission(), child: const Text('Buka pengaturan')),
        FilledButton(key: const ValueKey('pembaruan-pasang'), onPressed: _pasang, child: const Text('Pasang')),
      ],
      _Tahap.pasang => [close],
      _Tahap.gagal => [
        TextButton(onPressed: () => openDownloadPage(context, ref), child: const Text('Halaman unduh resmi')),
        close,
        FilledButton(key: const ValueKey('pembaruan-ulang'), onPressed: _pasang, child: const Text('Coba lagi')),
      ],
    };
    return PopScope(
      canPop: _tahap != _Tahap.unduh,
      child: AlertDialog(
        icon: const Icon(Icons.system_update_outlined, color: Brand.navy, size: 36),
        title: const Text('Versi baru tersedia'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: body),
        ),
        actionsOverflowButtonSpacing: 4,
        actions: actions,
      ),
    );
  }
}

/// M16: versi terpasang, pengelola, dan tautan ke halaman unduh resmi. Versi baru hanya diambil dari situs resmi.
/// ADR46: di Android tombol Perbarui mengunduh dan memasang langsung dari aplikasi.
class AppAboutCard extends ConsumerWidget {
  const AppAboutCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final latest = ref.watch(latestReleaseProvider).value;
    final build = ref.watch(installedBuildProvider);
    final newer = isNewerRelease(latest, build);
    final inApp = newer && ref.watch(appUpdatePlatformProvider).supported && latest!.berkas.isNotEmpty;
    return SectionCard(
      title: 'Tentang aplikasi',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            build > 0 ? 'Versi ${AppConfig.appVersion} (build $build)' : 'Versi ${AppConfig.appVersion}',
            key: const ValueKey('versi-aplikasi'),
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          const Text('Dikelola pengurus RT 05 RW 07 Taman Kedaung.', style: TextStyle(color: Brand.muted, height: 1.35)),
          if (newer) ...[
            const SizedBox(height: 12),
            Container(
              key: const ValueKey('versi-baru'),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Brand.sky, borderRadius: BorderRadius.circular(10)),
              child: Text(
                inApp
                    ? 'Versi baru ${latest.versi} (build ${latest.build}) tersedia. Perbarui langsung dari aplikasi.'
                    : 'Versi baru ${latest!.versi} (build ${latest.build}) tersedia. Unduh hanya dari halaman resmi.',
                style: const TextStyle(height: 1.35),
              ),
            ),
          ],
          if (inApp) ...[
            const SizedBox(height: 12),
            FilledButton.icon(
              key: const ValueKey('perbarui-aplikasi'),
              onPressed: () => showAppUpdateDialog(context, latest),
              icon: const Icon(Icons.system_update_outlined, size: 20),
              label: const Text('Perbarui sekarang'),
            ),
          ],
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              key: const ValueKey('buka-halaman-unduh'),
              onPressed: () => openDownloadPage(context, ref),
              icon: const Icon(Icons.download_outlined, size: 18),
              label: Text(newer && !inApp ? 'Unduh versi terbaru' : 'Halaman unduh resmi'),
            ),
          ),
        ],
      ),
    );
  }
}
