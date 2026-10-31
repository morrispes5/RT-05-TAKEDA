import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Akun yang token-nya disimpan di HP ini. Satu orang dapat menyimpan lebih dari satu
/// akun (misalnya dua rumah). Token tidak pernah ditulis ke log atau preferensi biasa.
class StoredAccount {
  const StoredAccount({required this.id, required this.email, required this.token, this.nama, this.peran, this.status});

  final String id;
  final String email;
  final String token;
  final String? nama;
  final String? peran;
  final String? status;

  Map<String, dynamic> toJson() => {'id': id, 'email': email, 'token': token, 'nama': nama, 'peran': peran, 'status': status};

  factory StoredAccount.fromJson(Map<String, dynamic> json) => StoredAccount(
    id: json['id'] as String,
    email: json['email'] as String,
    token: json['token'] as String,
    nama: json['nama'] as String?,
    peran: json['peran'] as String?,
    status: json['status'] as String?,
  );
}

/// Daftar akun di secure storage OS. [memory] hanya untuk tes: tiap instance punya peta sendiri
/// sehingga sesi uji tidak saling menimpa dan tidak memanggil plugin secure storage.
class AccountStore {
  // Nama parameter publik `memory` tetap dipakai pemanggil tes.
  // ignore: prefer_initializing_formals
  AccountStore({Map<String, String>? memory}) : _memory = memory;

  static const _secure = FlutterSecureStorage();
  static const _key = 'rt05_accounts';
  static const _activeKey = 'rt05_active_account_id';

  final Map<String, String>? _memory;

  Future<String?> _read(String key) async => _memory != null ? _memory[key] : _secure.read(key: key);

  Future<void> _write(String key, String value) async {
    if (_memory != null) {
      _memory[key] = value;
      return;
    }
    await _secure.write(key: key, value: value);
  }

  Future<void> _delete(String key) async {
    if (_memory != null) {
      _memory.remove(key);
      return;
    }
    await _secure.delete(key: key);
  }

  Future<List<StoredAccount>> loadAll() async {
    final raw = await _read(_key);
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw) as List;
      return list.map((e) => StoredAccount.fromJson(Map<String, dynamic>.from(e as Map))).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveAll(List<StoredAccount> accounts) async {
    await _write(_key, jsonEncode(accounts.map((a) => a.toJson()).toList()));
  }

  Future<String?> activeId() => _read(_activeKey);

  Future<void> setActive(String id) => _write(_activeKey, id);

  Future<void> clearActive() => _delete(_activeKey);

  Future<void> upsert(StoredAccount account) async {
    final all = await loadAll();
    final idx = all.indexWhere((a) => a.id == account.id);
    if (idx >= 0) {
      all[idx] = account;
    } else {
      all.add(account);
    }
    await saveAll(all);
  }

  Future<void> remove(String id) async {
    final all = await loadAll();
    all.removeWhere((a) => a.id == id);
    await saveAll(all);
    if (await activeId() == id) await clearActive();
  }
}
