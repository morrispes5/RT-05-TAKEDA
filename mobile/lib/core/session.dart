import 'dart:io' show Platform;

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'account_store.dart';
import 'api.dart';
import 'models.dart';

/// Penyimpanan token sesi. Aplikasi memakai secure storage OS (Keystore Android), bukan preferences biasa.
abstract interface class TokenStorage {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> delete();
}

class SecureTokenStorage implements TokenStorage {
  const SecureTokenStorage();
  static const _storage = FlutterSecureStorage();
  static const _key = 'rt05_token';

  @override
  Future<String?> read() => _storage.read(key: _key);
  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);
  @override
  Future<void> delete() => _storage.delete(key: _key);
}

final tokenStorageProvider = Provider<TokenStorage>((ref) => const SecureTokenStorage());

/// `flutter test` mengisi FLUTTER_TEST. Tiap container uji mendapat penyimpanan memori sendiri.
final accountStoreProvider = Provider<AccountStore>((ref) {
  if (Platform.environment['FLUTTER_TEST'] == 'true') return AccountStore(memory: <String, String>{});
  return AccountStore();
});

/// Validasi token ke server (`GET /auth/me`): status dan peran selalu dari API, bukan cache.
/// Pemanggil yang memeriksa token akun lain (pindah akun) mengirim bearer itu sendiri.
/// 401 di sini tidak mengakhiri sesi HP: [SessionController] yang memutuskan akun mana yang gugur.
typedef FetchMe = Future<Akun> Function(String token);

final fetchMeProvider = Provider<FetchMe>(
  (ref) => (token) async {
    final me = await ref.read(apiProvider).get('/auth/me', bearer: token, ignoreUnauthorized: true);
    return Akun(Map<String, dynamic>.from(me as Map));
  },
);

StoredAccount storedAccount(Akun akun, String token) =>
    StoredAccount(id: akun.id, email: akun.email, token: token, nama: akun.nama, peran: akun.peran, status: akun.status);

/// Status sesi aplikasi.
///
/// - `loading`: memulihkan sesi.
/// - `signedIn`: token + akun tervalidasi server.
/// - `unavailable`: token tetap tersimpan tetapi server belum dapat memvalidasinya
///   (offline, timeout, 5xx, 403). Data akun tidak ditampilkan sampai validasi berhasil.
class SessionState {
  const SessionState({this.loading = true, this.token, this.akun, this.expired = false, this.unavailable, this.accounts = const []});

  final bool loading;
  final String? token;
  final Akun? akun;
  final bool expired;
  final ApiException? unavailable;

  /// Akun yang token-nya ada di HP ini, termasuk yang sedang tidak aktif.
  final List<StoredAccount> accounts;

  bool get signedIn => token != null && akun != null;
  bool get pending => akun?.status == 'menunggu';
  bool get pengurus => akun?.peran == 'pengurus';

  /// Akun aktif yang belum menyetujui Kebijakan Privasi terbaru (ADR35).
  bool get needsPrivacy => akun != null && !pending && akun!.perluSetujuPrivasi;

  /// Akun menunggu tetap boleh menerima kabar persetujuan; navigasi layanan tetap ditahan.
  bool get canRegisterPush => signedIn && !loading && unavailable == null && !needsPrivacy;
  bool get readyForServices => canRegisterPush && !pending;

  SessionState copy({
    bool? loading,
    String? token,
    bool clearToken = false,
    Akun? akun,
    bool clearAkun = false,
    bool? expired,
    ApiException? unavailable,
    bool clearUnavailable = false,
    List<StoredAccount>? accounts,
  }) => SessionState(
    loading: loading ?? this.loading,
    token: clearToken ? null : token ?? this.token,
    akun: clearAkun ? null : akun ?? this.akun,
    expired: expired ?? this.expired,
    unavailable: clearUnavailable ? null : unavailable ?? this.unavailable,
    accounts: accounts ?? this.accounts,
  );
}

