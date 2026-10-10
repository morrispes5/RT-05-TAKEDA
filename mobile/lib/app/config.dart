/// Konfigurasi compile-time nonsensitif (docs/MOBILE.md). Tidak ada secret server di APK.
///
/// flutter run --dart-define=API_BASE_URL=https://takeda.morriz.tech
/// Default debug: API lokal di host emulator Android (10.0.2.2 → localhost PC).
class AppConfig {
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8077');
  static const flavor = String.fromEnvironment('FLAVOR', defaultValue: 'dev');
  static const appName = 'RT05 TAKEDA';
  static const version = '1.0.0';

  static bool get isProduction => flavor == 'prod';
}
