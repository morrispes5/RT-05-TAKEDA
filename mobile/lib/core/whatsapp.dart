import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import 'widgets.dart';

/// Hijau khas WhatsApp untuk penanda tombol chat (ADR35). Tautan memakai wa.me, bukan WhatsApp Business API.
const whatsappGreen = Color(0xFF25D366);

/// Nomor Indonesia → digit internasional tanpa '+', format wa.me: 0812… / +62 812… / 812… → 62812….
/// null bila kosong atau terlalu pendek untuk nomor telepon.
String? waNumber(String? phone) {
  var digits = (phone ?? '').replaceAll(RegExp(r'\D'), '');
  if (digits.startsWith('0')) {
    digits = '62${digits.substring(1)}';
  } else if (digits.startsWith('8')) {
    digits = '62$digits';
  }
  return digits.length < 9 ? null : digits;
}

Uri waUri(String number, {String? text}) =>
    Uri(scheme: 'https', host: 'wa.me', path: '/$number', queryParameters: text == null || text.isEmpty ? null : {'text': text});

/// Membuka chat WhatsApp ke [phone] di aplikasi WhatsApp (atau browser bila tidak terpasang).
Future<void> openWhatsApp(BuildContext context, String? phone, {String? text}) async {
  final number = waNumber(phone);
  if (number == null) {
    showMessage(context, 'Nomor WhatsApp belum diisi.', error: true);
    return;
  }
  final ok = await launchUrl(waUri(number, text: text), mode: LaunchMode.externalApplication);
  if (!ok && context.mounted) showMessage(context, 'WhatsApp tidak dapat dibuka di perangkat ini.', error: true);
}

/// Lingkaran hijau bergambar gelembung chat: penanda tombol WhatsApp.
class WhatsAppMark extends StatelessWidget {
  const WhatsAppMark({super.key, this.size = 22});
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: const BoxDecoration(color: whatsappGreen, shape: BoxShape.circle),
    alignment: Alignment.center,
    child: Icon(Icons.phone_in_talk_rounded, color: Colors.white, size: size * 0.6),
  );
}

/// Tombol ikon kecil "Chat WhatsApp" di samping nomor warga. Tidak tampil bila nomor kosong.
class WhatsAppIconButton extends StatelessWidget {
  const WhatsAppIconButton({super.key, required this.phone, required this.name, this.text});
  final String? phone;
  final String name;
  final String? text;

  @override
  Widget build(BuildContext context) {
    if (waNumber(phone) == null) return const SizedBox.shrink();
    return IconButton(
      tooltip: 'Chat WhatsApp $name',
      onPressed: () => openWhatsApp(context, phone, text: text),
      icon: const WhatsAppMark(),
    );
  }
}

/// Tombol lebar "Chat … lewat WhatsApp" (mis. ke bendahara).
class WhatsAppWideButton extends StatelessWidget {
  const WhatsAppWideButton({super.key, required this.label, required this.phone, this.text});
  final String label;
  final String? phone;
  final String? text;

  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
    onPressed: waNumber(phone) == null ? null : () => openWhatsApp(context, phone, text: text),
    icon: const WhatsAppMark(size: 20),
    label: Text(label, textAlign: TextAlign.center),
    style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF128C4A), minimumSize: const Size(0, 48)),
  );
}