class SessionController extends Notifier<SessionState> {
  /// Naik pada setiap perubahan sesi; hasil async lama (restore/401) yang selesai
  /// setelah keluar/masuk baru diabaikan sehingga tidak menimpa sesi terbaru.
  int _epoch = 0;

  /// Epoch yang sedang berlaku. Logout yang menunggu respons menyimpan nilai ini
  /// sebelum request dan memeriksanya lagi di dalam antrean storage.
  int get epoch => _epoch;

  /// Urutan permintaan pindah akun; ketukan pindah yang lebih baru membatalkan yang lama.
  int _switchSeq = 0;

  /// Periksa pemilik operasi async. Token ikut dibandingkan karena signIn menaikkan
  /// epoch sebelum storage selesai, ketika state sesi lama masih tersedia.
  bool isCurrentActive(SessionState started, {required int epoch}) =>
      state.readyForServices && _epoch == epoch && state.token == started.token && state.akun?.id == started.akun?.id;

  /// Operasi storage dijalankan berurutan: hapus milik logout tidak dapat
  /// menyusul dan menghapus token login baru.
  Future<void> _io = Future.value();

  TokenStorage get _storage => ref.read(tokenStorageProvider);
  AccountStore get _accounts => ref.read(accountStoreProvider);

  Future<T> _serial<T>(Future<T> Function() op) {
    final next = _io.then((_) => op());
    _io = next.then((_) {}, onError: (_) {});
    return next;
  }

  @override
  SessionState build() => const SessionState();

  /// Memulihkan sesi saat start atau "Coba lagi". Token hanya dihapus bila server
  /// menjawab 401 (token tidak berlaku); error jaringan/5xx/403 mempertahankannya.
  /// Akun yang sudah masuk sebelum fitur ganti akun ikut dicatat, supaya bisa
  /// menambah akun rumah lain tanpa keluar lebih dulu.
  Future<void> restore() async {
    final epoch = ++_epoch;
    state = const SessionState();

    final all = await _accounts.loadAll();
    final activeId = await _accounts.activeId();
    if (epoch != _epoch) return;

    String? token;
    String? tokenAccountId;
    if (activeId != null) {
      final active = all.where((a) => a.id == activeId).firstOrNull;
      token = active?.token;
      if (token != null) tokenAccountId = active!.id;
    }
    token ??= await _serial(_storage.read);
    if (epoch != _epoch) return;

    if (token == null) {
      state = SessionState(loading: false, accounts: all);
      return;
    }
    final bearer = token;

    state = SessionState(token: bearer, accounts: all);
    try {
      final akun = await ref.read(fetchMeProvider)(bearer);
      if (epoch != _epoch) return;
      await _serial(() => _storage.write(bearer));
      if (epoch != _epoch) return;
      await _accounts.upsert(storedAccount(akun, bearer));
      await _accounts.setActive(akun.id);
      if (epoch != _epoch) return;
      final fresh = await _accounts.loadAll();
      if (epoch != _epoch) return;
      state = SessionState(loading: false, token: bearer, akun: akun, accounts: fresh);
    } on ApiException catch (e) {
      if (epoch != _epoch) return;
      if (e.status == 401) {
        final generation = ++_epoch;
        state = SessionState(loading: false, expired: true, accounts: all.where((a) => a.id != tokenAccountId && a.token != bearer).toList());
        await _serial(_storage.delete);
        if (_epoch != generation) return;
        if (tokenAccountId != null) {
          await _accounts.remove(tokenAccountId);
        } else {
          final stored = await _accounts.loadAll();
          for (final account in stored.where((a) => a.token == bearer)) {
            await _accounts.remove(account.id);
          }
        }
        if (_epoch != generation) return;
        final fresh = await _accounts.loadAll();
        if (_epoch != generation) return;
        state = SessionState(loading: false, expired: true, accounts: fresh);
      } else {
        state = SessionState(loading: false, token: bearer, unavailable: e, accounts: all);
      }
    } catch (_) {
      if (epoch != _epoch) return;
      state = SessionState(loading: false, token: bearer, unavailable: ApiException(0, 'UNKNOWN', 'Terjadi kesalahan. Coba lagi.'), accounts: all);
    }
  }

