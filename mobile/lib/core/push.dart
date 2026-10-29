import 'dart:async';
import 'dart:math';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api.dart';

/// Push Android (M10, FR20). Push hanya pemberitahuan: inbox di server tetap sumber data, dan
/// penolakan izin tidak memblokir layanan. Firebase dikonfigurasi lewat `--dart-define` nilai klien
/// nonsensitif (FIREBASE_API_KEY, FIREBASE_APP_ID, FIREBASE_SENDER_ID, FIREBASE_PROJECT_ID); tanpa
/// nilai itu aplikasi berjalan tanpa push (CI/dev) dan halaman pengaturan menjelaskannya.
enum PushPermission { granted, denied, notDetermined, unavailable }

/// Isi data push yang dipercaya hanya sebagai petunjuk navigasi: jenis dari daftar tetap dan ID UUID.
/// Detail selalu dimuat ulang dari API sehingga tautan basi/tidak berhak berakhir di pesan aman.
class PushTap {
  const PushTap({this.notifikasiId, this.refType, this.refId, this.title, this.body, this.alarm = false});

  final String? notifikasiId;
  final String? refType;
  final String? refId;
  final String? title;
  final String? body;

  /// ADR50: push alarm data-only; dering sudah ditangani penerima Android, aplikasi tidak membunyikan ulang.
  final bool alarm;

  static const refTypes = {'pengumuman', 'agenda', 'pengaduan', 'aspirasi', 'usulan_kegiatan', 'tagihan', 'kejadian_darurat'};
  static final _uuid = RegExp(r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$');

  static bool isUuid(Object? v) => v is String && _uuid.hasMatch(v);

  /// Nilai di luar daftar/format dibuang; hasilnya paling buruk membuka inbox.
  factory PushTap.fromData(Map<String, dynamic> data, {String? title, String? body}) {
    final type = data['ref_type'];
    final id = data['ref_id'];
    final valid = type is String && refTypes.contains(type) && isUuid(id);
    return PushTap(
      notifikasiId: isUuid(data['notifikasi_id']) ? data['notifikasi_id'] as String : null,
      refType: valid ? type : null,
      refId: valid ? id as String : null,
      title: title,
      body: body,
      alarm: data['alarm'] == '1',
    );
  }
}

abstract interface class PushClient {
  bool get available;
  Future<PushPermission> permission();
  Future<PushPermission> requestPermission();
  Future<String?> token();
  Stream<String> get tokenRefresh;
  Stream<PushTap> get foreground;
  Stream<PushTap> get opened;
  Future<PushTap?> initialTap();
}

/// Tanpa konfigurasi Firebase: tidak ada push, inbox tetap bekerja.
class NoPushClient implements PushClient {
  const NoPushClient();
  @override
  bool get available => false;
  @override
  Future<PushPermission> permission() async => PushPermission.unavailable;
  @override
  Future<PushPermission> requestPermission() async => PushPermission.unavailable;
  @override
  Future<String?> token() async => null;
  @override
  Stream<String> get tokenRefresh => const Stream.empty();
  @override
  Stream<PushTap> get foreground => const Stream.empty();
  @override
  Stream<PushTap> get opened => const Stream.empty();
  @override
  Future<PushTap?> initialTap() async => null;
}

class FirebasePushClient implements PushClient {
  FirebasePushClient._();

  static const _apiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const _appId = String.fromEnvironment('FIREBASE_APP_ID');
  static const _senderId = String.fromEnvironment('FIREBASE_SENDER_ID');
  static const _projectId = String.fromEnvironment('FIREBASE_PROJECT_ID');

  static bool get configured => _apiKey != '' && _appId != '' && _senderId != '' && _projectId != '';

  /// null bila tidak dikonfigurasi atau inisialisasi gagal (mis. Play Services tidak ada).
  static Future<PushClient> create() async {
    if (!configured) return const NoPushClient();
    try {
      await Firebase.initializeApp(
        options: const FirebaseOptions(apiKey: _apiKey, appId: _appId, messagingSenderId: _senderId, projectId: _projectId),
      );
      return FirebasePushClient._();
    } catch (_) {
      return const NoPushClient();
    }
  }

