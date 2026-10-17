import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart' show FormData, RequestOptions;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/app/theme.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/pengurus/content_admin.dart';

import 'support/app_harness.dart';
import 'support/fakes.dart';

/// M13 pengurus: daftar konten, editor draf → terbit, foto album dengan teks alternatif, profil.
const _id = '0199f0a1-3333-7000-8000-000000000001';
const _mediaId = '0199f0a1-4444-7000-8000-000000000001';

Map<String, dynamic> _admin({String status = 'draf', int versi = 1, String slug = 'kerja-bakti', Map? draf, bool changed = false, String jenis = 'artikel'}) =>
    {
      'id': _id,
      'jenis': jenis,
      'slug': slug,
      'status': status,
      'versi': versi,
      'draf': draf ?? {'title': 'Kerja bakti bulanan'},
      'slug_terbit': status == 'terbit' ? slug : null,
      'ada_perubahan_belum_terbit': changed,
      'sumber': 'pengurus',
      'published_at': null,
      'updated_at': '2026-10-17T03:00:00Z',
      'archived_at': null,
    };

Map<String, dynamic> _body(Object? data) => Map<String, dynamic>.from(data is String ? jsonDecode(data) as Map : data as Map);

class _GalleryPicker extends Fake implements ImagePicker {
  _GalleryPicker(this.path);
  final String path;
  @override
  Future<XFile?> pickImage({
    required ImageSource source,
    double? maxWidth,
    double? maxHeight,
    int? imageQuality,
    CameraDevice preferredCameraDevice = CameraDevice.rear,
    bool requestFullMetadata = true,
  }) async => XFile(path);
}

Future<FakeAdapter> _pump(WidgetTester tester, Widget home, FakeHandler handler) async {
  final adapter = FakeAdapter(handler);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(adapter),
      ],
      child: MaterialApp(theme: buildTheme(), home: home),
    ),
  );
  await _settle(tester);
  return adapter;
}

Future<void> _settle(WidgetTester tester) async {
  for (var i = 0; i < 8; i++) {
    await tester.pump(const Duration(milliseconds: 150));
  }
}

Future<void> _type(WidgetTester tester, String key, String text) async {
  final f = find.byKey(ValueKey('f-$key'));
  await tester.ensureVisible(f);
  await tester.enterText(f, text);
}

Future<void> _tapText(WidgetTester tester, String text) async {
  await tester.ensureVisible(find.text(text).last);
  await tester.tap(find.text(text).last);
  await _settle(tester);
}

