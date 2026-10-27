# M19 — rilis uji tombol panik, 27 Oktober 2026

Status: **APK 1023 dirilis untuk uji, Selasa 27 Oktober 2026 11.37 WIB**. M19 tetap **in_progress**, menunggu alarm HP nyata.

## Kode, persetujuan dan batas

Pemilik meminta unduhan terbaru lewat Hostinger/Chrome/plugin dan memastikan shortcut di Home.
Kode dipublikasikan melalui GitHub Git Data API dengan commit/tree identik lokal; tidak ada fetch/pull/push
atau transport Git ke origin di PC. PR [#92](https://github.com/morrispes5/RT-05-TAKEDA/pull/92) merged,
CI 10/10 pada head `7c05f89e`, staging awal `e9c5f5ed` berhasil melalui skrip immutable.
Skrip deploy diverifikasi SHA-256 `aa50ca1a629ba5a7b0c29b7c24ac357118a96f5ded6246a35f55e693b6c52c75`.
Production, DNS, firewall, layanan VPS lain dan checkout awal/MyRepublic tidak diubah.

Build 1022 dibatalkan **sebelum distribusi**. Upgrade signed 1021 → 1022 di emulator mempertahankan data/sesi,
tetapi pemeriksaan layar menemukan footer bertanggal 26 Oktober saat isi kebijakan sudah 27 Oktober.
PR [#93](https://github.com/morrispes5/RT-05-TAKEDA/pull/93) memperbaiki tanggal; tes widget baru membuktikan
tanggal dan payload persetujuan sama-sama 27 Oktober. Artifact 1022 hanya kandidat QA privat, bukan rilis tim.
PR #93 CI **7/7**, merged `8d356e44abd9cd959022e729313665394e80f078`; tree source lokal `873074a5`
dan merge identik: `156de2684baa9c90a036268e5f2d133f43fc308c`. Staging diperbarui ke merge ini.
Runtime-smoke PASS, keenam layanan diverifikasi ulang; finance v2 dan konfigurasi restricted tetap sesuai.

## Validasi kode

| Perintah | Hasil sesi rilis M19 |
| --- | --- |
| `cd rest-api; php artisan test --compact` | 431/431; 4.518 assertions, PostgreSQL lokal 54329 |
| `cd rest-api; vendor/bin/pint --test` | PASS |
| `cd website; php artisan test` | 41/41; 549 assertions |
| `cd website; npm.cmd run build:preview` | PASS; 24 rute |
| `cd mobile; flutter analyze` | Bersih |
| `cd mobile; flutter test --reporter compact` | **339/339** setelah perbaikan tanggal |
| `cd mobile; dart format --output=none --set-exit-if-changed lib test` | 101 file, 0 berubah |
| `node infra/scripts/check-repo.mjs` dan `git diff --check` | PASS sebelum publikasi source |
| Lint OpenAPI dan kontrak Compose | PASS pada source M19 sebelum rilis |

Backend/website tidak berubah dalam PR #93; angka di atas berasal dari eksekusi sesi M19, bukan klaim
menjalankan ulang seluruh backend setiap perubahan footer. Tes Flutter lengkap dijalankan ulang untuk footer.

## Staging dan backup

Fitur aktif **restricted**, hanya dua akun sintetis baru untuk pengurus/warga. Identitas/kredensial disimpan
di direktori privat operator, tidak di Git. Rumah uji tidak ikut penagihan. Tidak ada broadcast ke warga asli.
API, tiga worker, scheduler: panic aktif, restricted, 2 penerima, finance v2 tetap aktif, push provider terkonfigurasi,
privasi versi 2026-10-27. Website memakai isi privasi 27 Oktober.

- Backup sebelum migrasi: restore PostgreSQL terisolasi, **19 tabel/view cocok**, 10 detik; file terenkripsi
  `rt05-20261027T0332Z.dump.gpg`, SHA-256 `1d99040a4dba5af38c399326c368a5d10b49a2301eaf62acd233c7a4010decd1`.
- Backup setelah migrasi: restore terisolasi **20 tabel/view cocok**, 6 detik (total 12), termasuk tabel kejadian darurat;
  file terenkripsi `rt05-20261027T0406Z.dump.gpg`, 244.980 byte,
  SHA-256 `e783f5088a2723296c4b70652ed31758946918ea1f88c6ebd7010221ffd34150`.
- Keduanya tersalin ke folder Drive privat, `shared=false`; unduh balik byte/hash sesuai.
- Backup final sesudah QA/consent reset: **20 tabel/view cocok**, dua riwayat kejadian ikut dipulihkan,
  restore 6 detik (total 11). `rt05-20261027T0440Z.dump.gpg`, 247.662 byte,
  SHA-256 `b0c1846bbcc4ddd25a9b0cae0b8d3516dc37683300b5ff6fc127e7f0913c5b52`, salinan Drive privat.
- Rekonsiliasi keuangan awal pasca-deploy: **0 masalah**, saldo kas Rp2.180.000.
- HTTP: login warga/pengurus sintetis, consent 27 Oktober, status restricted, pengaturan ditolak untuk warga (403),
  kirim tanpa konfirmasi ditolak (422).

## APK dan perangkat

Workflow [40448591468](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/40448591468) nomor **23**,
source `8d356e44`, success, selesai **Selasa 27 Oktober 2026 11.21.11 WIB**. Waktu manifest 11.21.01 WIB.
Artifact `12709930491`, `rt05-takeda-staging-release-23`. Kedua file cocok manifest, ukuran dan checksum sidecar.

| ABI | versionCode | Byte | SHA-256 |
| --- | --- | --- | --- |
| arm64-v8a | 3023 | 23.006.585 | `1e98008901bef5fe2b09a7bef146bd3e5cb78800a16b172e74a6684edba0b42e` |
| armeabi-v7a | 2023 | 20.857.665 | `2be32e17d5a5e76c2affe0f0b76bec24cc295e16b8a1a23c9605471a7e4ae3cc` |

`apksigner verify --verbose --print-certs` kedua ABI PASS (v2). Paket `tech.morriz.takeda`, versi 1.0.0,
sertifikat tetap `dba8bc3f6179cb747aace1b9bada7abbc568583d26bb17649374f6b759e4069a`.
Pixel_8a headless 2 GiB: `adb install -r` 1021 → kandidat 1022 → 1023 tanpa uninstall;
firstInstallTime tetap **21 Oktober 20.45.35**, lastUpdateTime **27 Oktober 11.22.06**; sesi dipertahankan.
Consent ulang 27 Oktober tampil dengan tanggal benar dan berhasil menuju Beranda warga.
Shortcut tersedia di Home aplikasi untuk kedua peran. Pilihan pin meminta konfirmasi launcher Android;
tidak memasang ikon secara diam-diam dan tidak langsung mengirim laporan. Tidak ada uninstall/wipe AVD.
Pin benar-benar ditambahkan di launcher pada kandidat, tetap ada setelah upgrade 1023; tap saat sesi aktif
membuka langkah awal. Kanal `rt05_darurat_v1`: importance 4, USAGE_ALARM, getar aktif, DND tidak dibypass.

Bukti visual sintetis: [privasi](privacy-1023.png), [Home warga](home-warga-1023.png), [Home pengurus](home-admin-1023.png),
[shortcut launcher](home-shortcut-1023.png), [notifikasi Android](notification-1023.png).

## Notifikasi dan pemeriksaan akhir

Pengiriman riil melalui provider staging, dibatasi dua akun uji. Replay Idempotency-Key memberi ID yang sama;
server mempertahankan identitas pengirim. Warga bisa membaca detail, tanpa catatan pengurus.
Status bergerak **antrean → provider_menerima**, Android benar-benar memposting notifikasi kanal darurat
dengan kategori alarm dan VISIBILITY_PRIVATE; teks generik tanpa nama/alamat. Emulator headless/no-audio:
ini bukti pengiriman dan tampilan, **bukan bukti bunyi/getar HP nyata**.

Temuan observasi: setelah upgrade/consent dan ketukan notifikasi foreground pertama, emulator sekali menampilkan
layar hitam. Log menunjukkan timeout SurfaceSyncGroup; crash UiAutomation ada pada PID lain, tanpa fatal app.
App dibuka ulang **tanpa menghapus data**. Buka biasa dari background, shortcut warm dan intent native yang sama
kemudian berhasil; detail server tampil. Akar penyebab belum dipastikan, jangan menyebutnya bug aplikasi yang fixed
atau menyamakan hasil emulator dengan HP nyata. **Uji ulang notifikasi aktual background kemudian PASS**:
kejadian kedua dikirim melalui FCM, Android menampilkan notifikasi, ketukan membuka detail kejadian yang tepat.
Tidak ada layar hitam pada pengulangan tersebut. Bukti: [notifikasi background](notification-background-1023.png)
dan [detail setelah ketukan](notification-detail-1023.png). Uji HP nyata harus mengulang jalur ini.
Kedua kejadian diselesaikan dengan alasan pengujian, tanpa penalti; riwayat dipertahankan. Pemeriksaan fitur off,
retry penanganan dan pemulihan konfigurasi restricted lulus. Pengurus uji memakai 2/3 kuota laporan/jam,
warga uji belum membuat laporan; jangan menghapus riwayat untuk mereset kuota. Rekonsiliasi akhir tetap nol/Rp2.180.000.

Gejala tampilan membeku juga diamati saat pergantian akun pada renderer lama `swiftshader_indirect`.
AVD yang sama direstart headless, RAM 2 GiB, dengan `-gpu software -no-snapshot`, **tanpa wipe/uninstall**.
Sesi pengurus langsung pulih (login/storage sebelumnya berhasil), Home dan akses panik normal; logout pengurus
dan login warga kemudian PASS tanpa membeku. Ini mendukung dugaan lingkungan renderer, tetapi bukan bukti akar
penyebab final. Tidak ada patch aplikasi yang diklaim memperbaikinya. Uji HP nyata mencakup login/pergantian akun,
ketukan notifikasi dan resume; M19 tetap terbuka.

## Distribusi

Kedua APK dipasang atomik ke volume unduh staging, **metadata `versi.json` terakhir**, Selasa 27 Oktober
**11.37.23 WIB**. HTTPS kedua ABI byte/hash cocok artifact; halaman resmi menampilkan build 1023 dan catatan M19.
Drive diperbarui in-place **11.37.38 WIB**, ID/parent/sharing tetap, nama `rt05-takeda-staging-1023-arm64-v8a.apk`.
Unduh balik binary Drive terverifikasi 23.006.585 byte dan SHA-256 arm64 sama dengan artifact.

- [Halaman unduh resmi (64-bit dan 32-bit)](https://takeda-staging.morriz.tech/aplikasi)
- [Drive, ID tetap](https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view)

Kredensial dua akun uji tersedia di catatan privat Desktop `RT05-akun-uji-M19-1023.txt`; tidak di repo/PR/log.
Keluarga uji bernama 1022 karena dibuat sebelum kandidat dibatalkan; **bukan nomor APK yang terpasang**.
Logout emulator selesai, kedua akun uji **0 perangkat aktif**, consent hanya kedua akun sintetis direset agar
pemilik membaca 27 Oktober di HP. Akun/riwayat uji dipertahankan. Emulator dan PostgreSQL lokal berhenti;
AVD dan pgdata lama dipertahankan, tanpa pembersihan yang ditolak sebelumnya.

## Gate berikutnya

Uji HP nyata pada dua perangkat dengan akun uji: foreground, background, layar terkunci, aplikasi ditutup,
izin notifikasi ditolak/diizinkan, mode senyap/DND dan jaringan gagal/pulih. Catat versi Android/merk serta
hasil bunyi, getar, tap dan akses detail. Penerimaan FCM bukan bukti bunyi di HP. Setelah diterima pemilik,
tutup M19 dengan bukti; gate production/UAT M16 tetap terpisah. Jangan membuka audience warga asli otomatis.
