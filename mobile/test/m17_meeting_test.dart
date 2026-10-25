import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/core/widgets.dart';
import 'package:rt05_takeda/features/household/admin_edits.dart';
import 'package:rt05_takeda/features/household/family_avatar.dart';
import 'package:rt05_takeda/features/pengurus/dues_confirmations.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// M17 (ADR43, rapat RT 24 Okt 2026): nomor Ketua RT untuk halaman Kontak website, super admin mengubah peran pengurus,
/// pilihan jalan Mawar 8â€“14, dan umur di daftar keluarga warga (temuan uji APK 1015).
Map<String, dynamic> _bodyOf(RequestOptions o) =>
    o.data is String ? jsonDecode(o.data as String) as Map<String, dynamic> : Map<String, dynamic>.from(o.data as Map);

Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

/// Halaman dengan sesi masuk sebagai [saya]; [saya] null berarti tanpa sesi.
Future<void> _pump(WidgetTester tester, Widget home, FakeHandler handler, {Map<String, dynamic>? saya}) async {
  final container = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
      httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
    ],
  );
  addTearDown(container.dispose);
  if (saya != null) await tester.runAsync(() => container.read(sessionProvider.notifier).signIn('t', Akun(saya)));
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(theme: buildTheme(), home: home),
    ),
  );
  await _settle(tester);
}

Map<String, dynamic> _target({String peran = 'warga', String status = 'aktif'}) => {
  'id': 'a-2',
  'nama': 'Calon Pengurus Uji',
  'email': 'calon@example.test',
  'status': status,
  'jenis': 'utama',
  'peran': peran,
  'super_admin': false,
  'tautan': const [],
  'permohonan': null,
};

