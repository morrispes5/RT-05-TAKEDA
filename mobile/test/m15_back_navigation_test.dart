import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// UAT M15 (20 Okt): tombol Kembali Android. Dari tab mana pun kembali ke dashboard; dari dashboard
/// meminta konfirmasi sebelum aplikasi ditutup.
void main() {
  for (final peran in ['warga', 'pengurus']) {
    testWidgets('kembali ($peran): tab lain → dashboard, dashboard → konfirmasi keluar', (tester) async {
      final closed = <MethodCall>[];
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        if (call.method == 'SystemNavigator.pop') closed.add(call);
        return null;
      });
      addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));

      await pumpApp(
        tester,
        storage: MemoryTokenStorage('t'),
        handler: apiHandler(me: () => akunJson(peran: peran)),
      );
      final first = peran == 'warga' ? 'Beranda' : 'Ringkasan';
      final other = peran == 'warga' ? 'Iuran' : 'Keuangan';
      Finder title(String text) => find.descendant(of: find.byType(AppBar), matching: find.text(text));

      await tester.tap(find.widgetWithText(NavigationDestination, other));
      await tester.pumpAndSettle();
      expect(title(other), findsOneWidget);

      // Kembali dari tab lain: ke dashboard, tanpa dialog dan tanpa menutup aplikasi.
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(title(first), findsOneWidget);
      expect(find.text('Keluar dari aplikasi?'), findsNothing);
      expect(closed, isEmpty);

      // Kembali dari dashboard: konfirmasi. Batal → tetap di aplikasi.
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      expect(find.text('Keluar dari aplikasi?'), findsOneWidget);
      await tester.tap(find.text('Batal'));
      await tester.pumpAndSettle();
      expect(find.text('Keluar dari aplikasi?'), findsNothing);
      expect(title(first), findsOneWidget);
      expect(closed, isEmpty);

      // Konfirmasi → aplikasi ditutup (sesi tidak dikeluarkan).
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      await tester.tap(find.text('Keluar aplikasi'));
      await tester.pumpAndSettle();
      expect(closed, hasLength(1));
    });
  }
}
