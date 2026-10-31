import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/api.dart';
import '../../core/format.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/whatsapp.dart';
import '../../core/widgets.dart';
import '../warga/dues_proof.dart';
import '../warga/providers.dart';
import 'pengurus_pages.dart';
import 'flexible_payment.dart';

/// Bukti pembayaran iuran warga per status (diajukan | selesai | ditolak).
final duesConfirmationsProvider = FutureProvider.autoDispose.family<Json, String>(
  (ref, status) async => await accountApi(ref).page('/admin/dues-confirmations', query: {'status': status, 'per_page': 50}),
);

List<Json> _items(Json envelope) => (envelope['data'] as List).cast<Map>().map(Json.from).toList();

int _jumlah(Json envelope) {
  final meta = envelope['meta'];
  final total = meta is Map ? meta['total'] : null;
  return total is num ? total.toInt() : _items(envelope).length;
}

/// Menunggu: yang paling lama dulu. Sudah diproses: yang terbaru dulu.
List<Json> _urut(List<Json> items, String status) {
  DateTime waktu(Json k) => parseTime(k['created_at']) ?? DateTime.fromMillisecondsSinceEpoch(0, isUtc: true);
  return [...items]..sort((a, b) => status == 'diajukan' ? waktu(a).compareTo(waktu(b)) : waktu(b).compareTo(waktu(a)));
}

String _periodes(Json k) => (k['periode'] as List? ?? const []).map((x) => periode('$x')).join(', ');

/// Pintu masuk di tab Keuangan: bukti yang menunggu pencatatan, dengan jumlahnya bila ada.
class DuesConfirmationsEntry extends ConsumerWidget {
  const DuesConfirmationsEntry({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final menunggu = ref.watch(duesConfirmationsProvider('diajukan')).asData?.value;
    final jumlah = menunggu == null ? 0 : _jumlah(menunggu);
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Card(
        child: ListTile(
          key: const ValueKey('bukti-iuran-entry'),
          leading: const Icon(Icons.receipt_long_outlined, color: Brand.navy),
          title: const Text('Bukti pembayaran warga', style: TextStyle(fontWeight: FontWeight.w700)),
          // Jumlah ditaruh di subtitle, bukan di trailing: chip di trailing ListTile memepetkan teks pada skala besar.
          subtitle: Text(jumlah == 0 ? 'Periksa foto transfer, lalu catat pembayarannya' : '$jumlah bukti menunggu diperiksa'),
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const DuesConfirmationsPage())),
        ),
      ),
    );
  }
}

/// Daftar bukti pembayaran iuran warga untuk pengurus, dengan filter status.
class DuesConfirmationsPage extends ConsumerStatefulWidget {
  const DuesConfirmationsPage({super.key});

  @override
  ConsumerState<DuesConfirmationsPage> createState() => _DuesConfirmationsPageState();
}

class _DuesConfirmationsPageState extends ConsumerState<DuesConfirmationsPage> {
  static const _filter = {'diajukan': 'Menunggu', 'selesai': 'Selesai', 'ditolak': 'Ditolak'};
  String _status = 'diajukan';

