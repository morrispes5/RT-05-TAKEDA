/// Aturan murni form usulan kegiatan. Server tetap pemilik status, versi, dan agenda.
Map<String, dynamic> proposalBody({
  required String nama,
  required String deskripsi,
  required String lokasi,
  required DateTime mulaiUtc,
  DateTime? selesaiUtc,
  int? versi,
}) => {
  'nama': nama.trim(),
  'deskripsi': deskripsi.trim(),
  'lokasi': lokasi.trim(),
  'mulai_at': mulaiUtc.toIso8601String(),
  if (selesaiUtc != null) 'selesai_at': selesaiUtc.toIso8601String(),
  if (versi != null) 'versi': versi,
};

/// Angka pada pemilih tanggal adalah jam dinding Asia/Jakarta, dikirim sebagai UTC.
DateTime jakartaWallToUtc(DateTime wall) => DateTime.utc(wall.year, wall.month, wall.day, wall.hour, wall.minute).subtract(const Duration(hours: 7));

bool proposalEditable(String status) => status == 'diajukan' || status == 'perlu_revisi';

bool proposalSchedulable(String status, Object? agendaId) => status == 'disetujui' && agendaId == null;

bool decisionNoteRequired(String status) => status == 'perlu_revisi' || status == 'ditolak';
