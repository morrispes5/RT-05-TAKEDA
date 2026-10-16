/// Aturan murni form usulan kegiatan (docs/USULAN_KEGIATAN.md §3). Server tetap pemilik validasi,
/// status, versi, dan agenda; fungsi ini membentuk body dan pesan supaya warga tahu salahnya sebelum mengirim.
library;

const proposalJenis = <String, String>{
  'olahraga': 'Olahraga',
  'kerja_bakti': 'Kerja bakti',
  'keagamaan': 'Keagamaan',
  'sosial': 'Sosial / perayaan',
  'rapat': 'Rapat / musyawarah',
  'lainnya': 'Lainnya',
};

const proposalMaxDocuments = 3;
const proposalMaxBytes = 8 * 1024 * 1024;
const proposalMaxTextBytes = 1024 * 1024;
const proposalStatement = 'Saya penanggung jawab kegiatan ini dan akan menjaga kebersihan, ketertiban, serta keamanan lingkungan.';

/// Isian form; waktu dalam jam dinding Asia/Jakarta.
class ProposalDraft {
  ProposalDraft({
    required this.jenis,
    required this.nama,
    required this.deskripsi,
    required this.lokasi,
    required this.mulai,
    required this.selesai,
    this.peserta = '',
    this.kebutuhan = '',
    this.pernyataan = false,
  });

  final String? jenis;
  final String nama;
  final String deskripsi;
  final String lokasi;
  final DateTime? mulai;
  final DateTime? selesai;
  final String peserta;
  final String kebutuhan;
  final bool pernyataan;
}

/// Pesan per field; kosong berarti boleh dikirim. [nowJakarta] = jam dinding Jakarta sekarang.
Map<String, String> validateProposal(ProposalDraft d, DateTime nowJakarta) {
  final e = <String, String>{};
  if (d.jenis == null || !proposalJenis.containsKey(d.jenis)) e['jenis'] = 'Pilih jenis kegiatan.';
  final nama = d.nama.trim();
  if (nama.length < 5) e['nama'] = 'Nama kegiatan minimal 5 karakter.';
  if (nama.length > 160) e['nama'] = 'Nama kegiatan maksimal 160 karakter.';
  if (d.lokasi.trim().length < 3) e['lokasi'] = 'Isi lokasi kegiatan.';
  if (d.deskripsi.trim().length < 20) e['deskripsi'] = 'Jelaskan kegiatan dan susunan acara minimal 20 karakter.';
  if (d.mulai == null) {
    e['mulai_at'] = 'Pilih tanggal dan jam mulai.';
  } else if (d.mulai!.isBefore(nowJakarta.add(const Duration(hours: 24)))) {
    e['mulai_at'] = 'Kegiatan diajukan paling cepat 24 jam sebelum mulai.';
  } else if (d.mulai!.isAfter(nowJakarta.add(const Duration(days: 365)))) {
    e['mulai_at'] = 'Tanggal kegiatan paling lambat satu tahun dari sekarang.';
  }
  if (d.selesai == null) {
    e['selesai_at'] = 'Pilih jam selesai.';
  } else if (d.mulai != null && !d.selesai!.isAfter(d.mulai!)) {
    e['selesai_at'] = 'Jam selesai harus setelah jam mulai.';
  } else if (d.mulai != null && d.selesai!.difference(d.mulai!) > const Duration(hours: 12)) {
    e['selesai_at'] = 'Kegiatan paling lama 12 jam.';
  }
  final peserta = d.peserta.trim();
  if (peserta.isNotEmpty) {
    final n = int.tryParse(peserta);
    if (n == null || n < 1 || n > 1000) e['perkiraan_peserta'] = 'Isi angka 1–1000 atau kosongkan.';
  }
  if (d.kebutuhan.trim().length > 1000) e['kebutuhan'] = 'Kebutuhan maksimal 1000 karakter.';
  if (!d.pernyataan) e['pernyataan'] = 'Centang pernyataan penanggung jawab.';
  return e;
}

/// Angka pada pemilih tanggal adalah jam dinding Asia/Jakarta, dikirim sebagai UTC.
DateTime jakartaWallToUtc(DateTime wall) => DateTime.utc(wall.year, wall.month, wall.day, wall.hour, wall.minute).subtract(const Duration(hours: 7));

Map<String, dynamic> proposalBody(ProposalDraft d, {int? versi}) => {
  'jenis': d.jenis,
  'nama': d.nama.trim(),
  'deskripsi': d.deskripsi.trim(),
  'lokasi': d.lokasi.trim(),
  'mulai_at': jakartaWallToUtc(d.mulai!).toIso8601String(),
  'selesai_at': jakartaWallToUtc(d.selesai!).toIso8601String(),
  if (d.peserta.trim().isNotEmpty) 'perkiraan_peserta': int.parse(d.peserta.trim()),
  if (d.kebutuhan.trim().isNotEmpty) 'kebutuhan': d.kebutuhan.trim(),
  'pernyataan': true,
  'versi': ?versi,
};

/// Jenis lampiran dari nama berkas; server tetap memeriksa isi berkas.
String? documentKind(String filename) => switch (filename.split('.').last.toLowerCase()) {
  'pdf' => 'pdf',
  'txt' => 'txt',
  'jpg' || 'jpeg' || 'png' || 'webp' => 'image',
  _ => null,
};

/// Pesan bila lampiran tidak dapat ditambahkan; null berarti boleh.
String? documentProblem(String filename, int bytes, int alreadyAttached) {
  if (alreadyAttached >= proposalMaxDocuments) return 'Maksimal $proposalMaxDocuments dokumen pendukung.';
  final kind = documentKind(filename);
  if (kind == null) return 'Hanya PDF, TXT, JPG, PNG, atau WebP. Simpan dokumen Word sebagai PDF terlebih dahulu.';
  if (kind == 'txt' && bytes > proposalMaxTextBytes) return 'Berkas TXT maksimal 1 MB.';
  if (bytes > proposalMaxBytes) return 'Berkas maksimal 8 MB.';
  return null;
}

bool proposalEditable(String status) => status == 'diajukan' || status == 'perlu_revisi';

bool decisionNoteRequired(String status) => status == 'perlu_revisi' || status == 'ditolak';

/// Penjelasan status untuk pengusul.
String proposalStatusHint(String status) => switch (status) {
  'diajukan' => 'Menunggu tinjauan pengurus RT. Anda masih dapat memperbaiki usulan.',
  'ditinjau' => 'Pengurus sedang meninjau usulan Anda.',
  'perlu_revisi' => 'Pengurus meminta perbaikan. Baca catatan di riwayat, lalu perbaiki usulan.',
  'disetujui' => 'Disetujui dan sudah masuk agenda RT. Semua warga mendapat pemberitahuan.',
  'ditolak' => 'Usulan ditolak. Alasan ada di riwayat keputusan.',
  _ => '',
};
