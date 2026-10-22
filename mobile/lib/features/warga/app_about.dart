import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/config.dart';
import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/widgets.dart';

/// Rilis terbaru dari `/unduh/versi.json` di situs resmi (dibuat workflow Mobile release APK, M16).
class AppRelease {
  const AppRelease(this.versi, this.build);
  final String versi;
  final int build;

  static AppRelease? fromJson(Object? json) {
    if (json is! Map) return null;
    final versi = json['versi'];
    final build = json['build'];
    return versi is String && build is int ? AppRelease(versi, build) : null;
  }
}

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

/// M16: versi terpasang, pengelola, dan tautan ke halaman unduh resmi. Versi baru hanya diambil dari situs resmi.
class AppAboutCard extends ConsumerWidget {
  const AppAboutCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final config = ref.watch(appConfigProvider);
    final latest = ref.watch(latestReleaseProvider).value;
    final build = ref.watch(installedBuildProvider);
    final newer = latest != null && build > 0 && latest.build > build;
    final download = Uri.parse('${config.apiBaseUrl}/aplikasi');
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
              child: Text('Versi baru ${latest.versi} (build ${latest.build}) tersedia. Unduh hanya dari halaman resmi.', style: const TextStyle(height: 1.35)),
            ),
          ],
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              key: const ValueKey('buka-halaman-unduh'),
              onPressed: () async {
                final ok = await launchUrl(download, mode: LaunchMode.externalApplication);
                if (!ok && context.mounted) showMessage(context, 'Tidak dapat membuka ${download.host}/aplikasi', error: true);
              },
              icon: const Icon(Icons.download_outlined, size: 18),
              label: Text(newer ? 'Unduh versi terbaru' : 'Halaman unduh resmi'),
            ),
          ),
        ],
      ),
    );
  }
}
