import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../app/theme.dart';
import '../../core/widgets.dart';

/// ADR40: Air Bersih KSM (tagihan air KSM Banyu Bening) masih aplikasi web terpisah dengan akun sendiri.
/// Sementara pengurus hanya diberi tautan; kelak fitur itu digabung ke RT05 dan berkas ini diganti layar asli.
final airBersihKsmUri = Uri.parse('https://airbersih-ksm-claude.vercel.app');

typedef UrlOpener = Future<bool> Function(Uri uri);

Future<bool> _openExternal(Uri uri) => launchUrl(uri, mode: LaunchMode.externalApplication);

/// Baris menu Lainnya pengurus: membuka Air Bersih KSM di browser.
class AirBersihKsmTile extends StatelessWidget {
  const AirBersihKsmTile({super.key, this.open = _openExternal});
  final UrlOpener open;

  @override
  Widget build(BuildContext context) => ListTile(
    key: const ValueKey('air-bersih-ksm'),
    contentPadding: EdgeInsets.zero,
    leading: const Icon(Icons.water_drop_outlined, color: Brand.navy),
    title: const Text('Tagihan air KSM'),
    subtitle: const Text('Catat meter, tagihan, dan pembayaran air KSM Banyu Bening. Dibuka di browser dengan akun air bersih'),
    trailing: const Icon(Icons.open_in_new, size: 18, color: Brand.muted),
    onTap: () async {
      var ok = false;
      try {
        ok = await open(airBersihKsmUri);
      } catch (_) {
        ok = false;
      }
      if (!ok && context.mounted) showMessage(context, 'Tidak dapat membuka ${airBersihKsmUri.host}', error: true);
    },
  );
}
