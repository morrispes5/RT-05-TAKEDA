import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class StoredAccount {
  const StoredAccount({
    required this.id,
    required this.email,
    required this.token,
    this.nama,
    this.peran,
    this.status,
  });

  final String id;
  final String email;
  final String token;
  final String? nama;
  final String? peran;
  final String? status;

  Map<String, dynamic> toJson() => {
        'id': id,
        'email': email,
        'token': token,
        'nama': nama,
        'peran': peran,
        'status': status,
      };

  factory StoredAccount.fromJson(Map<String, dynamic> json) => StoredAccount(
        id: json['id'] as String,
        email: json['email'] as String,
        token: json['token'] as String,
        nama: json['nama'] as String?,
        peran: json['peran'] as String?,
        status: json['status'] as String?,
      );
}

class AccountStore {
  static const _storage = FlutterSecureStorage();
  static const _key = 'rt05_accounts';
  static const _activeKey = 'rt05_active_account_id';

  Future<List<StoredAccount>> loadAll() async {
    final raw = await _storage.read(key: _key);
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw) as List;
      return list
          .map((e) => StoredAccount.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> saveAll(List<StoredAccount> accounts) async {
    final json = jsonEncode(accounts.map((a) => a.toJson()).toList());
    await _storage.write(key: _key, value: json);
  }

  Future<String?> activeId() => _storage.read(key: _activeKey);

  Future<void> setActive(String id) =>
      _storage.write(key: _activeKey, value: id);

  Future<void> clearActive() => _storage.delete(key: _activeKey);

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
    final active = await activeId();
    if (active == id) await clearActive();
  }

  Future<void> clearAll() async {
    await _storage.delete(key: _key);
    await clearActive();
  }
}