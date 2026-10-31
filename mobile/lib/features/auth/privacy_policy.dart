import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import 'account_access.dart';
import 'account_switcher.dart';
import 'auth_pages.dart';

/// Local text versions must match the content actually displayed before consent.
const legacyPrivacyTextVersion = '2026-10-25';
const privacyTextVersion = '2026-10-26';
const panicPrivacyTextVersion = '2026-10-27';

String? privacyVersionFor(String? server) => switch (server) {
  legacyPrivacyTextVersion => legacyPrivacyTextVersion,
  privacyTextVersion => privacyTextVersion,
  panicPrivacyTextVersion => panicPrivacyTextVersion,
  _ => null,
};
const privacySections = <(String, List<String>)>[
  (
    'Siapa pengelolanya',
    [
      'Aplikasi dan website RT05 TAKEDA dikelola oleh pengurus RT 05 RW 07 Taman Kedaung (Ketua RT: Agus Ferdiansyah). Pertanyaan atau permintaan tentang data pribadi bisa disampaikan ke pengurus RT, langsung, lewat WhatsApp Ketua RT yang tercantum di halaman Kontak website, atau lewat menu Aspirasi di aplikasi.',
    ],
  ),
  (
    'Data yang kami pakai',
    [
      'Untuk masuk: nama, email, dan kata sandi (kata sandi disimpan teracak, tidak bisa dibaca siapa pun).',
      'Untuk administrasi rumah: alamat rumah (blok, jalan, nomor), anggota keluarga, dan hubungan keluarga.',
      'Opsional: nomor WhatsApp, NIK, nomor Kartu Keluarga, dan tanggal lahir anggota keluarga. Boleh dikosongkan; pengurus juga dapat membantu mengisinya sesuai data RT. Tanggal lahir dipakai pengurus untuk menghitung kelompok umur warga (misalnya balita, remaja, dan lansia).',
      'Laporan yang Anda kirim: pengaduan, aspirasi, usulan kegiatan, beserta foto atau dokumennya.',
      'Iuran: tagihan rumah Anda, pembayaran, keringanan, sumbangan atau titipan yang dicatat pengurus, dan foto bukti transfer bila Anda mengirimnya.',
      'Token perangkat untuk notifikasi, serta catatan siapa mengubah data dan kapan (untuk keamanan).',
      'Kami tidak meminta rekening bank, lokasi GPS, atau kontak di HP Anda, dan tidak ada pembayaran online.',
    ],
  ),
  (
    'Siapa yang bisa melihat',
    [
      'Alamat rumah, NIK, nomor KK, nomor WhatsApp, dan tanggal lahir hanya dapat dilihat oleh pengurus RT dan keluarga Anda sendiri. Warga lain tidak bisa melihatnya.',
      'NIK dan nomor KK disimpan terenkripsi dan di aplikasi hanya tampil tersamar (misalnya 3674••••••••0001).',
      'Warga hanya melihat tagihan, pembayaran, keringanan, tunggakan, dan titipan rumahnya sendiri. Warga dapat melihat saldo serta ringkasan kas RT per bulan tanpa nama atau kode rumah. Status iuran rumah lain, rincian transaksi, catatan, dan bukti transfer hanya terlihat oleh pengurus.',
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
      'Data tetap tersimpan selama Anda tercatat sebagai warga. Bila pindah atau meninggal, data diarsipkan dan tidak tampil lagi. 12 bulan kemudian nama, nomor WhatsApp, NIK, nomor KK, dan tanggal lahir dianonimkan otomatis. Catatan iuran dan kas tidak dihapus karena menjadi bukti keuangan RT.',
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

List<(String, List<String>)> privacyContentFor(String version) {
  if (version == panicPrivacyTextVersion) {
    return [
      for (final section in privacySections)
        (
          section.$1,
          [
            for (final item in section.$2)
              item
                  .replaceAll(
                    'Warga lain tidak bisa melihatnya.',
                    'Warga lain tidak bisa melihatnya, kecuali nama dan alamat rumah pelapor dalam laporan darurat tombol panik yang dikirim dengan konfirmasi pelapor.',
                  )
                  .replaceAll(
                    'Data tidak dijual dan tidak dibagikan ke pihak lain di luar pengurus.',
                    'Data tidak dijual. Khusus tombol panik, nama pelapor, rumah, dan jenis darurat dibagikan kepada semua warga dan pengurus aktif penerima yang telah menyetujui kebijakan ini; tidak ditampilkan di website publik.',
                  ),
          ],
        ),
      (
        'Tombol panik',
        [
          'Laporan darurat tidak anonim. Sebelum mengirim, Anda membaca ketentuan, memilih jenis darurat, lalu mengonfirmasi. Nama, alamat rumah, jenis, waktu, dan status kejadian dipakai untuk penanganan bersama. Tidak ada pengambilan lokasi GPS.',
          'Pengurus mencatat hasil penanganan dan penilaian penyalahgunaan. Setelah tiga penyalahgunaan, tombol panik akun dinonaktifkan. Riwayat tetap disimpan untuk audit; identitas mengikuti kebijakan arsip dan anonimisasi yang sama.',
          'Notifikasi memakai Firebase Cloud Messaging. Teks layar kunci tidak memuat nama atau alamat; detail hanya tersedia setelah masuk. Bunyi bergantung izin dan pengaturan perangkat. Tombol ini bukan pengganti 112, damkar, atau polisi.',
        ],
      ),
    ];
  }
  return _financePrivacyContentFor(version);
}

List<(String, List<String>)> _financePrivacyContentFor(String version) => version == legacyPrivacyTextVersion
    ? [
        for (final section in privacySections)
          (
            section.$1,
            [
              for (final item in section.$2)
                item
                    .replaceAll(
                      'Warga hanya melihat tagihan, pembayaran, keringanan, tunggakan, dan titipan rumahnya sendiri. Warga dapat melihat saldo serta ringkasan kas RT per bulan tanpa nama atau kode rumah. Status iuran rumah lain, rincian transaksi, catatan, dan bukti transfer hanya terlihat oleh pengurus.',
                      'Warga hanya melihat tagihan iuran rumahnya sendiri. Status iuran rumah lain dan kas RT hanya terlihat oleh pengurus.',
                    )
                    .replaceAll(
                      'Iuran: tagihan rumah Anda, pembayaran, keringanan, sumbangan atau titipan yang dicatat pengurus, dan foto bukti transfer bila Anda mengirimnya.',
                      'Iuran: tagihan rumah Anda, pembayaran yang dicatat pengurus, dan foto bukti transfer bila Anda mengirimnya.',
                    ),
            ],
          ),
      ]
    : privacySections;

/// Halaman Kebijakan Privasi. [consent] true: gerbang setelah masuk (setujui atau keluar); false: baca saja.
class PrivacyPolicyPage extends ConsumerStatefulWidget {
  const PrivacyPolicyPage({super.key, this.consent = false});
  final bool consent;

  @override
  ConsumerState<PrivacyPolicyPage> createState() => _PrivacyPolicyPageState();
}

class _PrivacyPolicyPageState extends ConsumerState<PrivacyPolicyPage> {
  var _checked = false;
  String? _shownVersion;
  String? _accountId;

  Future<void> _agree() async {
    final session = ref.read(sessionProvider.notifier);
    final epoch = session.epoch;
    final versi = privacyVersionFor(ref.read(sessionProvider).akun?.raw['privasi_versi'] as String?);
    if (versi == null) {
      showMessage(context, 'Perbarui aplikasi untuk membaca kebijakan terbaru.', kind: AlertKind.warning);
      return;
    }
    try {
      final data = await ref.read(apiProvider).post('/auth/privacy-consent', data: {'versi': versi, 'versi_teks': versi});
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
  Widget build(BuildContext context) {
    final server = ref.watch(sessionProvider).akun?.raw['privasi_versi'] as String?;
    final accountId = ref.watch(sessionProvider).akun?.id;
    final version = widget.consent ? privacyVersionFor(server) : privacyVersionFor(server) ?? privacyTextVersion;
    if (_accountId != accountId || _shownVersion != version) {
      _accountId = accountId;
      _shownVersion = version;
      _checked = false;
    }
    return PopScope(
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
            for (final (title, items) in privacyContentFor(version ?? privacyTextVersion))
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
            Text(switch (version ?? privacyTextVersion) {
              legacyPrivacyTextVersion => 'Versi 25 Oktober 2026.',
              panicPrivacyTextVersion => 'Versi 27 Oktober 2026.',
              _ => 'Versi 26 Oktober 2026.',
            }, style: const TextStyle(color: Brand.muted, fontSize: 13)),
            if (widget.consent && version == null) const Text('Kebijakan server lebih baru. Perbarui aplikasi sebelum menyetujui.'),
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
              BusyButton(label: 'Setuju dan lanjutkan', icon: Icons.check_circle_outline, onPressed: _checked && version != null ? _agree : null),
              const SizedBox(height: 16),
              AccountSwitcher(onAdd: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginPage(adding: true)))),
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
}