  FirebaseMessaging get _m => FirebaseMessaging.instance;

  static PushTap _tap(RemoteMessage m) => PushTap.fromData(m.data, title: m.notification?.title, body: m.notification?.body);

  static PushPermission _map(AuthorizationStatus s) => switch (s) {
    AuthorizationStatus.authorized || AuthorizationStatus.provisional => PushPermission.granted,
    AuthorizationStatus.denied || AuthorizationStatus.deniedPermanently => PushPermission.denied,
    AuthorizationStatus.notDetermined => PushPermission.notDetermined,
  };

  @override
  bool get available => true;
  @override
  Future<PushPermission> permission() async => _map((await _m.getNotificationSettings()).authorizationStatus);
  @override
  Future<PushPermission> requestPermission() async => _map((await _m.requestPermission()).authorizationStatus);
  // Token registrasi FCM klasik: target `message.token` FCM HTTP v1 yang dipakai server. API FID
  // (register/onRegistered) belum dipakai karena server belum mendukung direct-send FID.
  @override
  // ignore: deprecated_member_use
  Future<String?> token() => _m.getToken();
  @override
  // ignore: deprecated_member_use
  Stream<String> get tokenRefresh => _m.onTokenRefresh;
  @override
  Stream<PushTap> get foreground => FirebaseMessaging.onMessage.map(_tap);
  @override
  Stream<PushTap> get opened => FirebaseMessaging.onMessageOpenedApp.map(_tap);
  @override
  Future<PushTap?> initialTap() async {
    final m = await _m.getInitialMessage();
    return m == null ? null : _tap(m);
  }
}

/// Di-override di main() dengan hasil [FirebasePushClient.create]; tes memakai klien palsu.
final pushClientProvider = Provider<PushClient>((ref) => const NoPushClient());

/// ID instalasi acak (bukan IMEI/ANDROID_ID), disimpan di secure storage.
abstract interface class DeviceIdStore {
  Future<String> read();
}

class SecureDeviceIdStore implements DeviceIdStore {
  const SecureDeviceIdStore();
  static const _storage = FlutterSecureStorage();
  static const _key = 'rt05_device_id';

  @override
  Future<String> read() async {
    final existing = await _storage.read(key: _key);
    if (existing != null && existing.isNotEmpty) return existing;
    final r = Random.secure();
    final id = 'android-${List.generate(16, (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0')).join()}';
    await _storage.write(key: _key, value: id);
    return id;
  }
}

final deviceIdStoreProvider = Provider<DeviceIdStore>((ref) => const SecureDeviceIdStore());

/// Pendaftaran token push untuk sesi aktif. Gagal jaringan diabaikan: dicoba lagi saat aplikasi
/// dibuka. Mengembalikan izin terakhir agar UI pengaturan bisa menjelaskan keadaan.
class PushService {
  PushService(this._ref);
  final Ref _ref;

  PushClient get client => _ref.read(pushClientProvider);

  Future<PushPermission> register({bool ask = true}) async {
    if (!client.available) return PushPermission.unavailable;
    try {
      var p = await client.permission();
      if (p == PushPermission.notDetermined && ask) p = await client.requestPermission();
      if (p != PushPermission.granted) return p;
      final token = await client.token();
      if (token != null && token.isNotEmpty) await sendToken(token);
      return p;
    } catch (_) {
      return PushPermission.unavailable;
    }
  }

  Future<void> sendToken(String token) async {
    try {
      final id = await _ref.read(deviceIdStoreProvider).read();
      await _ref.read(apiProvider).put('/devices/$id', data: {'fcm_token': token, 'platform': 'android'});
    } catch (_) {
      // Diabaikan: inbox tetap bekerja; pendaftaran diulang pada pembukaan berikutnya.
    }
  }
}

final pushServiceProvider = Provider<PushService>(PushService.new);