void main() {
  testWidgets('kontak Ketua RT: PUT hanya mengirim ketua_whatsapp dan tampil format lokal', (tester) async {
    useCompactScreen(tester, height: 1600);
    final puts = <RequestOptions>[];
    var tersimpan = const <String, Object?>{'bendahara_whatsapp': '6281111111111', 'ketua_whatsapp': null};
    final base = apiHandler(me: akunJson);
    await _pump(tester, const TreasurerContactPage(ketua: true), (o) {
      if (o.uri.path == '/api/v1/admin/settings/contacts' && o.method == 'PUT') {
        puts.add(o);
        tersimpan = {...tersimpan, 'ketua_whatsapp': '6281234567890'};
        return jsonBody(200, {'data': tersimpan});
      }
      if (o.uri.path == '/api/v1/settings/contacts') return jsonBody(200, {'data': tersimpan});
      return base(o);
    });

    expect(find.text('Nomor WhatsApp Ketua RT'), findsWidgets);
    expect(find.textContaining('halaman Kontak website'), findsWidgets);
    expect(find.text('0811-1111-1111'), findsNothing); // nomor bendahara tidak ikut tampil di halaman Ketua
    await tester.enterText(find.byKey(const ValueKey('nomor-ketua')), '0812 3456 7890');
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan nomor'));
    await _settle(tester);

    expect(puts, hasLength(1));
    expect(_bodyOf(puts.single), {'ketua_whatsapp': '0812 3456 7890'});
    expect(tester.widget<TextField>(find.byKey(const ValueKey('nomor-ketua'))).controller!.text, '0812-3456-7890');
    expect(find.text('Nomor WhatsApp Ketua RT tersimpan.'), findsOneWidget);
  });

  testWidgets('super admin menjadikan warga pengurus dengan alasan wajib', (tester) async {
    useCompactScreen(tester, height: 1400);
    final posts = <RequestOptions>[];
    var target = _target();
    await _pump(
      tester,
      const AccountDetailPage(id: 'a-2'),
      (o) {
        if (o.uri.path == '/api/v1/admin/accounts/a-2') return jsonBody(200, {'data': target});
        if (o.uri.path == '/api/v1/admin/accounts/a-2/role' && o.method == 'POST') {
          posts.add(o);
          target = _target(peran: 'pengurus');
          return jsonBody(200, {'data': target});
        }
        return jsonBody(200, {'data': {}});
      },
      saya: {
        ...akunJson(id: 'a-1', peran: 'pengurus'),
        'super_admin': true,
      },
    );

    await tester.tap(find.text('Jadikan pengurus'));
    await _settle(tester);
    await tester.enterText(find.byType(TextField).last, 'Ditetapkan rapat RT');
    await tester.tap(find.widgetWithText(FilledButton, 'Simpan'));
    await _settle(tester);

    expect(posts, hasLength(1));
    expect(_bodyOf(posts.single), {'peran': 'pengurus', 'alasan': 'Ditetapkan rapat RT'});
    expect(find.text('Turunkan jadi warga'), findsOneWidget);
    expect(find.textContaining('perlu masuk lagi'), findsOneWidget);
  });

  testWidgets('super admin: batal mengisi alasan tidak mengirim apa pun; akun nonaktif tidak bisa dinaikkan', (tester) async {
    useCompactScreen(tester, height: 1400);
    final posts = <RequestOptions>[];
    await _pump(
      tester,
      const AccountDetailPage(id: 'a-2'),
      (o) {
        if (o.uri.path == '/api/v1/admin/accounts/a-2') return jsonBody(200, {'data': _target(status: 'nonaktif')});
        if (o.method == 'POST') posts.add(o);
        return jsonBody(200, {'data': {}});
      },
      saya: {
        ...akunJson(id: 'a-1', peran: 'pengurus'),
        'super_admin': true,
      },
    );

    expect(find.text('Jadikan pengurus'), findsNothing);
    expect(find.text('Aktifkan akun dulu sebelum dijadikan pengurus.'), findsOneWidget);
    expect(posts, isEmpty);
  });

  testWidgets('pengurus biasa tidak melihat tombol peran', (tester) async {
    useCompactScreen(tester, height: 1400);
    FakeHandler handler(Map<String, dynamic> data) =>
        (o) => jsonBody(200, {'data': o.uri.path == '/api/v1/admin/accounts/a-2' ? data : {}});

    await _pump(
      tester,
      const AccountDetailPage(id: 'a-2'),
      handler(_target(peran: 'pengurus')),
      saya: {
        ...akunJson(id: 'a-1', peran: 'pengurus'),
        'super_admin': false,
      },
    );
    expect(find.text('Turunkan jadi warga'), findsNothing);
    expect(find.text('Jadikan pengurus'), findsNothing);
    expect(find.text('Peran hanya dapat diubah oleh super admin RT. Setiap pengurus memiliki hak yang sama.'), findsOneWidget);
  });

  testWidgets('super admin tidak dapat mengubah peran akunnya sendiri', (tester) async {
    useCompactScreen(tester, height: 1400);
    await _pump(
      tester,
      const AccountDetailPage(id: 'a-2'),
      (o) => jsonBody(200, {'data': o.uri.path == '/api/v1/admin/accounts/a-2' ? _target(peran: 'pengurus') : {}}),
      saya: {
        ...akunJson(id: 'a-2', peran: 'pengurus'),
        'super_admin': true,
      },
    );
    expect(find.text('Turunkan jadi warga'), findsNothing);
  });

  testWidgets('pilihan jalan Mawar 8â€“14 mengisi kolom dengan ejaan baku', (tester) async {
    final jalan = TextEditingController(text: 'jl mawar');
    addTearDown(jalan.dispose);
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              TextField(controller: jalan),
              StreetChips(controller: jalan),
            ],
          ),
        ),
      ),
    );
    for (final s in ['Mawar 8', 'Mawar 9', 'Mawar 10', 'Mawar 11', 'Mawar 12', 'Mawar 13', 'Mawar 14']) {
      expect(find.byKey(ValueKey('jalan-$s')), findsOneWidget);
    }
    await tester.tap(find.byKey(const ValueKey('jalan-Mawar 12')));
    await tester.pump();
    expect(jalan.text, 'Mawar 12');
  });

  testWidgets('daftar keluarga warga menampilkan umur anggota (regresi uji APK 1015)', (tester) async {
    final fixtures = shellFixtures();
    final anggota = ((fixtures['/api/v1/household']! as Map)['data'] as Map)['anggota'] as List;
    (anggota[0] as Map)['umur'] = 41;
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: apiHandler(me: akunJson, fixtures: fixtures),
    );
    await tester.tap(find.widgetWithText(NavigationDestination, 'Akun'));
    await tester.pumpAndSettle();
    expect(find.textContaining('41 tahun'), findsOneWidget);
    expect(find.textContaining('null tahun'), findsNothing);
  });

  test('15 avatar keluarga: setiap kode punya berkas SVG dan tidak ada berkas lain', () {
    final files = Directory('assets/avatars').listSync().whereType<File>().map((f) => f.uri.pathSegments.last).toSet();
    expect(familyAvatars, hasLength(15));
    expect(files, {for (final k in familyAvatars.keys) '$k.svg'});
  });

  testWidgets('akun utama memilih foto profil keluarga dari 15 avatar, bukan galeri', (tester) async {
    useCompactScreen(tester, height: 1400);
    final fixtures = shellFixtures();
    final puts = <RequestOptions>[];
    final base = apiHandler(me: akunJson, fixtures: fixtures);
    await pumpApp(
      tester,
      storage: MemoryTokenStorage('t'),
      handler: (o) {
        if (o.uri.path == '/api/v1/household/avatar' && o.method == 'PUT') {
          puts.add(o);
          ((fixtures['/api/v1/household']! as Map)['data'] as Map)['avatar'] = 'ibu_hijab';
          return jsonBody(200, fixtures['/api/v1/household']!);
        }
        return base(o);
      },
    );
    await tester.tap(find.widgetWithText(NavigationDestination, 'Akun'));
    await tester.pumpAndSettle();
    expect(find.bySemanticsLabel('Belum ada foto profil keluarga'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('foto-profil-keluarga')));
    await tester.pumpAndSettle();
    for (final k in familyAvatars.keys) {
      expect(find.byKey(ValueKey('avatar-$k')), findsOneWidget);
    }
    await tester.tap(find.byKey(const ValueKey('avatar-ibu_hijab')));
    await tester.pumpAndSettle();
    expect(puts, hasLength(1));
    expect(_bodyOf(puts.single), {'avatar': 'ibu_hijab'});
    expect(find.text('Foto profil keluarga tersimpan.'), findsOneWidget);
    expect(find.bySemanticsLabel('Foto profil keluarga: Ibu berhijab'), findsOneWidget);
  });
}
