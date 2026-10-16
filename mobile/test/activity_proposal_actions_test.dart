import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/features/warga/activity_proposal_actions.dart';

void main() {
  test('body usulan memangkas teks dan menyertakan versi hanya saat perbaikan', () {
    final mulai = DateTime.utc(2026, 10, 18, 1);
    final body = proposalBody(nama: ' Senam ', deskripsi: ' Bersama ', lokasi: ' Lapangan ', mulaiUtc: mulai, versi: 2);
    expect(body['nama'], 'Senam');
    expect(body['deskripsi'], 'Bersama');
    expect(body['lokasi'], 'Lapangan');
    expect(body['mulai_at'], mulai.toIso8601String());
    expect(body.containsKey('selesai_at'), isFalse);
    expect(body['versi'], 2);
  });

  test('jam dinding Jakarta dikirim sebagai UTC', () {
    expect(jakartaWallToUtc(DateTime(2026, 10, 18, 8, 30)), DateTime.utc(2026, 10, 18, 1, 30));
  });

  test('hanya usulan disetujui yang belum punya agenda yang dapat dijadwalkan', () {
    expect(proposalEditable('diajukan'), isTrue);
    expect(proposalEditable('perlu_revisi'), isTrue);
    expect(proposalEditable('disetujui'), isFalse);
    expect(proposalSchedulable('disetujui', null), isTrue);
    expect(proposalSchedulable('disetujui', 'agenda-1'), isFalse);
    expect(proposalSchedulable('diajukan', null), isFalse);
    expect(decisionNoteRequired('perlu_revisi'), isTrue);
    expect(decisionNoteRequired('ditolak'), isTrue);
    expect(decisionNoteRequired('disetujui'), isFalse);
  });
}