void main() {
  test('slug dari judul dan label status konten', () {
    expect(slugify('Kerja Bakti Bulanan!'), 'kerja-bakti-bulanan');
    expect(slugify('  RT 05 — 17 Agustus  '), 'rt-05-17-agustus');
    expect(slugify('a' * 130).length, 120);
    expect(contentStatusLabel(_admin(status: 'terbit', changed: true)), 'Terbit • ada draf baru');
    expect(contentStatusLabel(_admin(status: 'arsip')), 'Diarsipkan');
    expect(contentStatusLabel(_admin()), 'Draf');
  });

  testWidgets('daftar konten menampilkan status terbit dan draf yang belum terbit', (tester) async {
    useCompactScreen(tester, height: 1400);
    await _pump(
      tester,
      const ContentAdminPage(),
      (o) => jsonBody(200, {
        'data': [_admin(status: 'terbit', changed: true)],
        'meta': {'current_page': 1, 'per_page': 20, 'total': 1},
      }),
    );
    expect(find.text('Kerja bakti bulanan'), findsOneWidget);
    expect(find.text('/kerja-bakti'), findsOneWidget);
    expect(find.text('Terbit • ada draf baru'), findsOneWidget);
    expect(find.text('Artikel baru'), findsOneWidget);
    // Keempat tab terlihat di 360 dp dan tab Profil dapat diketuk.
    expect(tester.getRect(find.widgetWithText(Tab, 'Profil')).right, lessThanOrEqualTo(360));
    await tester.tap(find.widgetWithText(Tab, 'Profil'));
    await _settle(tester);
    expect(find.byType(SiteProfileEditor), findsOneWidget);
  });

  testWidgets('artikel baru: slug dari judul, terbitkan menyimpan draf lalu menerbitkan, dialog hasil', (tester) async {
    useCompactScreen(tester, height: 2600);
    final posts = <RequestOptions>[];
    await _pump(tester, const ContentEditorPage(type: 'articles'), (o) {
      posts.add(o);
      if (o.uri.path.endsWith('/publish')) return jsonBody(200, {'data': _admin(status: 'terbit')});
      return jsonBody(201, {'data': _admin(draf: _body(o.data)['draf'] as Map)});
    });
    await _type(tester, 'title', 'Kerja Bakti Bulanan');
    await tester.tap(find.byTooltip('Buat dari judul'));
    await _settle(tester);
    await _type(tester, 'category', 'Lingkungan');
    await _type(tester, 'summary', 'Kerja bakti rutin bersama warga RT 05.');
    await _type(tester, 'intro', 'Warga diajak membersihkan saluran air.');
    await _tapText(tester, 'Tambah bagian');
    await _type(tester, 'sections.0.title', 'Waktu');
    await _type(tester, 'sections.0.body', 'Minggu pertama setiap bulan pukul 07.00.');
    await _tapText(tester, 'Terbitkan');
    expect(find.text('Terbitkan Artikel?'), findsOneWidget);
    await _tapText(tester, 'Terbitkan');

    expect(posts.map((p) => p.uri.path.replaceFirst('/api/v1', '')).toList(), ['/admin/articles', '/admin/articles/$_id/publish']);
    final created = _body(posts.first.data);
    expect(created['slug'], 'kerja-bakti-bulanan');
    expect((created['draf'] as Map)['sections'], [
      {'title': 'Waktu', 'body': 'Minggu pertama setiap bulan pukul 07.00.'},
    ]);
    expect(_body(posts.last.data), {'versi': 1});
    expect(find.text('Artikel diterbitkan'), findsOneWidget);
  });

  testWidgets('kesalahan server tampil di field dan konflik versi berwarna peringatan', (tester) async {
    useCompactScreen(tester, height: 2600);
    var calls = 0;
    await _pump(
      tester,
      ContentEditorPage(
        type: 'articles',
        initial: _admin(draf: {'title': 'Kerja bakti', 'sections': []}),
      ),
      (o) {
        calls++;
        if (calls == 1) {
          return jsonBody(422, {
            'error': {
              'code': 'VALIDATION_FAILED',
              'message': 'Data tidak valid.',
              'fields': {
                'summary': ['Ringkasan minimal 10 karakter.'],
              },
              'request_id': '00000000-0000-4000-8000-000000000000',
            },
          });
        }
        return errorBody(409, 'VERSION_CONFLICT', 'Konten sudah diubah pengurus lain. Muat ulang lalu ulangi perubahan Anda.');
      },
    );
    await _tapText(tester, 'Simpan draf');
    expect(find.text('Ringkasan minimal 10 karakter.'), findsWidgets, reason: 'di field dan di alert');
    expect(find.byKey(const ValueKey('alert-error')), findsOneWidget);
    await _tapText(tester, 'Simpan draf');
    expect(find.byKey(const ValueKey('alert-warning')), findsOneWidget);
  });

  testWidgets('album: foto diunggah, teks alternatif dan sampul ikut di draf', (tester) async {
    useCompactScreen(tester, height: 2600);
    final file = File('${Directory.systemTemp.path}/m13-foto-uji.jpg')..writeAsBytesSync(List.filled(64, 1));
    final requests = <RequestOptions>[];
    await _pump(tester, ContentEditorPage(type: 'albums', picker: _GalleryPicker(file.path)), (o) {
      requests.add(o);
      if (o.uri.path.endsWith('/content-media')) {
        return jsonBody(201, {
          'data': {
            'id': _mediaId,
            'url': '/api/v1/public/media/$_mediaId',
            'srcset': null,
            'lebar': 1600,
            'tinggi': 1200,
            'status': 'siap',
            'pratinjau_url': '/x',
          },
        });
      }
      return jsonBody(201, {'data': _admin(jenis: 'album', slug: 'kerja-bakti-oktober')});
    });
    await _type(tester, 'title', 'Kerja bakti Oktober');
    await _type(tester, 'slug', 'kerja-bakti-oktober');
    await _type(tester, 'description', 'Dokumentasi kerja bakti warga.');
    await tester.runAsync(() async {
      await tester.ensureVisible(find.byKey(const ValueKey('tambah-foto')));
      await tester.tap(find.byKey(const ValueKey('tambah-foto')));
      await Future<void>.delayed(const Duration(milliseconds: 300));
    });
    await _settle(tester);
    expect(requests.single.data, isA<FormData>());
    await tester.enterText(find.byKey(const ValueKey('alt-$_mediaId')), 'Warga membersihkan saluran air');
    await _tapText(tester, 'Simpan draf');

    final draf = _body(requests.last.data)['draf'] as Map;
    expect(draf['photos'], [
      {'media_id': _mediaId, 'alt': 'Warga membersihkan saluran air', 'caption': null},
    ]);
    expect(draf['cover_media_id'], _mediaId);
    expect(find.byKey(const ValueKey('alert-success')), findsOneWidget);
    file.deleteSync();
  });

  testWidgets('arsip meminta konfirmasi bahaya lalu menutup editor', (tester) async {
    useCompactScreen(tester, height: 2600);
    final posts = <String>[];
    await _pump(
      tester,
      ContentEditorPage(
        type: 'facilities',
        initial: _admin(status: 'terbit', jenis: 'fasilitas', draf: {'title': 'Lapangan'}),
      ),
      (o) {
        posts.add(o.uri.path);
        return jsonBody(200, {'data': _admin(status: 'arsip', jenis: 'fasilitas', versi: 2)});
      },
    );
    await tester.ensureVisible(find.byKey(const ValueKey('arsipkan')));
    await tester.tap(find.byKey(const ValueKey('arsipkan')));
    await _settle(tester);
    expect(find.text('Arsipkan Fasilitas?'), findsOneWidget);
    await _tapText(tester, 'Arsipkan');
    expect(posts.single, endsWith('/admin/facilities/$_id/archive'));
    expect(find.text('Fasilitas diarsipkan'), findsOneWidget);
  });

  testWidgets('profil: simpan dan terbitkan dengan versi, nama belum dipublikasikan tetap', (tester) async {
    useCompactScreen(tester, height: 2600);
    final puts = <Map<String, dynamic>>[];
    final profile = _admin(
      jenis: 'profil',
      slug: 'profil',
      status: 'terbit',
      versi: 3,
      draf: {
        'nama': 'RT05 TAKEDA',
        'singkatan': 'Taman Kedaung',
        'wilayah': 'RT 05 RW 07 Taman Kedaung, Ciputat.',
        'ringkasan': 'Sebuah lingkungan dengan ruang untuk bertemu.',
        'pengurus': [
          {'jabatan': 'Ketua RT', 'nama': 'Agus Ferdiansyah', 'catatan': 'Ketua RT 05 RW 07 Taman Kedaung.'},
          {'jabatan': 'Sekretaris RT', 'nama': 'Nama belum dipublikasikan', 'catatan': null},
        ],
      },
    );
    await _pump(tester, const Scaffold(body: SiteProfileEditor()), (o) {
      if (o.method == 'PUT') {
        puts.add(_body(o.data));
        return jsonBody(200, {
          'data': {...profile, 'versi': 5},
        });
      }
      if (o.uri.path.endsWith('/site-profile')) return jsonBody(200, {'data': profile});
      return jsonBody(200, {'data': [], 'meta': {}});
    });
    Finder field(String value) => find.byWidgetPredicate((w) => w is EditableText && w.controller.text == value);
    expect(field('Nama belum dipublikasikan'), findsOneWidget);
    await tester.enterText(field('Sebuah lingkungan dengan ruang untuk bertemu.'), 'Lingkungan yang rukun dan bersih.');
    await _tapText(tester, 'Simpan dan terbitkan');
    await _tapText(tester, 'Terbitkan');
    expect(puts.single['versi'], 3);
    expect((puts.single['draf'] as Map)['ringkasan'], 'Lingkungan yang rukun dan bersih.');
    expect(((puts.single['draf'] as Map)['pengurus'] as List)[1]['nama'], 'Nama belum dipublikasikan');
    expect(find.text('Profil diterbitkan'), findsOneWidget);
  });

  for (final width in [360.0, 320.0]) {
    testWidgets('editor dan daftar konten skala teks 2.0 lebar $width', (tester) async {
      useCompactScreen(tester, width: width, height: 4000, textScale: 2.0);
      await _pump(
        tester,
        const ContentAdminPage(),
        (o) => jsonBody(200, {
          'data': [_admin(status: 'terbit', changed: true)],
          'meta': {},
        }),
      );
      expect(tester.takeException(), isNull);
      await _pump(tester, ContentEditorPage(type: 'articles', initial: _admin()), (o) => jsonBody(200, {'data': _admin()}));
      expect(tester.takeException(), isNull);
    });
  }
}
