import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/widgets.dart';

/// ADR41: Air Bersih KSM (tagihan air KSM Banyu Bening) aplikasi web terpisah dengan login sendiri.
/// Menu ini hanya ada di Lainnya pengurus (warga tidak punya tab Lainnya); mengetuk mengalihkan ke aplikasi itu.
final airBersihKsmUri = Uri.parse('https://airbersih-ksm-claude.vercel.app');

/// Logo KSM Banyu Bening, salinan byte-identik website/public/images/logo/air-bersih-ksm.png.
const airBersihKsmLogo = 'assets/brand/air-bersih-ksm.png';

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
    leading: const CircleAvatar(radius: 22, backgroundColor: Colors.white, backgroundImage: AssetImage(airBersihKsmLogo)),
    title: const Text('Air Bersih KSM'),
    subtitle: const Text('Catat meter, tagihan, dan pembayaran air KSM Banyu Bening. Masuk dengan akun air bersih'),
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