  Future<void> signIn(String token, Akun akun) async {
    final epoch = ++_epoch;
    await _serial(() => _storage.write(token));
    await _accounts.upsert(storedAccount(akun, token));
    await _accounts.setActive(akun.id);
    if (epoch != _epoch) return;
    final all = await _accounts.loadAll();
    if (epoch != _epoch) return;
    state = SessionState(loading: false, token: token, akun: akun, accounts: all);
  }

  /// Pindah ke akun yang sudah tersimpan. Token tujuan dicek dulu ke server.
  /// Sebelum server mengiyakan, sesi dan token yang sedang dipakai tidak diganti,
  /// supaya data rumah yang satu tidak menimpa rumah yang lain.
  /// Mengembalikan pesan bila pindah gagal; null bila sesi sudah berpindah.
  Future<String?> switchTo(String accountId) async {
    // Epoch baru dipakai hanya saat sesi benar-benar berganti. Pengecekan yang gagal
    // tidak boleh membatalkan permintaan (logout/401) milik sesi yang masih dipakai.
    final epoch = _epoch;
    final seq = ++_switchSeq;
    bool stale() => epoch != _epoch || seq != _switchSeq;
    final all = await _accounts.loadAll();
    if (stale()) return null;
    final target = all.where((a) => a.id == accountId).firstOrNull;
    if (target == null) return 'Akun tidak ditemukan di HP ini.';
    if (target.id == state.akun?.id && state.token == target.token) return null;

    try {
      final akun = await ref.read(fetchMeProvider)(target.token);
      if (stale()) return null;
      final generation = ++_epoch;
      await _serial(() => _storage.write(target.token));
      if (generation != _epoch) return null;
      await _accounts.upsert(storedAccount(akun, target.token));
      await _accounts.setActive(akun.id);
      if (generation != _epoch) return null;
      final fresh = await _accounts.loadAll();
      if (generation != _epoch) return null;
      state = SessionState(loading: false, token: target.token, akun: akun, accounts: fresh);
      return null;
    } on ApiException catch (e) {
      if (stale()) return null;
      if (e.status == 401) {
        await _accounts.remove(accountId);
        final fresh = await _accounts.loadAll();
        if (stale()) return null;
        state = state.copy(accounts: fresh);
        return 'Sesi akun itu sudah berakhir. Masuk lagi dengan email dan kata sandi.';
      }
      return 'Tidak dapat beralih akun. Periksa koneksi, lalu coba lagi.';
    } catch (_) {
      if (stale()) return null;
      return 'Tidak dapat beralih akun. Coba lagi.';
    }
  }

  /// Lepas akun yang tidak sedang dipakai dari HP ini, setelah server mencabut token itu.
  /// Data di server tidak dihapus. Akun yang sedang dipakai dilepas lewat Keluar.
  Future<String?> forgetAccount(String accountId) async {
    final epoch = _epoch;
    if (accountId == state.akun?.id) return 'Akun yang sedang dipakai dilepas lewat tombol Keluar.';
    final all = await _accounts.loadAll();
    final target = all.where((a) => a.id == accountId).firstOrNull;
    if (target == null) return null;
    if (target.token == state.token) return 'Akun ini memakai sesi yang sedang aktif.';
    try {
      await ref.read(apiProvider).post('/auth/logout', bearer: target.token);
    } on ApiException catch (e) {
      if (e.status != 401) {
        if (e.status == 0 || e.status >= 500) {
          return 'Server belum mengonfirmasi. Akun tetap tersimpan di HP ini.';
        }
        return e.display;
      }
    }
    if (epoch != _epoch || state.akun?.id == accountId) return null;
    await _accounts.remove(accountId);
    if (epoch != _epoch) return null;
    final fresh = await _accounts.loadAll();
    if (epoch != _epoch) return null;
    state = state.copy(accounts: fresh);
    return null;
  }

