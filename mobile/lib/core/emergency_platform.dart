import 'dart:async';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

abstract interface class EmergencyPlatform {
  Stream<Map<String, dynamic>> get launches;
  Future<Map<String, dynamic>?> initialLaunch();
  Future<void> alarm(String id, String? notificationId);
  Future<void> settings();
  Future<bool> pinShortcut();

  /// ADR50: izin yang dibutuhkan alarm berdering — notifikasi (kanal alarm aktif) dan layar penuh (Android 14+).
  Future<AlarmReadiness> readiness();
  Future<void> fullScreenSettings();
}

class AlarmReadiness {
  const AlarmReadiness({this.notifications = true, this.fullScreen = true});
  final bool notifications;
  final bool fullScreen;
  bool get ready => notifications && fullScreen;
}

class NoEmergencyPlatform implements EmergencyPlatform {
  const NoEmergencyPlatform();
  @override
  Stream<Map<String, dynamic>> get launches => const Stream.empty();
  @override
  Future<Map<String, dynamic>?> initialLaunch() async => null;
  @override
  Future<void> alarm(String id, String? notificationId) async {}
  @override
  Future<void> settings() async {}
  @override
  Future<bool> pinShortcut() async => false;
  @override
  Future<AlarmReadiness> readiness() async => const AlarmReadiness();
  @override
  Future<void> fullScreenSettings() async {}
}

class NativeEmergencyPlatform implements EmergencyPlatform {
  NativeEmergencyPlatform() {
    _channel.setMethodCallHandler((call) async {
      if (call.method == 'launch') {
        final action = await initialLaunch();
        if (action != null) _launches.add(action);
      }
    });
  }
  static const _channel = MethodChannel('rt05/emergency');
  final _launches = StreamController<Map<String, dynamic>>.broadcast();
  @override
  Future<bool> pinShortcut() async {
    try {
      return await _channel.invokeMethod<bool>('pinShortcut') ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      return false;
    }
  }

  @override
  Stream<Map<String, dynamic>> get launches => _launches.stream;
  @override
  Future<Map<String, dynamic>?> initialLaunch() async {
    try {
      return await _channel.invokeMapMethod<String, dynamic>('takeLaunch');
    } on PlatformException {
      return null;
    } on MissingPluginException {
      return null;
    }
  }

  @override
  Future<void> alarm(String id, String? notificationId) async {
    try {
      await _channel.invokeMethod<void>('alarm', {'id': id, 'notification_id': notificationId});
    } on PlatformException {
      /* Inbox remains available. */
    } on MissingPluginException {
      /* Non-Android host. */
    }
  }

  @override
  Future<AlarmReadiness> readiness() async {
    try {
      final map = await _channel.invokeMapMethod<String, dynamic>('readiness') ?? const {};
      return AlarmReadiness(notifications: map['notifikasi'] != false, fullScreen: map['layar_penuh'] != false);
    } on PlatformException {
      return const AlarmReadiness();
    } on MissingPluginException {
      return const AlarmReadiness();
    }
  }

  @override
  Future<void> fullScreenSettings() async {
    try {
      await _channel.invokeMethod<void>('fullScreenSettings');
    } on PlatformException {
      /* Settings may be unavailable on this device. */
    } on MissingPluginException {
      /* Non-Android host. */
    }
  }

  @override
  Future<void> settings() async {
    try {
      await _channel.invokeMethod<void>('settings');
    } on PlatformException {
      /* Settings may be unavailable on this device. */
    } on MissingPluginException {
      /* Non-Android host. */
    }
  }
}

final emergencyPlatformProvider = Provider<EmergencyPlatform>((ref) => const NoEmergencyPlatform());
