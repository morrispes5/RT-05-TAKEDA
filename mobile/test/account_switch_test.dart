import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rt05_takeda/app/config.dart';
import 'package:rt05_takeda/core/account_store.dart';
import 'package:rt05_takeda/core/api.dart';
import 'package:rt05_takeda/core/health.dart';
import 'package:rt05_takeda/core/models.dart';
import 'package:rt05_takeda/core/session.dart';
import 'package:rt05_takeda/features/warga/providers.dart';

import 'support/fakes.dart';

Akun _rumah({required String id, required String nama, required String email}) => Akun({...akunJson(id: id), 'nama': nama, 'email': email});

void main() {
  late MemoryTokenStorage storage;

  ProviderContainer container(FetchMe fetchMe, {HttpClientAdapter? adapter}) {
    final c = ProviderContainer(
      overrides: [
        appConfigProvider.overrideWithValue(AppConfig.parse(flavor: 'staging', apiBaseUrl: 'https://api.example.test')),
        tokenStorageProvider.overrideWithValue(storage),
        fetchMeProvider.overrideWithValue(fetchMe),
        httpAdapterProvider.overrideWithValue(adapter),
      ],
    );
    addTearDown(c.dispose);
    return c;
  }

  setUp(() => storage = MemoryTokenStorage());

  test('dua kali masuk menyimpan dua akun dan yang terakhir yang aktif', () async {
    final c = container((token) async => _rumah(id: token == 'token-a' ? 'rumah-a' : 'rumah-b', nama: token, email: '$token@example.test'));
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await n.signIn('token-b', _rumah(id: 'rumah-b', nama: 'Rumah B Uji', email: 'b@example.test'));

    final s = c.read(sessionProvider);
    expect(s.akun!.id, 'rumah-b');
    expect(s.token, 'token-b');
    expect(s.accounts.map((a) => a.id), ['rumah-a', 'rumah-b']);
    expect(storage.token, 'token-b');
    expect(await c.read(accountStoreProvider).activeId(), 'rumah-b');
  });

  test('restore mencatat akun yang sudah masuk supaya akun rumah kedua bisa ditambah', () async {
    storage.token = 'token-lama';
    final c = container((_) async => _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await c.read(sessionProvider.notifier).restore();
    final s = c.read(sessionProvider);
    expect(s.signedIn, isTrue);
    expect(s.accounts, hasLength(1));
    expect(s.accounts.single.id, 'rumah-a');
    expect(s.accounts.single.token, 'token-lama');
    expect(await c.read(accountStoreProvider).activeId(), 'rumah-a');
  });

  test('pindah akun mengganti token hanya setelah server mengiyakan', () async {
    final seen = <String>[];
    final c = container((token) async {
      seen.add(token);
      final id = token == 'token-a' ? 'rumah-a' : 'rumah-b';
      return _rumah(id: id, nama: id, email: '$id@example.test');
    });
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await c.read(accountStoreProvider).upsert(const StoredAccount(id: 'rumah-b', email: 'b@example.test', token: 'token-b', nama: 'Rumah B Uji'));
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));

    final writesBefore = storage.log.where((e) => e == 'write').length;
    final error = await n.switchTo('rumah-b');
    expect(error, isNull);
    expect(seen.last, 'token-b');
    expect(c.read(sessionProvider).akun!.id, 'rumah-b');
    expect(c.read(sessionProvider).token, 'token-b');
    expect(storage.token, 'token-b');
    expect(storage.log.where((e) => e == 'write').length, writesBefore + 1);
    expect(await c.read(accountStoreProvider).activeId(), 'rumah-b');
  });

  test('token mati saat pindah akun tidak menimpa akun yang sedang dipakai', () async {
    final c = container((token) async {
      if (token == 'token-mati') throw ApiException(401, 'UNAUTHENTICATED', 'Sesi berakhir.');
      return _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test');
    });
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await c.read(accountStoreProvider).upsert(const StoredAccount(id: 'rumah-b', email: 'b@example.test', token: 'token-mati', nama: 'Rumah B Uji'));
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));

    final writesBefore = storage.log.where((e) => e == 'write').length;
    final error = await n.switchTo('rumah-b');
    final s = c.read(sessionProvider);
    expect(error, contains('sudah berakhir'));
    expect(s.signedIn, isTrue);
    expect(s.akun!.id, 'rumah-a');
    expect(s.token, 'token-a');
    expect(s.expired, isFalse);
    expect(storage.token, 'token-a');
    expect(storage.log.where((e) => e == 'write').length, writesBefore);
    expect(s.accounts.map((a) => a.id), ['rumah-a']);
  });

  test('keluar hanya melepas akun itu; akun rumah lain tetap bisa dipilih', () async {
    final c = container((token) async {
      final id = token == 'token-b' ? 'rumah-b' : 'rumah-a';
      return _rumah(id: id, nama: id, email: '$id@example.test');
    });
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await n.signIn('token-b', _rumah(id: 'rumah-b', nama: 'Rumah B Uji', email: 'b@example.test'));
    await n.signOut();

    final signedOut = c.read(sessionProvider);
    expect(signedOut.signedIn, isFalse);
    expect(storage.token, isNull);
    expect(signedOut.accounts.map((a) => a.id), ['rumah-a']);

    expect(await n.switchTo('rumah-a'), isNull);
    expect(c.read(sessionProvider).akun!.id, 'rumah-a');
    expect(c.read(sessionProvider).token, 'token-a');
    expect(storage.token, 'token-a');
  });

  test('restore 401 melepas akun mati dan tidak memakainya lagi pada pembukaan berikutnya', () async {
    var calls = 0;
    final c = container((_) async {
      calls++;
      throw ApiException(401, 'UNAUTHENTICATED', 'Sesi berakhir.');
    });
    await c.read(accountStoreProvider).upsert(const StoredAccount(id: 'rumah-a', email: 'a@example.test', token: 'token-mati', nama: 'Rumah A Uji'));
    await c.read(accountStoreProvider).setActive('rumah-a');
    storage.token = 'token-mati';

    await c.read(sessionProvider.notifier).restore();
    expect(calls, 1);
    expect(c.read(sessionProvider).signedIn, isFalse);
    expect(c.read(sessionProvider).expired, isTrue);
    expect(c.read(sessionProvider).accounts, isEmpty);
    expect(storage.token, isNull);

    await c.read(sessionProvider.notifier).restore();
    expect(calls, 1);
    expect(c.read(sessionProvider).expired, isFalse);
    expect(c.read(sessionProvider).loading, isFalse);
  });

  test('hapus akun lain tidak melepas sesi yang sedang dipakai', () async {
    final adapter = FakeAdapter((o) {
      expect(o.headers['Authorization'], 'Bearer token-b');
      return ResponseBody.fromString('', 204);
    });
    final c = container(
      (_) async => _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'),
      adapter: adapter,
    );
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await c.read(accountStoreProvider).upsert(const StoredAccount(id: 'rumah-b', email: 'b@example.test', token: 'token-b', nama: 'Rumah B Uji'));
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));

    expect(await n.forgetAccount('rumah-a'), contains('Keluar'));
    expect(await n.forgetAccount('rumah-b'), isNull);
    expect(c.read(sessionProvider).akun!.id, 'rumah-a');
    expect(c.read(sessionProvider).token, 'token-a');
    expect(c.read(sessionProvider).accounts.map((a) => a.id), ['rumah-a']);
    expect(adapter.requests, hasLength(1));
  });

  test('hapus akun lain saat server tidak terjangkau tidak menghapus token lokal', () async {
    final c = container(
      (_) async => _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'),
      adapter: FakeAdapter(offline),
    );
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    await c.read(accountStoreProvider).upsert(const StoredAccount(id: 'rumah-b', email: 'b@example.test', token: 'token-b', nama: 'Rumah B Uji'));
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));

    final error = await n.forgetAccount('rumah-b');
    expect(error, contains('tetap tersimpan'));
    expect(c.read(sessionProvider).accounts.map((a) => a.id), containsAll(['rumah-a', 'rumah-b']));
    expect(c.read(sessionProvider).token, 'token-a');
  });
  test('data rumah yang masih ditonton tab dimuat ulang setelah pindah akun', () async {
    final adapter = FakeAdapter((o) {
      final bearer = '${o.headers['Authorization']}';
      final kode = bearer.endsWith('token-b') ? 'B-02' : 'A-01';
      return jsonBody(200, {
        'data': {'kode_rumah': kode},
      });
    });
    final c = container((token) async {
      final id = token == 'token-b' ? 'rumah-b' : 'rumah-a';
      return _rumah(id: id, nama: id, email: '$id@example.test');
    }, adapter: adapter);
    final n = c.read(sessionProvider.notifier);
    await n.signIn('token-b', _rumah(id: 'rumah-b', nama: 'Rumah B Uji', email: 'b@example.test'));
    await n.signIn('token-a', _rumah(id: 'rumah-a', nama: 'Rumah A Uji', email: 'a@example.test'));
    // Tab baru langsung menonton provider yang sama: autoDispose tidak sempat membuang cache.
    final sub = c.listen(householdProvider, (_, _) {});
    addTearDown(sub.close);
    expect((await c.read(householdProvider.future))['kode_rumah'], 'A-01');

    expect(await n.switchTo('rumah-b'), isNull);
    expect((await c.read(householdProvider.future))['kode_rumah'], 'B-02');
  });
}
