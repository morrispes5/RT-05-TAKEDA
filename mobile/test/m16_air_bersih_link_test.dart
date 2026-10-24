import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/features/pengurus/air_bersih_link.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// ADR41: menu Lainnya pengurus "Air Bersih KSM" berlogo KSM Banyu Bening, tanpa ikon panah, membuka aplikasi
/// terpisah itu di browser. Warga tidak pernah melihatnya di tab mana pun.
Future<void> _pump(WidgetTester tester, Widget child) async {
  await tester.pumpWidget(
    ProviderScope(
      child: MaterialApp(
        theme: buildTheme(),
        home: Scaffold(body: child),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('Lainnya pengurus menampilkan menu Air Bersih KSM berlogo, tanpa panah', (tester) async {
    await _pump(tester, const MoreAdminPage());
    final tile = find.byKey(const ValueKey('air-bersih-ksm'));
    await tester.scrollUntilVisible(tile, 200);
    expect(find.text('Air bersih KSM'), findsOneWidget);
    expect(find.descendant(of: tile, matching: find.text('Air Bersih KSM')), findsOneWidget);
    expect(find.text('Tagihan air KSM'), findsNothing);
    expect(find.descendant(of: tile, matching: find.byIcon(Icons.open_in_new)), findsNothing);
    final avatar = tester.widget<CircleAvatar>(find.descendant(of: tile, matching: find.byType(CircleAvatar)));
    expect((avatar.backgroundImage! as AssetImage).assetName, airBersihKsmLogo);
  });

  for (final (role, tabs, seen) in [
    ('warga', ['Beranda', 'Layanan', 'Agenda', 'Iuran', 'Akun'], false),
    ('pengurus', ['Ringkasan', 'Warga', 'Layanan', 'Keuangan', 'Lainnya'], true),
  ]) {
    testWidgets('aplikasi utuh: menu Air Bersih KSM ${seen ? 'ada' : 'tidak ada'} untuk $role', (tester) async {
      await pumpApp(
        tester,
        storage: MemoryTokenStorage('t'),
        handler: apiHandler(me: () => akunJson(peran: role)),
      );
      for (final tab in tabs) {
        await tester.tap(find.widgetWithText(NavigationDestination, tab));
        await tester.pumpAndSettle();
      }
      final tile = find.byKey(const ValueKey('air-bersih-ksm'), skipOffstage: false);
      if (seen) await tester.scrollUntilVisible(tile, 200, scrollable: find.byType(Scrollable).last);
      expect(tile, seen ? findsOneWidget : findsNothing);
      expect(find.text('Air Bersih KSM', skipOffstage: false), seen ? findsOneWidget : findsNothing);
    });
  }

  test('logo terbundel dan byte-identik dengan website', () async {
    final website = File('../website/public/images/logo/air-bersih-ksm.png').readAsBytesSync();
    expect(File(airBersihKsmLogo).readAsBytesSync(), website);
    TestWidgetsFlutterBinding.ensureInitialized();
    final bundled = await rootBundle.load(airBersihKsmLogo);
    expect(bundled.buffer.asUint8List(), website);
  });

  testWidgets('ketuk membuka situs air bersih', (tester) async {
    final opened = <Uri>[];
    await _pump(
      tester,
      AirBersihKsmTile(
        open: (uri) async {
          opened.add(uri);
          return true;
        },
      ),
    );
    await tester.tap(find.byKey(const ValueKey('air-bersih-ksm')));
    await tester.pumpAndSettle();
    expect(opened, [Uri.parse('https://airbersih-ksm-claude.vercel.app')]);
    expect(find.textContaining('Tidak dapat membuka'), findsNothing);
  });

  testWidgets('gagal membuka (atau galat) menampilkan pesan', (tester) async {
    for (final open in <UrlOpener>[(_) async => false, (_) async => throw Exception('tidak ada browser')]) {
      await _pump(tester, AirBersihKsmTile(open: open));
      await tester.tap(find.byKey(const ValueKey('air-bersih-ksm')));
      await tester.pump();
      expect(find.text('Tidak dapat membuka airbersih-ksm-claude.vercel.app'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
      await tester.pumpAndSettle();
    }
  });
}