  @override
  Widget build(BuildContext context) {
    final value = ref.watch(duesConfirmationsProvider(_status));
    return Scaffold(
      backgroundColor: Brand.sky,
      appBar: AppBar(title: const Text('Bukti pembayaran warga')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final e in _filter.entries)
                  ChoiceChip(
                    key: ValueKey('filter-${e.key}'),
                    label: Text(e.value),
                    selected: _status == e.key,
                    onSelected: (_) => setState(() => _status = e.key),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => ref.invalidate(duesConfirmationsProvider),
              child: AsyncView(
                value: value,
                onRetry: () => ref.invalidate(duesConfirmationsProvider),
                builder: (env) {
                  final items = _urut(_items(env), _status);
                  if (items.isEmpty) {
                    return ListView(
                      children: [
                        EmptyState(
                          icon: Icons.receipt_long_outlined,
                          title: 'Belum ada bukti ${_filter[_status]!.toLowerCase()}',
                          message: _status == 'diajukan' ? 'Bukti baru dari warga akan tampil di sini.' : null,
                        ),
                      ],
                    );
                  }
                  return ListView(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                    children: [
                      for (final k in items)
                        _BuktiRow(
                          k,
                          onTap: () async {
                            final berubah = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => DuesConfirmationDetailPage(item: k)));
                            if (berubah == true) ref.invalidate(duesConfirmationsProvider);
                          },
                        ),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BuktiRow extends StatelessWidget {
  const _BuktiRow(this.k, {required this.onTap});
  final Json k;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final rumah = Json.from((k['rumah'] as Map?) ?? const {});
    final pengirim = Json.from((k['pengirim'] as Map?) ?? const {});
    final nominal = k['nominal'];
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        key: ValueKey('bukti-${k['id']}'),
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 8,
                runSpacing: 6,
                children: [
                  Text('Rumah ${rumah['kode_rumah'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  StatusChip(duesProofChipStatus('${k['status']}'), label: '${k['status_label']}'),
                ],
              ),
              const SizedBox(height: 4),
              Text('${pengirim['nama'] ?? '-'} • ${tanggal(parseTime(k['created_at']))}'),
              Text(_periodes(k).isEmpty ? 'Tanpa bulan' : _periodes(k)),
              Text(nominal is num ? rupiah(nominal) : 'Nominal belum diisi', style: const TextStyle(color: Brand.muted)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Detail satu bukti: foto, rincian, dan tindakan pengurus selama status masih diajukan.
class DuesConfirmationDetailPage extends ConsumerStatefulWidget {
  const DuesConfirmationDetailPage({super.key, required this.item});
  final Json item;

  @override
  ConsumerState<DuesConfirmationDetailPage> createState() => _DuesConfirmationDetailPageState();
}

class _DuesConfirmationDetailPageState extends ConsumerState<DuesConfirmationDetailPage> {
  final _processKey = newKey();
  Json get _k => widget.item;
  Json get _rumah => Json.from((_k['rumah'] as Map?) ?? const {});
  Json get _pengirim => Json.from((_k['pengirim'] as Map?) ?? const {});
  bool get _menunggu => _k['status'] == 'diajukan';

  Future<void> _catatPembayaran() async {
    if (ref.read(sessionProvider).akun?.keuanganFleksibel == true) {
      final saved = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => FlexiblePaymentPage(
            house: _rumah,
            initialPeriods: [for (final p in (_k['periode'] as List? ?? const [])) '$p'],
            initialAmount: (_k['nominal'] as num?)?.toInt(),
            confirmationId: '${_k['id']}',
          ),
        ),
      );
      if (mounted && saved == true) Navigator.pop(context, true);
      return;
    }
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => RecordPaymentPage(house: _rumah, initialPeriods: [for (final p in (_k['periode'] as List? ?? const [])) '$p']),
      ),
    );
    if (!mounted) return;
    ref.invalidate(paymentsProvider);
    await _tandaiSelesai();
  }

  Future<void> _tandaiSelesai() async {
    final ok = await confirm(
      context,
      title: 'Tandai bukti ini selesai?',
      message: 'Lakukan setelah pembayaran tercatat.',
      yes: 'Tandai selesai',
      icon: Icons.check_circle_outline,
    );
    if (!ok || !mounted) return;
    await _proses(
      {'status': 'selesai'},
      judul: 'Bukti ditandai selesai',
      pesan: 'Bukti pembayaran sudah diproses. Warga dapat melihat statusnya di menu Iuran.',
    );
  }

  Future<void> _tolak() async {
    final alasan = await askText(context, title: 'Alasan penolakan', label: 'Alasan (wajib)', hint: 'mis. nominal tidak sesuai dengan transfer');
    if (alasan == null || !mounted) return;
    await _proses(
      {'status': 'ditolak', 'catatan': alasan},
      judul: 'Bukti ditolak',
      pesan: 'Warga dapat melihat alasan penolakan di menu Iuran.',
      kind: AlertKind.warning,
    );
  }

  Future<void> _proses(Map<String, dynamic> body, {required String judul, required String pesan, AlertKind kind = AlertKind.success}) async {
    try {
      await ref.read(apiProvider).post('/admin/dues-confirmations/${_k['id']}/process', data: body, idempotencyKey: _processKey);
      if (!mounted) return;
      await showResultDialog(context, title: judul, message: pesan, kind: kind);
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      showApiError(context, e);
      // Sudah diproses pengurus lain: kembali ke daftar agar status terbaru tampil.
      if (e.code == 'ALREADY_PROCESSED') Navigator.pop(context, true);
    }
  }

  void _lihatFoto(String url) {
    final api = ref.read(apiProvider);
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => Scaffold(
          backgroundColor: Colors.black,
          appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: const Text('Foto bukti')),
          body: Center(
            child: InteractiveViewer(
              child: Image.network(
                api.absolute(url),
                headers: api.authHeaders,
                fit: BoxFit.contain,
                semanticLabel: 'Foto bukti transfer',
                errorBuilder: (_, _, _) => const Icon(Icons.broken_image_outlined, color: Colors.white, size: 64),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _baris(String label, String value) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Brand.muted, fontSize: 13)),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    final api = ref.read(apiProvider);
    final url = _k['foto_url'];
    final nominal = _k['nominal'];
    final catatanWarga = '${_k['catatan'] ?? ''}'.trim();
    final catatanPengurus = '${_k['catatan_pengurus'] ?? ''}'.trim();
    final alamat = '${_rumah['alamat'] ?? ''}'.trim();
    return Scaffold(
      appBar: AppBar(title: Text('Bukti rumah ${_rumah['kode_rumah'] ?? '-'}')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (url != null)
            Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: InkWell(
                    key: const ValueKey('foto-bukti'),
                    onTap: () => _lihatFoto('$url'),
                    child: Image.network(
                      api.absolute('$url'),
                      headers: api.authHeaders,
                      height: 320,
                      fit: BoxFit.contain,
                      semanticLabel: 'Foto bukti transfer',
                      errorBuilder: (_, _, _) => const SizedBox(height: 160, child: Center(child: Icon(Icons.broken_image_outlined))),
                    ),
                  ),
                ),
                const SizedBox(height: 4),
                const Text('Ketuk foto untuk memperbesar.', style: TextStyle(color: Brand.muted, fontSize: 13)),
              ],
            ),
          const SizedBox(height: 16),
          SectionCard(
            title: 'Rincian',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _baris('Rumah', alamat.isEmpty ? '${_rumah['kode_rumah'] ?? '-'}' : '${_rumah['kode_rumah'] ?? '-'} • $alamat'),
                _baris('Pengirim', '${_pengirim['nama'] ?? '-'}'),
                _baris('Dikirim', tanggal(parseTime(_k['created_at']), jam: true)),
                _baris('Bulan', _periodes(_k).isEmpty ? 'Tanpa bulan' : _periodes(_k)),
                _baris('Nominal', nominal is num ? rupiah(nominal) : 'Tidak diisi warga'),
                if (catatanWarga.isNotEmpty) _baris('Catatan warga', catatanWarga),
                if (catatanPengurus.isNotEmpty) _baris('Catatan pengurus', catatanPengurus),
                if (_k['diproses_at'] != null) _baris('Diproses', tanggal(parseTime(_k['diproses_at']), jam: true)),
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  spacing: 8,
                  children: [
                    const Text('Status', style: TextStyle(color: Brand.muted, fontSize: 13)),
                    StatusChip(duesProofChipStatus('${_k['status']}'), label: '${_k['status_label']}'),
                  ],
                ),
              ],
            ),
          ),
          if (_menunggu) ...[
            const SizedBox(height: 16),
            FilledButton.icon(
              key: const ValueKey('catat-pembayaran'),
              onPressed: _catatPembayaran,
              icon: const Icon(Icons.point_of_sale),
              label: const Text('Catat pembayaran'),
            ),
            const SizedBox(height: 8),
            if (ref.watch(sessionProvider).akun?.keuanganFleksibel != true)
              BusyButton(label: 'Tandai selesai', icon: Icons.check_circle_outline, outlined: true, onPressed: _tandaiSelesai),
            const SizedBox(height: 8),
            BusyButton(label: 'Tolak', icon: Icons.cancel_outlined, outlined: true, onPressed: _tolak),
            const SizedBox(height: 12),
            Text(
              ref.watch(sessionProvider).akun?.keuanganFleksibel == true
                  ? 'Periksa nominal transfer. Persetujuan, penerimaan kas dan tagihan disimpan bersama setelah rincian dikonfirmasi.'
                  : 'Catat pembayaran di layar berikutnya. Bukti hanya konfirmasi; kas dan tagihan berubah setelah pembayaran dicatat.',
              style: TextStyle(color: Brand.muted, fontSize: 13, height: 1.4),
            ),
          ],
        ],
      ),
    );
  }
}

