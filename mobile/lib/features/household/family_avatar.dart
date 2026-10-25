import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../app/theme.dart';

/// M17 (permintaan pemilik 25 Okt 2026): foto profil keluarga dipilih dari 15 avatar bawaan, bukan foto galeri. Kode sama
/// dengan `FamilyAvatars::CODES` di API dan berkas `assets/avatars/<kode>.svg` (dibuat `tool/avatars.py`).
const familyAvatars = <String, String>{
  'ayah': 'Ayah',
  'ibu': 'Ibu',
  'ayah_peci': 'Ayah berpeci',
  'ibu_hijab': 'Ibu berhijab',
  'kakek': 'Kakek',
  'nenek': 'Nenek',
  'nenek_hijab': 'Nenek berhijab',
  'kakak_laki': 'Kakak laki-laki',
  'kakak_perempuan': 'Kakak perempuan',
  'remaja_hijab': 'Kakak berhijab',
  'adik_laki': 'Adik laki-laki',
  'adik_perempuan': 'Adik perempuan',
  'bayi': 'Bayi',
  'pasangan': 'Ayah dan ibu',
  'keluarga': 'Keluarga',
};

/// Avatar bulat; kode kosong/tidak dikenal menampilkan ikon rumah.
class FamilyAvatar extends StatelessWidget {
  const FamilyAvatar({super.key, required this.code, this.size = 64});

  final String? code;
  final double size;

  @override
  Widget build(BuildContext context) {
    final label = familyAvatars[code];
    if (label == null) {
      return Semantics(
        label: 'Belum ada foto profil keluarga',
        child: Container(
          width: size,
          height: size,
          decoration: const BoxDecoration(color: Brand.skyDeep, shape: BoxShape.circle),
          child: Icon(Icons.house_outlined, color: Brand.navy, size: size * .5),
        ),
      );
    }
    return Semantics(
      label: 'Foto profil keluarga: $label',
      child: SvgPicture.asset('assets/avatars/$code.svg', width: size, height: size, excludeFromSemantics: true),
    );
  }
}

/// Lembar pilihan 15 avatar. Mengembalikan kode terpilih, '' untuk menghapus, atau null bila ditutup.
Future<String?> pickFamilyAvatar(BuildContext context, {String? current}) => showModalBottomSheet<String>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (c) => SafeArea(
    child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Pilih foto profil keluarga', style: Theme.of(c).textTheme.titleMedium),
          const SizedBox(height: 4),
          const Text('Tampil di menu Akun keluarga Anda. Bukan foto asli, jadi aman untuk privasi.', style: TextStyle(color: Brand.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 12,
            alignment: WrapAlignment.center,
            children: [
              for (final e in familyAvatars.entries)
                InkWell(
                  key: ValueKey('avatar-${e.key}'),
                  borderRadius: BorderRadius.circular(16),
                  onTap: () => Navigator.pop(c, e.key),
                  child: Container(
                    width: 92,
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: e.key == current ? Brand.navy : Colors.transparent, width: 2.5),
                      color: e.key == current ? Brand.sky : null,
                    ),
                    child: Column(
                      children: [
                        FamilyAvatar(code: e.key, size: 64),
                        const SizedBox(height: 4),
                        Text(e.value, textAlign: TextAlign.center, style: const TextStyle(fontSize: 12, height: 1.2)),
                      ],
                    ),
                  ),
                ),
            ],
          ),
          if (current != null) ...[const SizedBox(height: 12), TextButton(onPressed: () => Navigator.pop(c, ''), child: const Text('Hapus foto profil'))],
        ],
      ),
    ),
  ),
);
