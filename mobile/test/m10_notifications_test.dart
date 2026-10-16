import 'dart:async';
import 'dart:convert';

import 'package:dio/dio.dart' show RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/push.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';
import 'package:rt05_takeda/features/warga/notification_center.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// M10: pengaturan push, tautan inbox/push yang aman, form agenda zona waktu, pendaftaran token.
const _agendaId = '0199f0a1-1111-7000-8000-000000000001';

class FakePushClient implements PushClient {
  FakePushClient(this.state, {this.tokenValue = 'fcm-token-uji'});
  PushPermission state;
  final String? tokenValue;
  int asked = 0;

  @override
  bool get available => true;
  @override
  Future<PushPermission> permission() async => state;
  @override
  Future<PushPermission> requestPermission() async {
    asked++;
    if (state == PushPermission.notDetermined) state = PushPermission.granted;
    return state;
  }

  @override
  Future<String?> token() async => tokenValue;
  @override
  Stream<String> get tokenRefresh => const Stream.empty();
  @override
  Stream<PushTap> get foreground => const Stream.empty();
  @override
  Stream<PushTap> get opened => const Stream.empty();
  @override
  Future<PushTap?> initialTap() async => null;
}

class FixedDeviceId implements DeviceIdStore {
  @override
  Future<String> read() async => 'android-uji-0001';
}

Map<String, dynamic> _body(Object? data) => Map<String, dynamic>.from(data is String ? jsonDecode(data) as Map : data as Map);

Future<FakeAdapter> _pump(WidgetTester tester, Widget home, FakeHandler handler, {PushClient push = const NoPushClient()}) async {
  final adapter = FakeAdapter(handler);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(adapter),
        pushClientProvider.overrideWithValue(push),
        deviceIdStoreProvider.overrideWithValue(FixedDeviceId()),
      ],
      child: MaterialApp(theme: buildTheme(), home: home),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 300));
  return adapter;
}

Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

final _prefs = {'pengumuman': true, 'agenda': true, 'pengaduan': true, 'aspirasi': true, 'iuran': true};

