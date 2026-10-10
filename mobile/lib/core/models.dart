/// DTO ringan sesuai respons API v1 (docs/openapi.yaml). Field mengikuti nama JSON server;
/// client tidak menghitung saldo, tunggakan, atau status — semuanya dari API.
typedef Json = Map<String, dynamic>;

DateTime? parseTime(dynamic v) => v == null ? null : DateTime.tryParse('$v')?.toUtc();

class Akun {
  Akun(this.raw);
  final Json raw;

  String get id => raw['id'] as String;
  String get nama => '${raw['nama']}';
  String get email => '${raw['email']}';
  String get peran => '${raw['peran']}';
  String get jenis => '${raw['jenis']}';
  String get status => '${raw['status']}';
  bool get dapatUbahKeluarga => raw['dapat_ubah_keluarga'] == true;
  Json? get rumah => raw['rumah'] as Json?;
  String? get kodeRumah => rumah?['kode_rumah'] as String?;
  String? get alamat => rumah?['alamat'] as String?;
}

class Tagihan {
  Tagihan(this.raw);
  final Json raw;

  String get id => raw['id'] as String;
  String get periode => '${raw['periode']}';
  int get nominal => (raw['nominal'] as num).toInt();
  String get status => '${raw['status']}';
  bool get lunas => status == 'lunas';
  String get label => '${raw['status_label']}';
}

class Laporan {
  /// Pengaduan atau aspirasi (field `deskripsi`/`isi`, `pelapor`/`pengirim`).
  Laporan(this.raw, {required this.aspirasi});
  final Json raw;
  final bool aspirasi;

  String get id => raw['id'] as String;
  String get judul => '${raw['judul']}';
  String get isi => '${raw[aspirasi ? 'isi' : 'deskripsi']}';
  String get status => '${raw['status']}';
  int get versi => (raw['versi'] as num).toInt();
  bool get anonim => raw['sembunyikan_identitas'] == true;
  bool get milikSaya => raw['milik_saya'] == true;
  String? get namaPelapor => (raw[aspirasi ? 'pengirim' : 'pelapor'] as Json?)?['nama'] as String?;
  String? get kategori => (raw['kategori'] as Json?)?['nama'] as String?;
  List<Json> get foto => ((raw['foto'] as List?) ?? []).cast<Json>();
  DateTime? get dibuat => parseTime(raw['created_at']);
}