/// Nomor WhatsApp bendahara (tombol chat di menu Iuran warga) atau, dengan [ketua], nomor Ketua RT yang tampil di halaman
/// Kontak website (ADR43). Keduanya disimpan terpisah; PUT hanya mengirim kolom halaman ini.
class TreasurerContactPage extends ConsumerStatefulWidget {
  const TreasurerContactPage({super.key, this.ketua = false});

  final bool ketua;

  @override
  ConsumerState<TreasurerContactPage> createState() => _TreasurerContactPageState();
}

class _TreasurerContactPageState extends ConsumerState<TreasurerContactPage> {
  final _nomor = TextEditingController();
  bool _terisi = false;
  String? _fieldError;

  String get _field => widget.ketua ? 'ketua_whatsapp' : 'bendahara_whatsapp';
  String get _siapa => widget.ketua ? 'Ketua RT' : 'bendahara';
  String get _dampakKosong => widget.ketua ? 'Halaman Kontak website menampilkan "Belum diumumkan".' : 'Tombol WhatsApp di menu Iuran warga nonaktif.';

  @override
  void initState() {
    super.initState();
    // Isi kolom sekali dengan nomor tersimpan; setelahnya kolom mengikuti ketikan pengurus.
    ref.read(contactsProvider.future).then((d) {
      if (!mounted || _terisi) return;
      _terisi = true;
      _nomor.text = d[_field] == null ? '' : waDisplay('${d[_field]}');
    }, onError: (_) {});
  }