void main() {
  test('data push hanya dipercaya untuk jenis tetap dan ID UUID', () {
    final ok = PushTap.fromData({'notifikasi_id': _agendaId, 'jenis': 'agenda', 'ref_type': 'agenda', 'ref_id': _agendaId});
    expect([ok.refType, ok.refId, ok.notifikasiId], ['agenda', _agendaId, _agendaId]);
    final forged = PushTap.fromData({'notifikasi_id': '../x', 'ref_type': 'akun', 'ref_id': _agendaId});
    expect([forged.refType, forged.refId, forged.notifikasiId], [null, null, null]);
    final badId = PushTap.fromData({'ref_type': 'pengaduan', 'ref_id': '1 OR 1=1'});
    expect(badId.refId, isNull);
  });

  test('validasi agenda lokal: waktu mulai di masa depan dan selesai setelah mulai', () {
    final now = DateTime(2026, 10, 17, 10);
    final errors = validateAgenda(
      nama: 'ab',
      lokasi: '',
      deskripsi: ' ',
      mulai: DateTime(2026, 10, 17, 9),
      selesai: DateTime(2026, 10, 17, 8),
      nowJakarta: now,
    );
    expect(errors.keys, containsAll(['nama', 'lokasi', 'deskripsi', 'mulai_at', 'selesai_at']));
    expect(
      validateAgenda(nama: 'Rapat', lokasi: 'Balai', deskripsi: 'x', mulai: DateTime(2026, 10, 17, 9), nowJakarta: now, requireFuture: false),
      isEmpty,
      reason: 'ubah deskripsi agenda lewat tetap boleh',
    );
  });

  test('pendaftaran push: izin diberikan → token dikirim; ditolak → tidak ada request', () async {
    for (final (state, expectPut) in [(PushPermission.notDetermined, true), (PushPermission.denied, false)]) {
      final adapter = FakeAdapter(
        (o) => jsonBody(200, {
          'data': {'device_identifier': 'android-uji-0001', 'platform': 'android', 'last_seen_at': null},
        }),
      );
      final client = FakePushClient(state);
      final container = ProviderContainer(
        overrides: [
          appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
          tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
          httpAdapterProvider.overrideWithValue(adapter),
          pushClientProvider.overrideWithValue(client),
          deviceIdStoreProvider.overrideWithValue(FixedDeviceId()),
        ],
      );
      final result = await container.read(pushServiceProvider).register();
      final puts = adapter.requests.where((r) => r.method == 'PUT').toList();
      expect(puts.length, expectPut ? 1 : 0, reason: '$state');
      if (expectPut) {
        expect(result, PushPermission.granted);
        expect(puts.single.path, endsWith('/devices/android-uji-0001'));
        expect(_body(puts.single.data), {'fcm_token': 'fcm-token-uji', 'platform': 'android'});
      } else {
        expect(result, PushPermission.denied);
        expect(client.asked, 0, reason: 'izin ditolak tidak diminta berulang');
      }
      container.dispose();
    }
  });

  testWidgets('pengaturan: push belum tersedia dijelaskan, preferensi tersimpan dengan alert', (tester) async {
    useCompactScreen(tester, height: 1200);
    final patches = <Map<String, dynamic>>[];
    await _pump(tester, const NotificationSettingsPage(), (o) {
      if (o.method == 'PATCH') {
        patches.add(_body(o.data));
        return jsonBody(200, {
          'data': {..._prefs, ..._body(o.data)},
        });
      }
      return jsonBody(200, {'data': _prefs});
    });
    await _settle(tester);

    expect(find.text('Push belum tersedia'), findsOneWidget);
    expect(find.textContaining('tetap masuk ke kotak masuk'), findsWidgets);
    await tester.tap(find.byKey(const ValueKey('pref-pengumuman')));
    await _settle(tester);
    expect(patches, [
      {'pengumuman': false},
    ]);
    expect(find.byKey(const ValueKey('alert-success')), findsOneWidget);
    expect(find.textContaining('tetap ada di kotak masuk'), findsOneWidget);
  });

  testWidgets('pengaturan: izin ditolak memberi panduan, layanan tetap jalan', (tester) async {
    useCompactScreen(tester, height: 1200);
    await _pump(tester, const NotificationSettingsPage(), (o) => jsonBody(200, {'data': _prefs}), push: FakePushClient(PushPermission.denied));
    await _settle(tester);
    expect(find.text('Diblokir'), findsOneWidget);
    expect(find.textContaining('Pengaturan HP'), findsOneWidget);
    expect(find.text('Izinkan notifikasi'), findsNothing);
  });

  testWidgets('pengaturan: izin belum ditentukan → tombol izinkan mendaftarkan HP', (tester) async {
    useCompactScreen(tester, height: 1200);
    final client = FakePushClient(PushPermission.notDetermined);
    final adapter = await _pump(tester, const NotificationSettingsPage(), (o) {
      final device = {'device_identifier': 'android-uji-0001', 'platform': 'android', 'last_seen_at': null};
      return jsonBody(200, {'data': o.method == 'PUT' ? device : _prefs});
    }, push: client);
    await _settle(tester);
    await tester.tap(find.text('Izinkan notifikasi'));
    await _settle(tester);
    expect(client.asked, 1);
    expect(adapter.requests.where((r) => r.method == 'PUT').length, 1);
    expect(find.text('Aktif'), findsOneWidget);
    expect(find.byKey(const ValueKey('alert-success')), findsOneWidget);
  });

  testWidgets('tautan notifikasi ke agenda terarsip menampilkan pesan aman, bukan data payload', (tester) async {
    useCompactScreen(tester);
    await _pump(tester, const AgendaByIdPage(id: _agendaId), (o) => errorBody(404, 'NOT_FOUND', 'Data tidak ditemukan.'));
    await _settle(tester);
    expect(find.text('Agenda tidak lagi tersedia'), findsOneWidget);
  });

  testWidgets('tautan agenda valid memuat detail dari API', (tester) async {
    useCompactScreen(tester);
    await _pump(
      tester,
      const AgendaByIdPage(id: _agendaId),
      (o) => jsonBody(200, {
        'data': {
          'id': _agendaId,
          'nama': 'Badminton warga',
          'deskripsi': 'Terbuka untuk semua.',
          'lokasi': 'Lapangan RT 05',
          'mulai_at': '2026-10-23T02:00:00Z',
          'selesai_at': '2026-10-23T04:00:00Z',
          'google_calendar_url': 'https://calendar.google.com/calendar/render?action=TEMPLATE',
          'ics_url': '/api/v1/agendas/$_agendaId/calendar',
          'archived_at': null,
        },
      }),
    );
    await _settle(tester);
    expect(find.text('Badminton warga'), findsOneWidget);
    expect(find.textContaining('09.00'), findsWidgets, reason: '02:00Z ditampilkan 09.00 WIB');
  });

  testWidgets('form agenda: waktu lewat ditolak lokal; terbit mengirim UTC berzona dan menampilkan hasil', (tester) async {
    useCompactScreen(tester, height: 1400);
    final posts = <Map<String, dynamic>>[];
    final completer = Completer<void>();
    await _pump(tester, AgendaFormPage(now: () => DateTime(2026, 10, 17, 10)), (RequestOptions o) {
      posts.add(_body(o.data));
      if (!completer.isCompleted) completer.complete();
      return jsonBody(201, {
        'data': {'id': _agendaId},
      });
    });
    await tester.enterText(find.byKey(const ValueKey('agenda-nama')), 'Badminton warga');
    await tester.enterText(find.byKey(const ValueKey('agenda-lokasi')), 'Lapangan RT 05');
    await tester.enterText(find.byKey(const ValueKey('agenda-deskripsi')), 'Terbuka untuk semua umur.');
    await tester.tap(find.text('Terbitkan agenda'));
    await _settle(tester);
    // Default besok 08.00 WIB → konfirmasi, lalu POST.
    expect(find.text('Terbitkan agenda?'), findsOneWidget);
    await tester.tap(find.text('Terbitkan'));
    await _settle(tester);
    expect(posts.single['mulai_at'], '2026-10-18T01:00:00.000Z');
    expect(posts.single['selesai_at'], isNull);
    expect(find.text('Agenda diterbitkan'), findsOneWidget);
  });

  testWidgets('form agenda: error server per field tampil inline', (tester) async {
    useCompactScreen(tester, height: 1400);
    await _pump(
      tester,
      AgendaFormPage(now: () => DateTime(2026, 10, 17, 10)),
      (o) => jsonBody(422, {
        'error': {
          'code': 'VALIDATION_FAILED',
          'message': 'Data tidak valid.',
          'fields': {
            'mulai_at': ['Waktu mulai harus setelah saat ini.'],
          },
          'request_id': '00000000-0000-4000-8000-000000000000',
        },
      }),
    );
    await tester.enterText(find.byKey(const ValueKey('agenda-nama')), 'Badminton warga');
    await tester.enterText(find.byKey(const ValueKey('agenda-lokasi')), 'Lapangan RT 05');
    await tester.enterText(find.byKey(const ValueKey('agenda-deskripsi')), 'Terbuka.');
    await tester.tap(find.text('Terbitkan agenda'));
    await _settle(tester);
    await tester.tap(find.text('Terbitkan'));
    await _settle(tester);
    expect(find.text('Waktu mulai harus setelah saat ini.'), findsWidgets);
    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
  });

  for (final width in [360.0, 320.0]) {
    testWidgets('pengaturan notifikasi dan form agenda skala teks 2.0 lebar $width', (tester) async {
      useCompactScreen(tester, width: width, height: 2400, textScale: 2.0);
      await _pump(tester, const NotificationSettingsPage(), (o) => jsonBody(200, {'data': _prefs}), push: FakePushClient(PushPermission.denied));
      await _settle(tester);
      expect(tester.takeException(), isNull);
      await _pump(tester, AgendaFormPage(now: () => DateTime(2026, 10, 17, 10)), (o) => jsonBody(200, {'data': {}}));
      await _settle(tester);
      expect(tester.takeException(), isNull);
    });
  }
}
