import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../app/theme.dart';
import '../../core/format.dart';
import '../../core/models.dart';

/// Sentuhan kecil di atas tampilan yang sudah ada (permintaan pemilik 16 Nov 2026): salam sesuai waktu, hiasan kartu
/// salam, ucapan terima kasih saat iuran lunas, dan ilustrasi untuk keadaan kosong. Tata letak, warna, dan komponen
/// tidak berubah; semua gambar hanya hiasan (dikecualikan dari pembaca layar) di `assets/ilustrasi/`.
const _dir = 'assets/ilustrasi';

/// Salam menurut jam WIB (0–23).
String salamWaktu(int jam) => switch (jam) {
  >= 4 && < 11 => 'Selamat pagi,',
  >= 11 && < 15 => 'Selamat siang,',
  >= 15 && < 18 => 'Selamat sore,',
  _ => 'Selamat malam,',
};

bool waktuMalam(int jam) => jam >= 18 || jam < 4;

int jamWib() => toJakarta(DateTime.now()).hour;

/// Hiasan samar di latar kartu salam: matahari dan atap rumah (pagi–sore), bulan dan jendela menyala (malam).
class HeroDecor extends StatelessWidget {
  const HeroDecor({super.key, required this.malam});
  final bool malam;

  @override
  Widget build(BuildContext context) =>
      SvgPicture.asset('$_dir/${malam ? 'hero_malam' : 'hero_pagi'}.svg', fit: BoxFit.cover, alignment: Alignment.bottomRight, excludeFromSemantics: true);
}

/// Enam ucapan terima kasih; tiap periode mendapat ucapan berbeda secara berurutan.
const ucapanTerimaKasih = <({String gambar, String judul, String isi})>[
  (gambar: 'terima_kasih_keluarga', judul: 'Terima kasih!', isi: 'Iuranmu ikut menjaga lingkungan kita.'),
  (gambar: 'terima_kasih_lampu', judul: 'Makasih ya!', isi: 'Lampu jalan kita tetap menyala.'),
  (gambar: 'terima_kasih_kerja_bakti', judul: 'Mantap!', isi: 'Kerja bakti bulan ini makin ringan.'),
  (gambar: 'terima_kasih_keamanan', judul: 'Terima kasih!', isi: 'Lingkungan kita makin aman.'),
  (gambar: 'terima_kasih_gapura', judul: 'Terima kasih!', isi: 'Kamu ikut merawat RT kita.'),
  (gambar: 'terima_kasih_hati', judul: 'Lunas!', isi: 'Terima kasih banyak atas iuranmu.'),
];

/// Ucapan untuk periode "YYYY-MM"; periode tak terbaca memakai ucapan pertama.
({String gambar, String judul, String isi}) ucapanUntuk(String periode) {
  final parts = periode.split('-');
  final tahun = parts.isNotEmpty ? int.tryParse(parts[0]) : null;
  final bulan = parts.length > 1 ? int.tryParse(parts[1]) : null;
  if (tahun == null || bulan == null) return ucapanTerimaKasih.first;
  return ucapanTerimaKasih[(tahun * 12 + bulan) % ucapanTerimaKasih.length];
}

/// Ucapan hanya untuk tagihan yang benar-benar dibayar: bulan yang diputihkan/diringankan penuh berstatus lunas
/// tetapi tidak ada uang yang diterima, jadi tidak diberi ucapan "terima kasih sudah membayar".
bool layakUcapan(Tagihan t) => t.lunas && t.terbayar > 0;

/// Strip ucapan terima kasih. [rinci] menambah baris periode dan nominal yang dibayar (tab Iuran).
class ThanksStrip extends StatelessWidget {
  const ThanksStrip({super.key, required this.tagihan, this.rinci = false});
  final Tagihan tagihan;
  final bool rinci;

  @override
  Widget build(BuildContext context) {
    final u = ucapanUntuk(tagihan.periode);
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: Brand.sky, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          SvgPicture.asset('$_dir/${u.gambar}.svg', width: 84, excludeFromSemantics: true),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(u.judul, style: const TextStyle(fontWeight: FontWeight.w700)),
                Text(u.isi, style: const TextStyle(height: 1.35)),
                if (rinci) ...[
                  const SizedBox(height: 6),
                  Text(
                    'Iuran ${periode(tagihan.periode)} lunas • ${rupiah(tagihan.terbayar)}',
                    style: const TextStyle(color: Brand.success, fontWeight: FontWeight.w700, fontSize: 13),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Keadaan kosong di dalam kartu: teks yang sama seperti sebelumnya, ditemani ilustrasi kecil.
class IllustratedEmpty extends StatelessWidget {
  const IllustratedEmpty({super.key, required this.gambar, required this.teks});
  final String gambar;
  final String teks;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      SvgPicture.asset('$_dir/$gambar.svg', width: 64, excludeFromSemantics: true),
      const SizedBox(width: 12),
      Expanded(child: Text(teks)),
    ],
  );
}
