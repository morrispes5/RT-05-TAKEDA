import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Hasil meminta Android memasang APK pembaruan (ADR46).
enum InstallStart { started, needsPermission }

/// Kanal `rt05/update` di MainActivity: ABI HP, folder cache pembaruan, izin "sumber ini", dan PackageInstaller.
/// SHA-256 dan versionCode diperiksa di sisi Android sebelum sesi pemasangan dibuat.
abstract interface class AppUpdatePlatform {
  /// false di luar Android (tes, build lain): aplikasi kembali ke tautan halaman unduh resmi.
  bool get supported;
  Future<List<String>> abis();
  Future<String> directory();
  Future<InstallStart> install(String path, String sha256);
  Future<void> openInstallPermission();

  /// Status akhir dari Android: SUCCESS, ABORTED, STORAGE, CONFLICT, FAILED.
  Stream<String> get statuses;
}

class NoAppUpdatePlatform implements AppUpdatePlatform {
  const NoAppUpdatePlatform();
  @override
  bool get supported => false;
  @override
  Future<List<String>> abis() async => const [];
  @override
  Future<String> directory() async => throw UnsupportedError('Pembaruan dalam aplikasi hanya di Android');
  @override
  Future<InstallStart> install(String path, String sha256) async => throw UnsupportedError('Pembaruan dalam aplikasi hanya di Android');
  @override
  Future<void> openInstallPermission() async {}
  @override
  Stream<String> get statuses => const Stream.empty();
}

class NativeAppUpdatePlatform implements AppUpdatePlatform {
  NativeAppUpdatePlatform() {
    _channel.setMethodCallHandler((call) async {
      if (call.method == 'status' && call.arguments is String) _statuses.add(call.arguments as String);
    });
  }
  static const _channel = MethodChannel('rt05/update');
  final _statuses = StreamController<String>.broadcast();

  @override
  bool get supported => true;
  @override
  Stream<String> get statuses => _statuses.stream;

  @override
  Future<List<String>> abis() async => (await _channel.invokeListMethod<String>('abis')) ?? const [];

  @override
  Future<String> directory() async => (await _channel.invokeMethod<String>('directory'))!;

  @override
  Future<InstallStart> install(String path, String sha256) async {
    final r = await _channel.invokeMethod<String>('install', {'path': path, 'sha256': sha256});
    return r == 'permission' ? InstallStart.needsPermission : InstallStart.started;
  }

  @override
  Future<void> openInstallPermission() async {
    try {
      await _channel.invokeMethod<void>('permission');
    } on PlatformException {
      /* Pengaturan tidak tersedia; pesan di layar tetap menjelaskan langkahnya. */
    }
  }
}

final appUpdatePlatformProvider = Provider<AppUpdatePlatform>((ref) => const NoAppUpdatePlatform());
