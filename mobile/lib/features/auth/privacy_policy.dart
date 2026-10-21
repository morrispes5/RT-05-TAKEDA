import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import 'account_access.dart';

/// Isi Kebijakan Privasi aplikasi (ADR35, versi 2026-10-21). Server hanya menyimpan versi yang disetujui
/// (`privasi_versi`); naikkan versi di rest-api `App\Support\Privacy::VERSION` bila isi ini berubah.
const privacySections = <(String, List<String>)>[
  (
    'Siapa pengelolanya',
    [
      'Aplikasi dan website RT05 TAKEDA dikelola oleh pengurus RT 05 RW 07 Taman Kedaung. Pertanyaan atau permintaan tentang data pribadi bisa disampaikan ke pengurus RT, langsung atau lewat menu Aspirasi di aplikasi.',
    ],
  ),
  (
    'Data yang kami pakai',
    [
      'Untuk masuk: nama, email, dan kata sandi (kata sandi disimpan teracak, tidak bisa dibaca siapa pun).',
      'Untuk administrasi rumah: alamat rumah (blok, jalan, nomor), anggota keluarga, dan hubungan keluarga.',
      'Opsional: nomor WhatsApp, NIK, dan nomor Kartu Keluarga. Boleh dikosongkan; pengurus juga dapat membantu mengisinya sesuai data RT.',
      'Laporan yang Anda kirim: pengaduan, aspirasi, usulan kegiatan, beserta foto atau dokumennya.',
      'Iuran: tagihan rumah Anda, pembayaran yang dicatat pengurus, dan foto bukti transfer bila Anda mengirimnya.',
      'Token perangkat untuk notifikasi, serta catatan siapa mengubah data dan kapan (untuk keamanan).',
      'Kami tidak meminta rekening bank, lokasi GPS, atau kontak di HP Anda, dan tidak ada pembayaran online.',
    ],
  ),
  (
    'Siapa yang bisa melihat',
    [
      'Alamat rumah, NIK, nomor KK, dan nomor WhatsApp hanya dapat dilihat oleh pengurus RT dan keluarga Anda sendiri. Warga lain tidak bisa melihatnya.',
      'NIK dan nomor KK disimpan terenkripsi dan di aplikasi hanya tampil tersamar (misalnya 3674••••••••0001).',
      'Warga hanya melihat tagihan iuran rumahnya sendiri. Status iuran rumah lain dan kas RT hanya terlihat oleh pengurus.',
      'Laporan yang disembunyikan identitasnya tidak menampilkan nama pelapor kepada warga lain, tetapi pengurus tetap dapat melihat pelapor untuk tindak lanjut.',
      'Website publik hanya menampilkan artikel, dokumentasi, fasilitas, dan profil RT yang diterbitkan pengurus.',
    ],
  ),
  (
    'Data Anda tidak disebarkan',
    [
      'Data Anda hanya dipakai untuk keperluan layanan RT 05: administrasi warga, iuran, dan tindak lanjut laporan. Data tidak dijual dan tidak dibagikan ke pihak lain di luar pengurus.',
      'Data disimpan di layanan database dan server yang dipakai aplikasi ini; notifikasi dikirim lewat Firebase Cloud Messaging (Google).',
    ],
  ),
  (
    'Penyimpanan',
    [
      'Data tetap tersimpan selama Anda tercatat sebagai warga. Bila pindah, data diarsipkan dan tidak tampil lagi, tetapi catatan iuran dan kas tidak dihapus karena menjadi bukti keuangan RT.',
      'Log teknis disimpan 14 hari, berkas ekspor 24 jam, dan notifikasi sekitar 12 bulan.',
    ],
  ),
  (
    'Hak Anda',
    [
      'Melihat dan memperbaiki data keluarga Anda (akun utama) atau meminta pengurus memperbaikinya.',
      'Mengosongkan NIK, nomor KK, atau nomor WhatsApp kapan saja.',
      'Meminta salinan data atau penonaktifan akun kepada pengurus.',
      'Bila kebijakan ini berubah, aplikasi akan meminta persetujuan Anda lagi.',
    ],
  ),
];

/// Halaman Kebijakan Privasi. [consent] true: gerbang setelah masuk (setujui atau keluar); false: baca saja.
class PrivacyPolicyPage extends ConsumerStatefulWidget {
  const PrivacyPolicyPage({super.key, this.consent = false});
  final bool consent;

  @override
  ConsumerState<PrivacyPolicyPage> createState() => _PrivacyPolicyPageState();
}

class _PrivacyPolicyPageState extends ConsumerState<PrivacyPolicyPage> {
  var _checked = false;

  Future<void> _agree() async {
    final session = ref.read(sessionProvider.notifier);
    final epoch = session.epoch;
    final versi = ref.read(sessionProvider).akun?.raw['privasi_versi'];
    try {
      final data = await ref.read(apiProvider).post('/auth/privacy-consent', data: {'versi': versi});
      session.updateAkun(Akun(Json.from(data as Map)), epoch: epoch);
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.code == 'PRIVACY_VERSION_OUTDATED') {
        await session.restore();
        return;
      }
      showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !widget.consent,
    child: Scaffold(
      appBar: AppBar(title: const Text('Kebijakan Privasi'), automaticallyImplyLeading: !widget.consent),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (widget.consent) ...[
            Text('Sebelum mulai, baca dulu ya.', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            const Text(
              'Kami ingin Anda tahu data apa yang dipakai aplikasi RT05 TAKEDA dan siapa yang dapat melihatnya.',
              style: TextStyle(color: Brand.muted, height: 1.4),
            ),
            const SizedBox(height: 16),
          ],
          for (final (title, items) in privacySections)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: SectionCard(
                title: title,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final item in items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Padding(
                              padding: EdgeInsets.only(top: 7),
                              child: Icon(Icons.circle, size: 6, color: Brand.navy),
                            ),
                            const SizedBox(width: 10),
                            Expanded(child: Text(item, style: const TextStyle(height: 1.45))),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            ),
          const Text('Versi 21 Oktober 2026.', style: TextStyle(color: Brand.muted, fontSize: 13)),
          if (widget.consent) ...[
            const SizedBox(height: 12),
            CheckboxListTile(
              key: const ValueKey('privasi-setuju-centang'),
              value: _checked,
              onChanged: (v) => setState(() => _checked = v ?? false),
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: const Text('Saya sudah membaca dan menyetujui Kebijakan Privasi ini.'),
            ),
            const SizedBox(height: 8),
            BusyButton(label: 'Setuju dan lanjutkan', icon: Icons.check_circle_outline, onPressed: _checked ? _agree : null),
            const SizedBox(height: 8),
            TextButton(
              key: const ValueKey('privasi-keluar'),
              onPressed: () => revokeServerSession(ref, path: '/auth/logout'),
              child: const Text('Tidak setuju, keluar'),
            ),
          ],
        ],
      ),
    ),
  );
}
