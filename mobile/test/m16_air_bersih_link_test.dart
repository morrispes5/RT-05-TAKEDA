import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/features/pengurus/air_bersih_link.dart';
import 'package:rt05_takeda/features/pengurus/pengurus_pages.dart';

/// ADR40: menu Lainnya pengurus menautkan Air Bersih KSM (aplikasi terpisah) dan membukanya di browser.
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
  testWidgets('Lainnya pengurus menampilkan tautan Air bersih KSM', (tester) async {
    await _pump(tester, const MoreAdminPage());
    await tester.scrollUntilVisible(find.byKey(const ValueKey('air-bersih-ksm')), 200);
    expect(find.text('Air bersih KSM'), findsOneWidget);
    expect(find.text('Tagihan air KSM'), findsOneWidget);
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