  /// Menimpa akun hanya bila [epoch] masih sesi yang meminta data itu.
  void updateAkun(Akun akun, {required int epoch}) {
    if (epoch != _epoch || state.token == null) return;
    final token = state.token!;
    final previousId = state.akun?.id;
    final stored = storedAccount(akun, token);
    state = SessionState(
      loading: false,
      token: token,
      akun: akun,
      accounts: [
        for (final account in state.accounts)
          if (account.id != akun.id && account.id != previousId) account,
        stored,
      ],
    );
    _writeProfile(stored, previousId);
  }

  Future<void> _writeProfile(StoredAccount stored, String? previousId) async {
    if (!ref.mounted) return;
    await _accounts.upsert(stored);
    if (!ref.mounted) return;
    if (previousId != null && previousId != stored.id) await _accounts.remove(previousId);
    if (!ref.mounted) return;
    await _accounts.setActive(stored.id);
  }

  /// Logout lokal tanpa syarat (splash). Hapus token akun ini; akun lain di HP tetap tersimpan.
  Future<void> signOut() async {
    final currentId = state.akun?.id;
    final generation = ++_epoch;
    state = SessionState(loading: false, accounts: state.accounts.where((a) => a.id != currentId).toList());
    await _serial(_storage.delete);
    if (currentId != null) await _accounts.remove(currentId);
    if (_epoch != generation) return;
    final remaining = await _accounts.loadAll();
    if (_epoch != generation) return;
    state = SessionState(loading: false, accounts: remaining);
  }

  /// Logout server yang selesai terlambat. Token, akun, dan cache sesi dihapus
  /// hanya bila sesi masih [token] pada [epoch] yang dicatat sebelum request.
  /// Pemeriksaan ada di dalam antrean storage, termasuk bila token string-nya sama.
  /// Akun lain yang tersimpan di HP tidak ikut dihapus.
  Future<void> signOutIfCurrent({required String token, required int epoch}) async {
    await _serial(() async {
      if (_epoch != epoch || state.token != token) return;
      final currentId = state.akun?.id;
      final generation = ++_epoch;
      state = SessionState(loading: false, accounts: state.accounts.where((a) => a.id != currentId).toList());
      await _storage.delete();
      if (currentId != null) await _accounts.remove(currentId);
      if (_epoch != generation) return;
      final remaining = await _accounts.loadAll();
      if (_epoch != generation) return;
      state = SessionState(loading: false, accounts: remaining);
    });
  }

  /// 401 dari API. [token] dan [epoch] milik request tersebut; respons sesi lama
  /// (sudah keluar atau masuk lagi, termasuk token yang sama) tidak mengakhiri sesi sekarang.
  /// Hanya akun yang token-nya ditolak yang dilepas. Akun rumah lain tetap bisa dipilih.
  Future<void> expire({String? token, int? epoch}) async {
    if (state.token == null || (token != null && token != state.token)) return;
    if (epoch != null && epoch != _epoch) return;
    final currentId = state.akun?.id;
    final tokenToDrop = state.token;
    final generation = ++_epoch;
    state = SessionState(loading: false, expired: true, accounts: state.accounts.where((a) => a.id != currentId && a.token != tokenToDrop).toList());
    await _serial(_storage.delete);
    if (_epoch != generation) return;
    if (currentId != null) {
      await _accounts.remove(currentId);
    } else if (tokenToDrop != null) {
      final stored = await _accounts.loadAll();
      for (final account in stored.where((a) => a.token == tokenToDrop)) {
        await _accounts.remove(account.id);
      }
    }
    if (_epoch != generation) return;
    final remaining = await _accounts.loadAll();
    if (_epoch != generation) return;
    state = SessionState(loading: false, expired: true, accounts: remaining);
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
