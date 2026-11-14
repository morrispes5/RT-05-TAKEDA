import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/core/account_store.dart';
import 'package:rt05_takeda/core/api.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/session.dart';

import 'support/fakes.dart';

/// Review bug mobile 14 Nov 2026: respons non-JSON dan daftar akun yang ditulis bersamaan.
void main() {
  ProviderContainer container(FakeHandler handler) {
    final c = ProviderContainer(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(MemoryTokenStorage('t')),
        httpAdapterProvider.overrideWithValue(FakeAdapter(handler)),
      ],
    );
    addTearDown(c.dispose);
    return c;
  }

  ResponseBody html(int status) => ResponseBody.fromString(
    '<html><body>Checking your browser</body></html>',
    status,
    headers: {
      Headers.contentTypeHeader: ['text/html; charset=utf-8'],
    },
  );

  test('halaman HTML berstatus 200 (tantangan anti-bot, captive portal) menjadi ApiException, bukan TypeError', () async {
    final api = container((_) => html(200)).read(apiProvider);
    for (final call in <Future<dynamic> Function()>[
      () => api.get('/dues/my'),
      () => api.page('/agendas'),
      () => api.post('/auth/login', data: {'email': 'a@example.test'}),
      () => api.put('/admin/settings/contacts', data: {}),
    ]) {
      await expectLater(call(), throwsA(isA<ApiException>().having((e) => e.code, 'code', 'BAD_RESPONSE')));
    }
  });

  test('mutasi 204 tanpa isi tetap mengembalikan null', () async {
    final api = container((_) => ResponseBody.fromString('', 204)).read(apiProvider);
    expect(await api.delete('/complaints/x/photos/y'), isNull);
    expect(await api.post('/auth/logout'), isNull);
  });

  test('field error yang bukan list tidak membuat parser error melempar TypeError', () async {
    final api = container(
      (_) => jsonBody(422, {
        'error': {
          'code': 'VALIDATION_FAILED',
          'message': 'Data tidak valid.',
          'fields': {'email': 'Email wajib diisi.'},
        },
      }),
    ).read(apiProvider);
    await expectLater(api.post('/auth/login'), throwsA(isA<ApiException>().having((e) => e.display, 'display', 'Email wajib diisi.')));
  });

  test('upsert dan remove yang berjalan bersamaan tidak saling menimpa daftar akun', () async {
    final memory = <String, String>{};
    final store = AccountStore(memory: memory);
    StoredAccount akun(String id) => StoredAccount(id: id, email: '$id@example.test', token: 'token-$id');
    await store.upsert(akun('a'));
    // Tanpa antrean: keduanya membaca [a]; upsert menulis [a, b], lalu remove menulis [] sehingga b hilang.
    await Future.wait([store.upsert(akun('b')), store.remove('a')]);
    expect((await store.loadAll()).map((x) => x.id), ['b']);
  });
}
