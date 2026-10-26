# Perbaikan temuan QA — 26 Oktober 2026

Status: **fixed pada kode lokal, terverifikasi otomatis** di branch `codex/m18-perbaikan-qa`,
berbasis main `1955d69`. Bukti audit asal: [QA_REVIEW.md](QA_REVIEW.md).
Belum menjadi bukti deploy staging, APK baru atau HP nyata. Staging/APK 1020 sebelumnya tetap berjalan.
Tidak ada perubahan ledger, skema database, aturan titipan manual atau nominal transaksi historis.

## Perubahan dan bukti penutupan

| Temuan | Perbaikan | Bukti trigger tidak lagi lolos dan kontrol sah |
| --- | --- | --- |
| QA-S1 budget recovery | Limit IP agregat sebelum hash: forgot 20/15 menit, login 60/menit, reset 60/15 menit; bucket IP+email tetap 3/5/10 dan antar operasi terpisah. Tiga budget agregat dapat dikonfigurasi untuk NAT bersama | Rotasi email/kapitalisasi/spasi mencapai 429 sebelum hash tambahan; IP lain tetap 202. Login mobile/CMS berbagi budget; reset tetap dapat digunakan sesudah kuota forgot habis |
| QA-S3 hash-work akun | Unknown/invalid-password login, recovery recent/unknown/inactive, serta reset missing/expired menjalankan hash pembanding dengan driver/cost konfigurasi | Tes mengamati panggilan hash; respons tetap generik, token recent tidak diganti/email tidak digandakan. Login sah, password lama lemah, batas 72 byte, reset sah/sekali pakai dan pencabutan sesi tetap lulus. Ini penyamaan kerja hash, bukan klaim timing jaringan identik |
| QA-S2 dokumen sesi lama | Guard bersama memeriksa ready, token, akun dan epoch; mounted diperiksa sebelum native launch dan sebelum pesan hasil | Lima widget test: kontrol aktif membuka sekali; pop/logout+akunB/token sama dipakai ulang/consent berubah menghasilkan nol launch. Uji snapshot selama storage signIn tertunda juga menolak token yang sudah berubah |
| QA-F2 push/consent | Push diantre sampai layanan siap; recheck sesi sesudah mark-read/endOfFrame; inbox memakai guard yang sama. Antrean startup dipertahankan untuk akun pertama, antrean akun lama dibuang saat logout | Push tanpa consent tidak mark-read/navigasi; startup dilanjutkan setelah consent akun sama; akun berganti tidak mewarisi antrean. Race push/inbox setelah logout ditolak; kontrol normal tetap membuka detail |
| Kompatibilitas akun menunggu | Hak mendaftarkan push dipisah dari hak membuka layanan | Reviewer menemukan risiko hilangnya push persetujuan pada kandidat awal. Diperbaiki; tes aplikasi utuh membuktikan pending melakukan PUT perangkat dan refresh token, tetapi tetap tidak membuka layanan |
| QA-F1 sisa cicilan | Nominal otomatis dan label bulan memakai sisa; nominal yang diketik warga tetap dipertahankan | Tagihan Rp75.000, terbayar Rp50.000 memberi Rp25.000. Mengubah nominal sendiri tetap dihormati. Bukti transfer biasa/M15b dan M18 tetap lulus |
| QA-F3 copy pembalikan | Dialog menyatakan saldo tagihan dihitung ulang, bukan semua tagihan kembali belum dibayar | Tes konfirmasi pembatalan memeriksa pesan baru dan tidak adanya klaim lama; alur idempotensi/hasil tetap lulus |

Satu investigator read-only sebelum patch dan satu reviewer bypass/regresi independen setelah patch digunakan.
Parent memverifikasi temuan reviewer akun pending terhadap route perangkat, dokumentasi dan tes positif.
Tidak ada temuan tambahan terkonfirmasi pada siklus review tersebut. Tidak mengklaim review menyeluruh baru.

## Validasi akhir yang benar-benar dijalankan

Semua tes DB memakai `127.0.0.1:54329/rt05_test`, role runtime/migrator lokal terpisah.
Tidak memakai `php artisan ... --env=testing` atau Neon untuk tes.

| Perintah / lokasi | Hasil |
| --- | --- |
| `php -l` AuthController dan PasswordPolicy | PASS |
| `php artisan test --filter='M18QaAuthTest\|M06PasswordResetTest\|M06ReviewFixesTest\|M06FollowUpTest\|M06SessionTest'`, rest-api | **55/55**, 575 assertions |
| `php artisan test`, rest-api | **413/413**, 4.359 assertions, 148.638 ms |
| `php vendor/bin/pint --test`, rest-api | PASS |
| `dart format --output=none --set-exit-if-changed lib test`, mobile | **98 file, 0 berubah** |
| `flutter analyze --no-pub`, mobile | Bersih |
| `flutter test --no-pub --concurrency=2`, mobile | **326/326**, sekitar 58 detik |
| `php artisan test`, website | **40/40**, 538 assertions, 22.483 ms |
| `npx.cmd --yes @redocly/cli@2 lint docs/openapi.yaml` | PASS, tanpa warning kontrak |
| `node infra/scripts/check-repo.mjs` dan `git diff --check` | PASS |

Tujuh tes backend baru berada di `rest-api/tests/Feature/M18QaAuthTest.php`. Delapan belas tes mobile baru
berada di `m18_private_document_session_test.dart`, `m18_qa_flows_test.dart` dan tambahan `session_test.dart`;
assertion copy pada `m12_finance_admin_test.dart` diperbarui. Dependency test launcher sudah ada secara
transitif; kini dinyatakan sebagai dev dependency langsung dengan versi sama 2.3.2, tanpa update paket runtime.

Suite penuh pertama Flutter menemukan assertion teks pembatalan lama; sesudah disesuaikan dengan perilaku
yang diminta, suite penuh akhir 326/326. Review juga membuktikan dan memperbaiki kandidat regresi pending-push
sebelum run final. Tes skala backend: 300 rumah/3.600 tagihan, validasi 16.669 ms, laporan tahunan 7 query.

## Batas dan langkah distribusi

HTTP/native launcher/FCM dalam tes mobile memakai fake, tidak mengakses dokumen warga atau browser nyata.
Tidak mengukur klasifikasi timing jaringan atau menyerang staging dengan beban recovery. Hasil scan lama
tetap immutable; laporan verifikasi perbaikan terpisah disimpan melalui artifact tool.
API/OpenAPI dan contoh konfigurasi diperbarui; ERD tidak berubah karena tidak ada perubahan skema.

Berikutnya CI/PR, lalu deploy staging yang terotorisasi, validasi ulang alur auth dan mobile, kemudian satu
APK perbaikan gabungan. Jangan menandai APK 1020 sudah mengandung patch ini. Production/DNS/firewall tidak disentuh.
PostgreSQL lokal dihentikan; pgdata yang sebelumnya ditolak penghapusannya tetap dibiarkan tanpa proses aktif.
