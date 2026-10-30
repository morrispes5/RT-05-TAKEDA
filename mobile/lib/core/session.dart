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

final accountStoreProvider = Provider<AccountStore>((ref) => AccountStore());

/// Validasi token ke server (`GET /auth/me`): status dan peran selalu dari API, bukan cache.
typedef FetchMe = Future<Akun> Function(String token);

final fetchMeProvider = Provider<FetchMe>(
  (ref) => (token) async {
    final me = await ref.read(apiProvider).get('/auth/me');
    return Akun(Map<String, dynamic>.from(me as Map));
  },
);

/// Status sesi aplikasi.
///
/// - `loading`: memulihkan sesi.
/// - `signedIn`: token + akun tervalidasi server.
/// - `unavailable`: token tetap tersimpan tetapi server belum dapat memvalidasinya
///   (offline, timeout, 5xx, 403). Data akun tidak ditampilkan sampai validasi berhasil.
class SessionState {
  const SessionState({
    this.loading = true,
    this.token,
    this.akun,
    this.expired = false,
    this.unavailable,
    this.accounts = const [],
  });

  final bool loading;
  final String? token;
  final Akun? akun;
  final bool expired;
  final ApiException? unavailable;
  final List<StoredAccount> accounts;

  bool get signedIn => token != null && akun != null;
  bool get pending => akun?.status == 'menunggu';
  bool get pengurus => akun?.peran == 'pengurus';

  /// Akun aktif yang belum menyetujui Kebijakan Privasi terbaru (ADR35).
  bool get needsPrivacy => akun != null && !pending && akun!.perluSetujuPrivasi;

  /// Akun menunggu tetap boleh menerima kabar persetujuan; navigasi layanan tetap ditahan.
  bool get canRegisterPush => signedIn && !loading && unavailable == null && !needsPrivacy;
  bool get readyForServices => canRegisterPush && !pending;
}

class SessionController extends Notifier<SessionState> {
  /// Naik pada setiap perubahan sesi; hasil async lama (restore/401) yang selesai
  /// setelah keluar/masuk baru diabaikan sehingga tidak menimpa sesi terbaru.
  int _epoch = 0;

  /// Epoch yang sedang berlaku. Logout yang menunggu respons menyimpan nilai ini
  /// sebelum request dan memeriksanya lagi di dalam antrean storage.
  int get epoch => _epoch;

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
  Future<void> restore() async {
    final epoch = ++_epoch;
    state = const SessionState();

    final all = await _accounts.loadAll();
    final activeId = await _accounts.activeId();

    String? token;
    if (activeId != null) {
      final active = all.where((a) => a.id == activeId).firstOrNull;
      token = active?.token;
    }
    token ??= await _serial(_storage.read);

    if (epoch != _epoch) return;

    if (token == null) {
      state = SessionState(loading: false, accounts: all);
      return;
    }

    state = SessionState(token: token, accounts: all);
    try {
      final akun = await ref.read(fetchMeProvider)(token);
      if (epoch != _epoch) return;
      state = SessionState(loading: false, token: token, akun: akun, accounts: all);
    } on ApiException catch (e) {
      if (epoch != _epoch) return;
      if (e.status == 401) {
        _epoch++;
        state = SessionState(loading: false, expired: true, accounts: all);
        await _serial(_storage.delete);
        if (activeId != null) await _accounts.remove(activeId);
      } else {
        state = SessionState(loading: false, token: token, unavailable: e, accounts: all);
      }
    } catch (_) {
      if (epoch != _epoch) return;
      state = SessionState(
        loading: false,
        token: token,
        unavailable: ApiException(0, 'UNKNOWN', 'Terjadi kesalahan. Coba lagi.'),
        accounts: all,
      );
    }
  }

  Future<void> signIn(String token, Akun akun) async {
    final epoch = ++_epoch;
    await _serial(() => _storage.write(token));

    final stored = StoredAccount(
      id: akun.id,
      email: akun.email ?? '',
      token: token,
      nama: akun.nama,
      peran: akun.peran,
      status: akun.status,
    );
    await _accounts.upsert(stored);
    await _accounts.setActive(akun.id);

    if (epoch != _epoch) return;
    final all = await _accounts.loadAll();
    state = SessionState(loading: false, token: token, akun: akun, accounts: all);
  }

  /// Switch ke akun yang sudah tersimpan.
  Future<void> switchTo(String accountId) async {
    final all = await _accounts.loadAll();
    final target = all.where((a) => a.id == accountId).firstOrNull;
    if (target == null) return;

    final epoch = ++_epoch;
    await _accounts.setActive(accountId);
    await _serial(() => _storage.write(target.token));

    state = SessionState(token: target.token, accounts: all);
    try {
      final akun = await ref.read(fetchMeProvider)(target.token);
      if (epoch != _epoch) return;
      state = SessionState(loading: false, token: target.token, akun: akun, accounts: all);
    } on ApiException catch (e) {
      if (epoch != _epoch) return;
      if (e.status == 401) {
        await _accounts.remove(accountId);
        final remaining = await _accounts.loadAll();
        state = SessionState(loading: false, expired: true, accounts: remaining);
      } else {
        state = SessionState(loading: false, token: target.token, unavailable: e, accounts: all);
      }
    }
  }

  /// Menimpa akun hanya bila [epoch] masih sesi yang meminta data itu.
  void updateAkun(Akun akun, {required int epoch}) {
    if (epoch != _epoch || state.token == null) return;
    state = SessionState(loading: false, token: state.token, akun: akun, accounts: state.accounts);
  }

  /// Logout lokal tanpa syarat (splash). Hapus token dan cache pribadi.
  Future<void> signOut() async {
    final currentId = state.akun?.id;
    _epoch++;
    state = SessionState(loading: false, accounts: state.accounts);
    await _serial(_storage.delete);
    if (currentId != null) {
      await _accounts.remove(currentId);
    }
    final remaining = await _accounts.loadAll();
    state = SessionState(loading: false, accounts: remaining);
  }

  /// Logout server yang selesai terlambat. Token, akun, dan cache sesi dihapus
  /// hanya bila sesi masih [token] pada [epoch] yang dicatat sebelum request.
  /// Pemeriksaan ada di dalam antrean storage, termasuk bila token string-nya sama.
  Future<void> signOutIfCurrent({required String token, required int epoch}) async {
    await _serial(() async {
      if (_epoch != epoch || state.token != token) return;
      final currentId = state.akun?.id;
      _epoch++;
      state = const SessionState(loading: false);
      await _storage.delete();
      if (currentId != null) await _accounts.remove(currentId);
    });
  }

  /// 401 dari API. [token] dan [epoch] milik request tersebut; respons sesi lama
  /// (sudah keluar atau masuk lagi, termasuk token yang sama) tidak mengakhiri sesi sekarang.
  Future<void> expire({String? token, int? epoch}) async {
    if (state.token == null || (token != null && token != state.token)) return;
    if (epoch != null && epoch != _epoch) return;
    final currentId = state.akun?.id;
    _epoch++;
    state = const SessionState(loading: false, expired: true);
    await _serial(_storage.delete);
    if (currentId != null) await _accounts.remove(currentId);
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);