import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'models.dart';

/// Status sesi aplikasi. Token disimpan di secure storage OS, tidak di preferences biasa.
class SessionState {
  const SessionState({this.loading = true, this.token, this.akun, this.expired = false});

  final bool loading;
  final String? token;
  final Akun? akun;
  final bool expired;

  bool get signedIn => token != null && akun != null;
  bool get pending => akun?.status == 'menunggu';
  bool get pengurus => akun?.peran == 'pengurus';
}

class SessionController extends Notifier<SessionState> {
  static const _storage = FlutterSecureStorage();
  static const _key = 'rt05_token';

  @override
  SessionState build() => const SessionState();

  Future<void> restore(Future<Akun> Function(String token) fetchMe) async {
    final token = await _storage.read(key: _key);
    if (token == null) {
      state = const SessionState(loading: false);
      return;
    }
    state = SessionState(loading: true, token: token);
    try {
      state = SessionState(loading: false, token: token, akun: await fetchMe(token));
    } catch (_) {
      await _storage.delete(key: _key);
      state = const SessionState(loading: false);
    }
  }

  Future<void> signIn(String token, Akun akun) async {
    await _storage.write(key: _key, value: token);
    state = SessionState(loading: false, token: token, akun: akun);
  }

  void updateAkun(Akun akun) => state = SessionState(loading: false, token: state.token, akun: akun);

  /// Logout/401: hapus token dan cache pribadi.
  Future<void> signOut() async {
    await _storage.delete(key: _key);
    state = const SessionState(loading: false);
  }

  Future<void> expire() async {
    if (state.token == null) return;
    await _storage.delete(key: _key);
    state = const SessionState(loading: false, expired: true);
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
