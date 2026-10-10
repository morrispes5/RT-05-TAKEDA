# mobile/ — aplikasi Flutter warga dan pengurus

**Status: placeholder (M01).** Aplikasi belum dibuat. Scaffold dikerjakan pada **M05** sesuai [docs/MOBILE.md](../docs/MOBILE.md) dan [docs/ROADMAP.md](../docs/ROADMAP.md).

Satu aplikasi Android dengan navigasi sesuai role (warga, akun tambahan, pengurus). Seluruh data dan aturan bisnis berasal dari `rest-api/` melalui `/api/v1`; aplikasi tidak terhubung langsung ke Neon atau Redis dan tidak menghitung saldo sendiri.

## Prasyarat lingkungan pengembangan (diinspeksi 10 Oktober 2026)

| Komponen | Keadaan pada PC pemilik |
| --- | --- |
| Flutter SDK / Dart | **Belum terpasang / tidak ada di PATH** — wajib sebelum M05 |
| Android SDK | Ada di `%LOCALAPPDATA%\Android\Sdk` (platforms 34–36.1, build-tools 36.x) |
| Emulator (AVD) | `Pixel_7a`, `Pixel_8a` (system image android-36.1/37.x) |
| JDK | Android Studio JBR tersedia; `java` di PATH masih 1.8 dan `JAVA_HOME` kosong |

Struktur target M05: `lib/`, `test/`, `integration_test/`, `android/`, `pubspec.yaml`, `pubspec.lock` (di-commit). Signing key, `key.properties`, dan `google-services.json` tidak pernah masuk Git.
