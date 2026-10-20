import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/features/warga/activity_proposal_actions.dart';

/// Aturan form usulan kegiatan (docs/USULAN_KEGIATAN.md §3) sebagai fungsi murni.
void main() {
  final now = DateTime(2026, 10, 16, 10, 0); // jam dinding Jakarta
  ProposalDraft draft({
    String? jenis = 'olahraga',
    String nama = 'Badminton bersama di lapangan RT 05',
    String deskripsi = 'Badminton warga, terbuka untuk semua umur.',
    String lokasi = 'Lapangan RT 05',
    DateTime? mulai,
    DateTime? selesai,
    String peserta = '20',
    bool pernyataan = true,
  }) => ProposalDraft(
    jenis: jenis,
    nama: nama,
    deskripsi: deskripsi,
    lokasi: lokasi,
    mulai: mulai ?? DateTime(2026, 10, 23, 16, 0),
    selesai: selesai ?? DateTime(2026, 10, 23, 18, 0),
    peserta: peserta,
    pernyataan: pernyataan,
  );

  test('draf lengkap Ibu Sri lolos dan body dikirim dalam UTC', () {
    expect(validateProposal(draft(), now), isEmpty);
    final body = proposalBody(draft(), versi: 2);
    expect(body['mulai_at'], '2026-10-23T09:00:00.000Z');
    expect(body['selesai_at'], '2026-10-23T11:00:00.000Z');
    expect(body['perkiraan_peserta'], 20);
    expect(body['pernyataan'], true);
    expect(body['versi'], 2);
    expect(proposalBody(draft(peserta: ''))..remove('versi'), isNot(contains('perkiraan_peserta')));
  });

  test('setiap aturan memberi pesan pada field yang tepat', () {
    expect(validateProposal(draft(jenis: null), now).keys, ['jenis']);
    expect(validateProposal(draft(nama: 'Abc'), now).keys, ['nama']);
    expect(validateProposal(draft(deskripsi: 'pendek'), now).keys, ['deskripsi']);
    expect(validateProposal(draft(lokasi: ' '), now).keys, ['lokasi']);
    expect(validateProposal(draft(mulai: DateTime(2026, 10, 16, 20), selesai: DateTime(2026, 10, 16, 21)), now)['mulai_at'], contains('24 jam'));
    expect(validateProposal(draft(selesai: DateTime(2026, 10, 23, 15)), now)['selesai_at'], contains('setelah jam mulai'));
    expect(validateProposal(draft(selesai: DateTime(2026, 10, 24, 6)), now)['selesai_at'], contains('12 jam'));
    expect(validateProposal(draft(peserta: '5000'), now).keys, ['perkiraan_peserta']);
    expect(validateProposal(draft(pernyataan: false), now).keys, ['pernyataan']);
  });

  test('lampiran dibatasi jenis, ukuran, dan jumlah', () {
    expect(documentProblem('susunan.pdf', 1000, 0), isNull);
    expect(documentProblem('catatan.TXT', 1000, 2), isNull);
    expect(documentProblem('poster.jpeg', 1000, 0), isNull);
    expect(documentProblem('proposal.docx', 1000, 0), contains('PDF'));
    expect(documentProblem('catatan.txt', proposalMaxTextBytes + 1, 0), contains('1 MB'));
    // UAT M15: PDF disimpan apa adanya, maks. 1 MB; foto boleh 8 MB karena diperkecil ulang.
    expect(documentProblem('besar.pdf', proposalMaxTextBytes + 1, 0), contains('PDF maksimal 1 MB'));
    expect(documentProblem('foto.jpg', proposalMaxBytes + 1, 0), contains('8 MB'));
    expect(documentProblem('foto.jpg', proposalMaxTextBytes + 1, 0), isNull);
    expect(documentProblem('keempat.pdf', 10, 3), contains('Maksimal 3'));
  });

  test('status dan catatan keputusan', () {
    expect(proposalEditable('diajukan'), isTrue);
    expect(proposalEditable('perlu_revisi'), isTrue);
    expect(proposalEditable('ditinjau'), isFalse);
    expect(proposalEditable('disetujui'), isFalse);
    expect(decisionNoteRequired('perlu_revisi'), isTrue);
    expect(decisionNoteRequired('ditolak'), isTrue);
    expect(decisionNoteRequired('disetujui'), isFalse);
    expect(proposalStatusHint('disetujui'), contains('agenda'));
  });
}
