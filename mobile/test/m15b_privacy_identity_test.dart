import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/router.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/core/whatsapp.dart';
import 'package:rt05_takeda/features/auth/privacy_policy.dart';
import 'package:rt05_takeda/features/household/admin_links.dart';
import 'package:rt05_takeda/features/household/complete_data_card.dart';
import 'package:rt05_takeda/features/household/identity_data.dart';

import 'support/fakes.dart';

/// Permintaan Pak RT 20 Okt (ADR35): Kebijakan Privasi wajib disetujui setelah masuk, NIK/KK opsional tersamar,
/// tombol WhatsApp warga untuk pengurus, dan ajakan melengkapi data.
Object? _body(RequestOptions options) {
  final data = options.data;
  if (data is Map) return Map<String, dynamic>.from(data);
  if (data is String && data.isNotEmpty) return jsonDecode(data);
  return data;
}

Future<void> _step(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

Future<ProviderContainer> _pump(WidgetTester tester, Widget home, FakeHandler handler, {MemoryTokenStorage? storage}) async {
  tester.view.physicalSize = const Size(1200, 3600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  final container = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
      tokenStorageProvider.overrideWithValue(storage ?? MemoryTokenStorage('t')),
      httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
    ],
  );
  addTearDown(container.dispose);
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(theme: buildTheme(), home: home),
    ),
  );
  await tester.pumpAndSettle();
  return container;
}

Map<String, dynamic> _household({String? kk, String? nik, String? wa = '0812 1111 2222'}) => {
  'id': 'k-1',
  'status': 'terverifikasi',
  'versi': 4,
  'rumah': {'id': 'h-1', 'kode_rumah': 'D5-21', 'alamat': 'Blok D5 No. 21'},
  'jenis_hunian': 'pemilik',
  'nomor_kk_tersamar': kk,
  'anggota': [
    {
      'id': 'w-1',
      'nama': 'Kepala Uji',
      'hubungan_keluarga': 'Kepala keluarga',
      'is_kepala': true,
      'whatsapp': wa,
      'nik_tersamar': nik,
      'status': 'aktif',
      'punya_akun': true,
    },
    {
      'id': 'w-2',
      'nama': 'Anak Uji',
      'hubungan_keluarga': 'Anak',
      'is_kepala': false,
      'whatsapp': null,
      'nik_tersamar': null,
      'status': 'aktif',
      'punya_akun': false,
    },
  ],
  'dapat_ubah': true,
};

