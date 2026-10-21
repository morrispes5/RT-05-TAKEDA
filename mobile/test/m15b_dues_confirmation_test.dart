import 'dart:typed_data';

import 'package:dio/dio.dart' show FormData, RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/dues_confirmations.dart';
import 'package:rt05_takeda/features/warga/dues_proof.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// Bukti pembayaran iuran (M15b): warga mengirim bukti offline, pengurus memproses. Status tagihan
/// tidak pernah berubah dari aplikasi; pencatatan tetap lewat RecordPaymentPage.
class _GaleriPicker extends Fake implements ImagePicker {
  @override
  Future<XFile?> pickImage({
    required ImageSource source,
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    CameraDevice preferredCameraDevice = CameraDevice.rear,
    bool requestFullMetadata = true,
  }) async => XFile.fromData(Uint8List.fromList([1, 2, 3]), name: 'bukti.jpg', path: 'bukti.jpg');
}

Map<String, dynamic> _konfirmasi({String id = 'k-1', String status = 'diajukan', String statusLabel = 'Diajukan', String? catatanPengurus}) => {
  'id': id,
  'status': status,
  'status_label': statusLabel,
  'periode': ['2026-10'],
  'nominal': 75000,
  'catatan': null,
  'catatan_pengurus': catatanPengurus,
  'foto_url': '/api/v1/dues/confirmations/$id/photo',
  'created_at': '2026-10-20T03:00:00Z',
  'diproses_at': null,
};

/// Bukti menunggu dengan relasi rumah dan pengirim, bentuk item daftar admin.
Map<String, dynamic> _menunggu() => {
  ..._konfirmasi(),
  'rumah': {'id': 'h-1', 'kode_rumah': 'A-01', 'alamat': 'Jalan Uji Coba Nomor 1 Blok A'},
  'pengirim': {'id': 'w-1', 'nama': 'Warga Uji Coba'},
};

Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

Future<void> _pump(WidgetTester tester, Widget home, FakeHandler handler) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
      child: MaterialApp(theme: buildTheme(), home: home),
    ),
  );
  await _settle(tester);
}

/// Halaman yang dibuka dari tombol "Buka", agar tombol kembali (pop) bisa diuji.
Widget _opener(Widget Function() page) => Scaffold(
  body: Builder(
    builder: (c) => Center(
      child: TextButton(
        onPressed: () => Navigator.push(c, MaterialPageRoute(builder: (_) => page())),
        child: const Text('Buka'),
      ),
    ),
  ),
);

Map<String, dynamic> _bodyOf(RequestOptions o) => Map<String, dynamic>.from(o.data as Map);

