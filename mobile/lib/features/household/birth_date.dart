import 'package:flutter/material.dart';

import '../../app/theme.dart';

/// ADR42: tanggal lahir anggota keluarga (opsional). Umur dihitung dari tanggal hari ini di WIB, sama dengan server.
const kelompokUsiaLabel = {'balita': 'Balita', 'anak': 'Anak-anak', 'remaja': 'Remaja', 'dewasa': 'Dewasa', 'lansia': 'Lansia', 'lansia_70': 'Lansia 70+'};

/// Rentang tiap kelompok, untuk keterangan di layar statistik.
const kelompokUsiaRentang = {'balita': '0–5 th', 'anak': '6–12 th', 'remaja': '13–17 th', 'dewasa': '18–59 th', 'lansia': '60–69 th', 'lansia_70': '≥70 th'};

const _bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

DateTime todayJakarta([DateTime? now]) {
  final t = (now ?? DateTime.now()).toUtc().add(const Duration(hours: 7));
  return DateTime(t.year, t.month, t.day);
}

/// "1985-06-15" → DateTime(1985, 6, 15); selain itu null.
DateTime? parseTanggalLahir(Object? value) {
  final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch('${value ?? ''}');
  return m == null ? null : DateTime(int.parse(m.group(1)!), int.parse(m.group(2)!), int.parse(m.group(3)!));
}

String tanggalLahirBody(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

String tanggalLahirText(DateTime d) => '${d.day} ${_bulan[d.month - 1]} ${d.year}';

int umurPada(DateTime lahir, DateTime today) {
  var umur = today.year - lahir.year;
  if (today.month < lahir.month || (today.month == lahir.month && today.day < lahir.day)) umur--;
  return umur < 0 ? 0 : umur;
}

/// Kolom tanggal lahir: ketuk untuk memilih dari kalender, tombol silang untuk mengosongkan.
class BirthDateField extends StatelessWidget {
  const BirthDateField({super.key, required this.value, required this.onChanged, this.enabled = true});

  final DateTime? value;
  final ValueChanged<DateTime?> onChanged;
  final bool enabled;

  Future<void> _pick(BuildContext context) async {
    final today = todayJakarta();
    final picked = await showDatePicker(
      context: context,
      initialDate: value ?? DateTime(today.year - 30, today.month, today.day > 28 ? 28 : today.day),
      firstDate: DateTime(1900),
      lastDate: today,
      initialEntryMode: DatePickerEntryMode.calendarOnly,
      helpText: 'Pilih tanggal lahir',
    );
    if (picked != null) onChanged(DateTime(picked.year, picked.month, picked.day));
  }

  @override
  Widget build(BuildContext context) {
    final v = value;
    return InkWell(
      key: const ValueKey('tanggal-lahir'),
      onTap: enabled ? () => _pick(context) : null,
      borderRadius: BorderRadius.circular(8),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: 'Tanggal lahir',
          helperText: 'Boleh dikosongkan. Dipakai pengurus untuk menghitung kelompok umur warga.',
          helperMaxLines: 2,
          suffixIcon: v != null && enabled
              ? IconButton(
                  key: const ValueKey('hapus-tanggal-lahir'),
                  tooltip: 'Kosongkan tanggal lahir',
                  icon: const Icon(Icons.close),
                  onPressed: () => onChanged(null),
                )
              : const Icon(Icons.calendar_today_outlined),
        ),
        child: Text(
          v == null ? 'Belum diisi' : '${tanggalLahirText(v)} (${umurPada(v, todayJakarta())} tahun)',
          style: TextStyle(color: v == null ? Brand.muted : null),
        ),
      ),
    );
  }
}