void main() {
  group('gerbang Kebijakan Privasi', () {
    const healthy = HealthState.ok(HealthOk());
    String? go(SessionState s, String loc) => sessionRedirect(health: healthy, session: s, location: loc);

    test('akun aktif yang belum setuju hanya boleh di halaman privasi; setelah setuju kembali ke beranda', () {
      final needs = SessionState(loading: false, token: 't', akun: Akun({...akunJson(), 'privasi_perlu_setuju': true}));
      expect(go(needs, Routes.home), Routes.privacy);
      expect(go(needs, Routes.notifications), Routes.privacy);
      expect(go(needs, Routes.privacy), isNull);
      final done = SessionState(loading: false, token: 't', akun: Akun({...akunJson(), 'privasi_perlu_setuju': false}));
      expect(go(done, Routes.privacy), Routes.home);
      expect(go(done, Routes.home), isNull);
      // Respons lama tanpa field ini tidak memaksa halaman privasi; akun menunggu tetap ke halaman menunggu.
      expect(go(SessionState(loading: false, token: 't', akun: Akun(akunJson())), Routes.home), isNull);
      final pending = SessionState(
        loading: false,
        token: 't',
        akun: Akun({...akunJson(status: 'menunggu', jenis: 'tambahan'), 'privasi_perlu_setuju': true}),
      );
      expect(go(pending, Routes.home), Routes.pending);
    });

    testWidgets('setuju hanya setelah dicentang, lalu mengirim versi dan memperbarui sesi', (tester) async {
      Object? sent;
      final storage = MemoryTokenStorage('t');
      final container = await _pump(tester, const PrivacyPolicyPage(consent: true), (options) {
        if (options.uri.path == '/api/v1/auth/privacy-consent') {
          sent = _body(options);
          return jsonBody(200, {
            'data': {...akunJson(), 'privasi_versi': '2026-10-21', 'privasi_perlu_setuju': false},
          });
        }
        return jsonBody(200, {'data': {}});
      }, storage: storage);
      await container.read(sessionProvider.notifier).signIn('t', Akun({...akunJson(), 'privasi_versi': '2026-10-21', 'privasi_perlu_setuju': true}));
      await tester.pump();
      expect(container.read(sessionProvider).needsPrivacy, isTrue);

      expect(find.text('Siapa yang bisa melihat'), findsOneWidget);
      await tester.tap(find.text('Setuju dan lanjutkan'));
      await _step(tester);
      expect(sent, isNull, reason: 'tombol nonaktif sebelum dicentang');

      await tester.tap(find.byKey(const ValueKey('privasi-setuju-centang')));
      await tester.pump();
      await tester.tap(find.text('Setuju dan lanjutkan'));
      await _step(tester);
      expect(sent, {'versi': '2026-10-21'});
      expect(container.read(sessionProvider).needsPrivacy, isFalse);
    });

    testWidgets('mode baca saja tanpa tombol setuju', (tester) async {
      await _pump(tester, const PrivacyPolicyPage(), (_) => jsonBody(200, {'data': {}}));
      expect(find.text('Kebijakan Privasi'), findsOneWidget);
      expect(find.text('Setuju dan lanjutkan'), findsNothing);
      expect(find.textContaining('NIK dan nomor KK disimpan terenkripsi'), findsOneWidget);
    });
  });

  group('data kependudukan (NIK & KK)', () {
    test('validasi 16 digit; kosong berarti tidak diubah', () {
      expect(identityNumberError('', label: 'NIK'), isNull);
      expect(identityNumberError('3674010101900001', label: 'NIK'), isNull);
      expect(identityNumberError('12345', label: 'NIK'), 'NIK harus 16 digit angka.');
    });

    testWidgets('akun utama mengisi KK dan NIK lewat endpoint keluarga sendiri dengan versi terbaru', (tester) async {
      final patches = <String, Object?>{};
      var versi = 4;
      await _pump(tester, const IdentityDataPage(), (options) {
        if (options.method == 'GET' && options.uri.path == '/api/v1/household') {
          return jsonBody(200, {
            'data': {..._household(), 'versi': versi},
          });
        }
        if (options.method == 'PATCH') {
          patches[options.uri.path] = _body(options);
          versi++;
          return jsonBody(200, {'data': _household()});
        }
        return jsonBody(200, {'data': {}});
      });
      expect(find.textContaining('Belum diisi'), findsNWidgets(3));

      await tester.enterText(find.byKey(const ValueKey('field-nomor-kk')), '1234');
      await tester.tap(find.text('Simpan'));
      await _step(tester);
      expect(find.text('Nomor KK harus 16 digit angka.'), findsOneWidget);
      expect(patches, isEmpty);

      await tester.enterText(find.byKey(const ValueKey('field-nomor-kk')), '3674012345678901');
      await tester.enterText(find.byKey(const ValueKey('field-nik-w-2')), '3674010101100002');
      await tester.tap(find.text('Simpan'));
      await _step(tester);
      expect(patches['/api/v1/household'], {'versi': 4, 'nomor_kk': '3674012345678901'});
      expect(patches['/api/v1/household/members/w-2'], {'versi': 5, 'nik': '3674010101100002'});
      expect(patches.containsKey('/api/v1/household/members/w-1'), isFalse, reason: 'NIK yang tidak diisi tidak dikirim');
      expect(find.text('Data tersimpan'), findsOneWidget);
    });

    testWidgets('pengurus mengosongkan nomor KK lewat endpoint admin', (tester) async {
      Object? sent;
      await _pump(tester, const IdentityDataPage(familyId: 'k-1'), (options) {
        if (options.method == 'GET' && options.uri.path == '/api/v1/admin/families/k-1') return jsonBody(200, {'data': _household(kk: '3674••••••••8901')});
        if (options.method == 'PATCH' && options.uri.path == '/api/v1/admin/families/k-1') {
          sent = _body(options);
          return jsonBody(200, {'data': _household()});
        }
        return jsonBody(200, {'data': {}});
      });
      expect(find.textContaining('Tersimpan: 3674••••••••8901'), findsOneWidget);
      await tester.tap(find.text('Kosongkan'));
      await tester.pump();
      await tester.tap(find.text('Simpan'));
      await _step(tester);
      expect(sent, {'versi': 4, 'nomor_kk': null});
    });
  });

  group('WhatsApp warga dan lengkapi data', () {
    test('nomor Indonesia dinormalisasi ke format wa.me', () {
      expect(waNumber('0812-3456-7890'), '6281234567890');
      expect(waNumber('+62 812 3456 7890'), '6281234567890');
      expect(waNumber('812 3456 7890'), '6281234567890');
      expect(waNumber(''), isNull);
      expect(waNumber(null), isNull);
      expect(waUri('6281234567890', text: 'Halo').toString(), 'https://wa.me/6281234567890?text=Halo');
    });

    testWidgets('pengurus melihat tombol WhatsApp hanya untuk anggota yang punya nomor', (tester) async {
      await _pump(tester, const FamilyAdminPage(id: 'k-1'), (options) {
        if (options.uri.path == '/api/v1/admin/families/k-1') {
          return jsonBody(200, {
            'data': {..._household(), 'archived_at': null, 'penghunian': [], 'riwayat_keanggotaan': [], 'akun': []},
          });
        }
        return jsonBody(200, {'data': {}});
      });
      expect(find.byTooltip('Chat WhatsApp Kepala Uji'), findsOneWidget);
      expect(find.byTooltip('Chat WhatsApp Anak Uji'), findsNothing);
      expect(find.byKey(const ValueKey('admin-data-kependudukan')), findsOneWidget);
    });

    test('kartu lengkapi data menyebut yang masih kosong', () {
      expect(missingHouseholdData(_household()), ['NIK', 'nomor KK']);
      expect(missingHouseholdData(_household(wa: null, kk: 'x', nik: 'y')), ['WhatsApp']);
      expect(missingHouseholdData(_household(kk: 'x', nik: 'y')), isEmpty);
    });
  });
}
