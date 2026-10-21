import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/push.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/core/whatsapp.dart';
import 'package:rt05_takeda/features/household/admin_census.dart';
import 'package:rt05_takeda/features/household/family_actions.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';
import 'package:rt05_takeda/main.dart';

import 'support/fakes.dart';

/// Temuan uji emulator APK 1010 (22 Okt 2026): tombol pengurus di detail rumah, izin notifikasi setelah Kebijakan
/// Privasi, bulan dari bukti langsung tercentang, nomor bendahara terbaca manusia, label anggota tidak dobel.
class _Push implements PushClient {
  int asked = 0;
  PushPermission state = PushPermission.notDetermined;
  @override
  bool get available => true;
  @override
  Future<PushPermission> permission() async => state;
  @override
  Future<PushPermission> requestPermission() async {
    asked++;
    return state = PushPermission.granted;
  }

  @override
  Future<String?> token() async => 'fcm-uji';
  @override
  Stream<String> get tokenRefresh => const Stream.empty();
  @override
  Stream<PushTap> get foreground => const Stream.empty();
  @override
  Stream<PushTap> get opened => const Stream.empty();
  @override
  Future<PushTap?> initialTap() async => null;
}

class _DeviceId implements DeviceIdStore {
  @override
  Future<String> read() async => 'android-uji-1011';
}

Future<void> _pump(WidgetTester tester, Widget home, FakeHandler handler) async {
  tester.view.physicalSize = const Size(1200, 3600);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
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
  await tester.pumpAndSettle();
}

void main() {
  test('nomor wa.me ditampilkan dalam format lokal yang mudah dibaca', () {
    expect(waDisplay('6287772283010'), '0877-7228-3010');
    expect(waDisplay('0812 3456 7890'), '0812-3456-7890');
    expect(waDisplay('6281234567'), '0812-3456-7');
    expect(waDisplay(null), '');
  });

  test('label anggota tidak menulis "kepala keluarga" dua kali', () {
    expect(memberSubtitle({'hubungan_keluarga': 'Kepala keluarga', 'is_kepala': true, 'punya_akun': true}), 'Kepala keluarga • punya akun');
    expect(memberSubtitle({'hubungan_keluarga': 'Suami', 'is_kepala': true}), 'Suami • kepala keluarga');
    expect(memberSubtitle({'hubungan_keluarga': 'Anak', 'whatsapp': '0812'}, phone: true), 'Anak • 0812');
  });

  testWidgets('detail rumah pengurus: tombol WhatsApp per anggota, Data kependudukan, dan kelola keluarga', (tester) async {
    await _pump(tester, HouseAdminPage(summary: const {'id': 'h-1', 'kode_rumah': 'D5-21'}, onRecordPayment: (_) async {}), (o) {
      if (o.uri.path == '/api/v1/admin/houses/h-1') {
        return jsonBody(200, {
          'data': {
            'id': 'h-1',
            'kode_rumah': 'D5-21',
            'alamat': 'Blok D5 No. 21',
            'tagihan_aktif': true,
            'mulai_tagih': null,
            'versi': 2,
            'archived_at': null,
            'pemilik': null,
            'riwayat_penghunian': [],
            'keluarga': {
              'id': 'k-1',
              'status': 'terverifikasi',
              'versi': 3,
              'anggota': [
                {
                  'id': 'w-1',
                  'nama': 'Kepala Uji',
                  'hubungan_keluarga': 'Kepala keluarga',
                  'is_kepala': true,
                  'whatsapp': '0812 1111 2222',
                  'punya_akun': true,
                },
                {'id': 'w-2', 'nama': 'Anak Uji', 'hubungan_keluarga': 'Anak', 'is_kepala': false, 'whatsapp': null, 'punya_akun': false},
              ],
            },
          },
        });
      }
      if (o.uri.path == '/api/v1/admin/houses/h-1/dues') {
        return jsonBody(200, {
          'data': {'tagihan': []},
        });
      }
      return jsonBody(200, {'data': {}});
    });
    expect(find.byTooltip('Chat WhatsApp Kepala Uji'), findsOneWidget);
    expect(find.byTooltip('Chat WhatsApp Anak Uji'), findsNothing);
    expect(find.byKey(const ValueKey('rumah-data-kependudukan')), findsOneWidget);
    expect(find.byKey(const ValueKey('rumah-kelola-keluarga')), findsOneWidget);
    expect(find.textContaining('kepala keluarga • kepala keluarga'), findsNothing);
  });

  testWidgets('catat pembayaran dari bukti: bulan yang diklaim langsung tercentang, yang sudah lunas tidak', (tester) async {
    await _pump(
      tester,
      const RecordPaymentPage(house: {'id': 'h-1', 'kode_rumah': 'D5-21', 'alamat': 'Blok D5 No. 21'}, initialPeriods: ['2026-10', '2026-09']),
      (o) {
        if (o.uri.path == '/api/v1/admin/houses') {
          return jsonBody(200, {
            'data': [
              {'id': 'h-1', 'kode_rumah': 'D5-21', 'alamat': 'Blok D5 No. 21'},
            ],
            'meta': {'current_page': 1, 'last_page': 1, 'per_page': 50, 'total': 1},
          });
        }
        if (o.uri.path == '/api/v1/admin/houses/h-1/dues') {
          return jsonBody(200, {
            'data': {
              'tagihan': [
                {'id': 't-9', 'periode': '2026-09', 'nominal': 75000, 'status': 'lunas', 'status_label': 'Lunas'},
                {'id': 't-10', 'periode': '2026-10', 'nominal': 75000, 'status': 'belum_bayar', 'status_label': 'Belum dibayar'},
                {'id': 't-11', 'periode': '2026-11', 'nominal': 75000, 'status': 'belum_bayar', 'status_label': 'Belum dibayar'},
              ],
            },
          });
        }
        return jsonBody(200, {'data': {}});
      },
    );
    final boxes = tester.widgetList<CheckboxListTile>(find.byType(CheckboxListTile)).toList();
    expect(boxes.length, 2, reason: 'hanya bulan belum lunas yang tampil');
    expect(boxes.map((b) => b.value).toList(), [true, false], reason: 'Oktober dari bukti tercentang, November tidak');
  });

  testWidgets('izin notifikasi baru diminta setelah Kebijakan Privasi disetujui', (tester) async {
    final push = _Push();
    Map<String, dynamic> me = {...akunJson(), 'privasi_versi': '2026-10-21', 'privasi_perlu_setuju': true};
    final container = ProviderContainer(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(apiHandler(me: () => me))),
        pushClientProvider.overrideWithValue(push),
        deviceIdStoreProvider.overrideWithValue(_DeviceId()),
      ],
    );
    addTearDown(container.dispose);
    await tester.pumpWidget(UncontrolledProviderScope(container: container, child: const Rt05App()));
    await tester.pumpAndSettle();
    expect(container.read(sessionProvider).needsPrivacy, isTrue);
    expect(find.text('Kebijakan Privasi'), findsWidgets);
    expect(push.asked, 0, reason: 'izin tidak menimpa halaman kebijakan');

    me = {...me, 'privasi_perlu_setuju': false};
    final session = container.read(sessionProvider.notifier);
    session.updateAkun(Akun(me), epoch: session.epoch);
    await tester.pumpAndSettle();
    expect(push.asked, 1, reason: 'izin diminta sekali setelah setuju');
  });
}