  @override
  void dispose() {
    _nomor.dispose();
    super.dispose();
  }

  Future<void> _simpan() => _kirim(_nomor.text.trim().isEmpty ? null : _nomor.text.trim(), sukses: 'Nomor WhatsApp $_siapa tersimpan.');

  Future<void> _kosongkan() async {
    final ok = await confirm(
      context,
      title: 'Kosongkan nomor $_siapa?',
      message: widget.ketua
          ? 'Halaman Kontak website menampilkan "Belum diumumkan" sampai nomor baru diisi.'
          : 'Tombol WhatsApp di menu Iuran warga nonaktif sampai nomor baru diisi.',
      yes: 'Kosongkan',
      danger: true,
    );
    if (!ok || !mounted) return;
    await _kirim(null, sukses: 'Nomor $_siapa dikosongkan.');
  }

  Future<void> _kirim(String? nomor, {required String sukses}) async {
    setState(() => _fieldError = null);
    try {
      final res = await ref.read(apiProvider).put('/admin/settings/contacts', data: {_field: nomor});
      ref.invalidate(contactsProvider);
      if (!mounted) return;
      final normal = res is Map ? res[_field] : null;
      _nomor.text = normal == null ? '' : waDisplay('$normal');
      showMessage(context, sukses);
    } on ApiException catch (e) {
      if (!mounted) return;
      final pesanField = e.fields[_field];
      if (pesanField != null && pesanField.isNotEmpty) {
        setState(() => _fieldError = pesanField.first);
      } else {
        showApiError(context, e);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final kontak = ref.watch(contactsProvider);
    final tersimpan = kontak.asData?.value[_field] as String?;
    return Scaffold(
      appBar: AppBar(title: Text('Nomor WhatsApp $_siapa')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            widget.ketua
                ? 'Nomor ini tampil sebagai kontak resmi Ketua RT di halaman Kontak website (bisa dilihat siapa saja). Isi dengan nomor WhatsApp Ketua RT yang aktif.'
                : 'Nomor ini dipakai tombol "Chat bendahara lewat WhatsApp" di menu Iuran warga. Isi dengan nomor WhatsApp bendahara yang aktif.',
            style: const TextStyle(height: 1.45),
          ),
          const SizedBox(height: 16),
          AsyncView(
            value: kontak,
            onRetry: () => ref.invalidate(contactsProvider),
            builder: (_) => SectionCard(
              title: 'Nomor tersimpan',
              child: Text(tersimpan == null ? 'Belum diatur. $_dampakKosong' : waDisplay(tersimpan), style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            key: ValueKey(widget.ketua ? 'nomor-ketua' : 'nomor-bendahara'),
            controller: _nomor,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(labelText: 'Nomor WhatsApp $_siapa', hintText: 'mis. 0812 3456 7890', errorText: _fieldError),
          ),
          const SizedBox(height: 12),
          BusyButton(label: 'Simpan nomor', icon: Icons.save_outlined, onPressed: _simpan),
          const SizedBox(height: 8),
          OutlinedButton(onPressed: _kosongkan, child: const Text('Kosongkan')),
          const SizedBox(height: 20),
          WhatsAppWideButton(label: 'Coba buka WhatsApp', phone: tersimpan),
          const SizedBox(height: 8),
          const Text(
            'Tes ini hanya membuka chat WhatsApp ke nomor tersimpan. Pesan tidak terkirim otomatis.',
            style: TextStyle(color: Brand.muted, fontSize: 13, height: 1.4),
          ),
        ],
      ),
    );
  }
}