void main() {
  testWidgets('kirim bukti: multipart berisi foto, bulan, dan nominal, lalu kembali setelah hasil', (tester) async {
    useCompactScreen(tester, height: 1600);
    final posts = <RequestOptions>[];
    final base = apiHandler(me: akunJson);
    await _pump(tester, _opener(() => DuesProofPage(picker: _GaleriPicker())), (o) {
      if (o.uri.path == '/api/v1/dues/confirmations' && o.method == 'POST') {
        posts.add(o);
        return jsonBody(201, {'data': _konfirmasi()});
      }
      return base(o);
    });

    await tester.tap(find.text('Buka'));
    await _settle(tester);
    expect(find.text('Belum ada foto dipilih.'), findsOneWidget);

    await tester.tap(find.text('Galeri'));
    await _settle(tester);
    expect(find.text('Belum ada foto dipilih.'), findsNothing);

    await tester.tap(find.text('Oktober 2026'));
    await _settle(tester);
    expect(tester.widget<TextField>(find.byKey(const ValueKey('nominal-bukti'))).controller!.text, '75000');

    await tester.tap(find.widgetWithText(FilledButton, 'Kirim bukti'));
    await _settle(tester);
    expect(find.text('Kirim bukti transfer?'), findsOneWidget);
    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.text('Kirim')));
    await _settle(tester);

    expect(posts, hasLength(1));
    final form = posts.single.data as FormData;
    final fields = [for (final e in form.fields) '${e.key}=${e.value}'];
    expect(fields, containsAll(const ['periode[]=2026-10', 'nominal=75000']));
    expect(form.files.single.key, 'foto');
    expect(form.files.single.value.filename, 'bukti.jpg');
    expect(find.text('Bukti terkirim'), findsOneWidget);

    await tester.tap(find.text('Mengerti'));
    await _settle(tester);
    expect(find.text('Kirim bukti transfer'), findsNothing, reason: 'halaman kirim tertutup setelah berhasil');
    expect(tester.takeException(), isNull);
  });

  testWidgets('kirim bukti tanpa bulan dicentang tidak mengirim apa pun', (tester) async {
    useCompactScreen(tester, height: 1600);
    final posts = <RequestOptions>[];
    final base = apiHandler(me: akunJson);
    await _pump(tester, _opener(() => DuesProofPage(picker: _GaleriPicker())), (o) {
      if (o.method == 'POST') posts.add(o);
      return base(o);
    });

    await tester.tap(find.text('Buka'));
    await _settle(tester);
    await tester.tap(find.text('Galeri'));
    await _settle(tester);
    await tester.tap(find.widgetWithText(FilledButton, 'Kirim bukti'));
    await _settle(tester);

    expect(find.text('Centang minimal satu bulan yang sudah dibayar.'), findsOneWidget);
    expect(posts, isEmpty);
  });

  testWidgets('pengurus: tolak wajib alasan, lalu mengirim status ditolak dengan catatan', (tester) async {
    useCompactScreen(tester, height: 1600);
    final posts = <RequestOptions>[];
    final base = apiHandler(me: akunJson);
    await _pump(tester, _opener(() => DuesConfirmationDetailPage(item: _menunggu())), (o) {
      if (o.uri.path == '/api/v1/admin/dues-confirmations/k-1/process' && o.method == 'POST') {
        posts.add(o);
        return jsonBody(200, {'data': _konfirmasi(status: 'ditolak', statusLabel: 'Ditolak')});
      }
      return base(o);
    });

    await tester.tap(find.text('Buka'));
    await _settle(tester);
    expect(find.text('Bukti rumah A-01'), findsOneWidget);

    // Tanpa alasan, dialog ditutup dan tidak ada permintaan.
    await tester.tap(find.text('Tolak'));
    await _settle(tester);
    expect(find.text('Alasan penolakan'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
    await _settle(tester);
    expect(posts, isEmpty);

    await tester.tap(find.text('Tolak'));
    await _settle(tester);
    await tester.enterText(find.byType(TextField), 'Nominal tidak sesuai');
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
    await _settle(tester);

    expect(posts, hasLength(1));
    expect(_bodyOf(posts.single), {'status': 'ditolak', 'catatan': 'Nominal tidak sesuai'});
    expect(find.text('Bukti ditolak'), findsOneWidget);
    await tester.tap(find.text('Mengerti'));
    await _settle(tester);
    expect(tester.takeException(), isNull);
  });

  testWidgets('pengurus: tandai selesai meminta konfirmasi lalu mengirim status selesai', (tester) async {
    useCompactScreen(tester, height: 1600);
    final posts = <RequestOptions>[];
    final base = apiHandler(me: akunJson);
    await _pump(tester, _opener(() => DuesConfirmationDetailPage(item: _menunggu())), (o) {
      if (o.uri.path == '/api/v1/admin/dues-confirmations/k-1/process' && o.method == 'POST') {
        posts.add(o);
        return jsonBody(200, {'data': _konfirmasi(status: 'selesai', statusLabel: 'Selesai')});
      }
      return base(o);
    });

    await tester.tap(find.text('Buka'));
    await _settle(tester);
    await tester.tap(find.text('Tandai selesai'));
    await _settle(tester);
    expect(find.text('Tandai bukti ini selesai?'), findsOneWidget);
    expect(posts, isEmpty, reason: 'belum ada permintaan sebelum dikonfirmasi');

    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.text('Tandai selesai')));
    await _settle(tester);
    expect(posts, hasLength(1));
    expect(_bodyOf(posts.single), {'status': 'selesai'});
    expect(find.text('Bukti ditandai selesai'), findsOneWidget);
  });

  testWidgets('pengurus: nomor bendahara dikirim sebagai PUT dan tampil ternormalisasi', (tester) async {
    useCompactScreen(tester, height: 1600);
    final puts = <RequestOptions>[];
    var tersimpan = const <String, Object?>{'bendahara_whatsapp': null};
    final base = apiHandler(me: akunJson);
    await _pump(tester, const TreasurerContactPage(), (o) {
      if (o.uri.path == '/api/v1/admin/settings/contacts' && o.method == 'PUT') {
        puts.add(o);
        tersimpan = {'bendahara_whatsapp': '6281234567890'};
        return jsonBody(200, {'data': tersimpan});
      }
      if (o.uri.path == '/api/v1/settings/contacts') return jsonBody(200, {'data': tersimpan});
      return base(o);
    });

    expect(find.text('Belum diatur. Tombol WhatsApp di menu Iuran warga nonaktif.'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('nomor-bendahara')), '0812 3456 7890');
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan nomor'));
    await _settle(tester);

    expect(puts, hasLength(1));
    expect(_bodyOf(puts.single), {'bendahara_whatsapp': '0812 3456 7890'});
    expect(tester.widget<TextField>(find.byKey(const ValueKey('nomor-bendahara'))).controller!.text, '0812-3456-7890'); // ditampilkan format lokal (perbaikan uji emulator 1010)
    expect(find.text('Nomor WhatsApp bendahara tersimpan.'), findsOneWidget);
  });

  testWidgets('pengurus: nomor tidak valid menampilkan pesan pada kolom', (tester) async {
    useCompactScreen(tester, height: 1600);
    final base = apiHandler(me: akunJson);
    await _pump(tester, const TreasurerContactPage(), (o) {
      if (o.uri.path == '/api/v1/admin/settings/contacts' && o.method == 'PUT') {
        return jsonBody(422, {
          'error': {
            'code': 'VALIDATION_FAILED',
            'message': 'Data belum valid.',
            'fields': {
              'bendahara_whatsapp': ['Nomor hanya boleh berisi angka, spasi, + atau -.'],
            },
            'request_id': '00000000-0000-4000-8000-000000000000',
          },
        });
      }
      return base(o);
    });

    await tester.enterText(find.byKey(const ValueKey('nomor-bendahara')), 'abc');
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan nomor'));
    await _settle(tester);
    expect(find.text('Nomor hanya boleh berisi angka, spasi, + atau -.'), findsOneWidget);
  });

  testWidgets('pengurus: kosongkan nomor meminta konfirmasi lalu PUT null', (tester) async {
    useCompactScreen(tester, height: 1600);
    final puts = <RequestOptions>[];
    final base = apiHandler(me: akunJson);
    await _pump(tester, const TreasurerContactPage(), (o) {
      if (o.uri.path == '/api/v1/admin/settings/contacts' && o.method == 'PUT') {
        puts.add(o);
        return jsonBody(200, {
          'data': {'bendahara_whatsapp': null},
        });
      }
      return base(o);
    });

    await tester.tap(find.text('Kosongkan'));
    await _settle(tester);
    expect(find.text('Kosongkan nomor bendahara?'), findsOneWidget);
    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.text('Kosongkan')));
    await _settle(tester);

    expect(puts, hasLength(1));
    expect(_bodyOf(puts.single), {'bendahara_whatsapp': null});
  });

  testWidgets('pengurus: daftar menunggu tampil dengan status dan membuka detail', (tester) async {
    useCompactScreen(tester, height: 1600);
    final base = apiHandler(me: akunJson);
    await _pump(tester, const DuesConfirmationsPage(), (o) {
      if (o.uri.path == '/api/v1/admin/dues-confirmations') {
        final status = o.queryParameters['status'];
        final data = status == 'diajukan' ? [_menunggu()] : <Object?>[];
        return jsonBody(200, {
          'data': data,
          'meta': {'current_page': 1, 'per_page': 50, 'total': data.length},
        });
      }
      return base(o);
    });

    expect(find.text('Rumah A-01'), findsOneWidget);
    expect(find.text('Diajukan'), findsOneWidget);
    expect(find.textContaining('Warga Uji Coba'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('bukti-k-1')));
    await _settle(tester);
    expect(find.text('Tandai selesai'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('warga: bukti yang sudah dikirim tampil dengan catatan pengurus', (tester) async {
    useCompactScreen(tester, height: 1600);
    final base = apiHandler(me: akunJson);
    await _pump(tester, const Scaffold(body: DuesPage()), (o) {
      if (o.uri.path == '/api/v1/dues/confirmations') {
        return jsonBody(200, {
          'data': [_konfirmasi(status: 'ditolak', statusLabel: 'Ditolak', catatanPengurus: 'Nominal tidak sesuai dengan transfer.')],
        });
      }
      return base(o);
    });

    expect(find.text('Bukti yang sudah dikirim'), findsOneWidget);
    expect(find.text('Ditolak'), findsOneWidget);
    expect(find.text('Catatan pengurus: Nominal tidak sesuai dengan transfer.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
