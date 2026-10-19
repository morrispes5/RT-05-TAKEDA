import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/report_draft.dart';
import 'package:rt05_takeda/features/warga/warga_pages.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// Hanya untuk tes: draf di memori.
class MemoryDraftStore implements ReportDraftStore {
  MemoryDraftStore([this.draft]);
  ReportDraft? draft;
  final log = <String>[];

  @override
  Future<ReportDraft?> read() async => draft;

  @override
  Future<void> write(ReportDraft d) async {
    log.add('write:${d.kamera}:${d.judul}');
    draft = d;
  }

  @override
  Future<void> clear() async {
    log.add('clear');
    draft = null;
  }
}

/// Kamera yang "berhasil" kembali normal (aplikasi tidak ditutup Android).
class _CameraPicker extends Fake implements ImagePicker {
  _CameraPicker(this.path);
  final String path;
  ReportDraft? seenDraft;
  MemoryDraftStore? store;

  @override
  Future<XFile?> pickImage({
    required ImageSource source,
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    CameraDevice preferredCameraDevice = CameraDevice.rear,
    bool requestFullMetadata = true,
  }) async {
    seenDraft = store?.draft;
    return XFile(path);
  }
}

final _categories = {
  '/api/v1/complaint-categories': {
    'data': [
      {'id': 'k-1', 'nama': 'Kebersihan'},
    ],
  },
};

Future<ProviderContainer> _pump(
  WidgetTester tester, {
  required MemoryDraftStore store,
  ImagePicker? picker,
  List<XFile> lost = const [],
  FakeHandler? handler,
}) async {
  final container = ProviderContainer(
    overrides: [
      appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
      tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
      httpAdapterProvider.overrideWithValue(FakeAdapter(handler ?? apiHandler(me: akunJson, fixtures: _categories))),
      reportDraftStoreProvider.overrideWithValue(store),
      lostPhotosProvider.overrideWithValue((_) async => lost),
    ],
  );
  addTearDown(container.dispose);
  await container.read(sessionProvider.notifier).signIn('t', Akun(akunJson(id: 'a-1')));
  await tester.pumpWidget(
    UncontrolledProviderScope(
      container: container,
      child: MaterialApp(
        theme: buildTheme(),
        home: ReportFormPage(aspirasi: false, picker: picker),
      ),
    ),
  );
  await tester.pumpAndSettle();
  return container;
}

void main() {
  late Directory tmp;
  setUp(() => tmp = Directory.systemTemp.createTempSync('rt05-m15-'));
  tearDown(() => tmp.deleteSync(recursive: true));

  testWidgets('Android menutup aplikasi saat kamera terbuka: isian dan foto kamera dipulihkan', (tester) async {
    useCompactScreen(tester, height: 1400);
    final before = File('${tmp.path}/galeri.jpg')..writeAsBytesSync([1, 2, 3]);
    final store = MemoryDraftStore(
      ReportDraft(
        akunId: 'a-1',
        kategori: 'k-1',
        judul: 'Saluran tersumbat',
        isi: 'Air meluap di depan rumah A-01.',
        anonim: true,
        photos: [before.path],
        kamera: true,
      ),
    );
    await _pump(tester, store: store, lost: [XFile('${tmp.path}/kamera.jpg')]);

    expect(find.text('Saluran tersumbat'), findsOneWidget);
    expect(find.text('Air meluap di depan rumah A-01.'), findsOneWidget);
    expect(find.text('Foto 1'), findsOneWidget);
    expect(find.text('Foto 2'), findsOneWidget);
    expect(find.textContaining('dipulihkan'), findsOneWidget);
    expect(store.draft, isNull, reason: 'draf dihapus setelah dipulihkan ke memori');
  });

  testWidgets('draf milik akun lain di HP yang sama tidak ditampilkan dan dihapus', (tester) async {
    useCompactScreen(tester, height: 1400);
    final store = MemoryDraftStore(const ReportDraft(akunId: 'a-lain', judul: 'Rahasia akun lain', isi: 'Isi akun lain.', kamera: true));
    await _pump(tester, store: store, lost: [XFile('${tmp.path}/kamera.jpg')]);

    expect(find.text('Rahasia akun lain'), findsNothing);
    expect(find.text('Foto 1'), findsNothing);
    expect(store.draft, isNull);
  });

  testWidgets('draf ditulis sebelum kamera dibuka lalu dihapus bila kamera kembali normal', (tester) async {
    useCompactScreen(tester, height: 1400);
    final store = MemoryDraftStore();
    final picker = _CameraPicker('${tmp.path}/kamera.jpg')..store = store;
    await _pump(tester, store: store, picker: picker);

    await tester.enterText(find.byType(TextField).first, 'Lampu jalan mati');
    await tester.tap(find.text('Kamera'));
    await tester.pumpAndSettle();

    expect(picker.seenDraft?.kamera, isTrue);
    expect(picker.seenDraft?.judul, 'Lampu jalan mati');
    expect(find.text('Foto 1'), findsOneWidget);
    expect(store.draft, isNull);
    expect(store.log, ['write:true:Lampu jalan mati', 'clear']);
  });
}
