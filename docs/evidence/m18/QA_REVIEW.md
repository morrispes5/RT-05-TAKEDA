# Audit QA ulang — 26 Oktober 2026

**Tindak lanjut:** pemilik menyetujui perbaikan; lima bug dan copy pembalikan kini diperbaiki/diuji pada kode lokal.
Lihat [QA_FIXES.md](QA_FIXES.md). Isi di bawah mempertahankan hasil audit sebelum patch; bukan status patch terbaru.

Target: `1955d69a652f5ab107b78849edfd3d7e225eb411` (main sesudah penutupan M18), worktree
`m17-verifikasi-1018`, branch `codex/m18-qa-audit`. Pemilik meminta cari bug dahulu.
Audit ini tidak mengubah implementasi, merge, deploy, konfigurasi production atau APK.

## Kesimpulan dan cakupan

**Lima temuan baru direproduksi:** dua security medium, satu security low, dua fungsional P2.
Satu masalah teks pembalikan lama tetap terbuka. Tidak ditemukan high/critical atau uang hilang/ganda
pada cakupan yang diperiksa. Kelulusan regresi tidak menutup temuan ini. **Perbaiki sebelum gate production M16**;
bukti M18 passed staging sintetis/emulator tetap berlaku sebagai bukti historis, bukan clearance production.

Security scan Standard `543d9975-2183-4c04-96d5-7595e7be2526` selesai dengan **cakupan manual parsial**.
Laporan deterministik, threat model dan PoC/log berada di workbench Codex Security lokal, bukan Git publik.
Baseline membaca penuh 129 file, mobile 33 file: **141 file unik** setelah deduplikasi, ditambah pemetaan arsitektur,
review keuangan terarah, pencarian sumber dan seluruh suite regresi. Tidak mengklaim setiap baris dari 1.069 file
terlacak dibaca manual. Implementasi dependency, history Git, isi binary/APK, runtime privat, live database
grants/proxy, production dan HP nyata tidak diperiksa penuh. Tidak memakai data warga untuk PoC.

## Security yang tervalidasi

### QA-S1 — Medium: mengganti email melewati budget lupa sandi

`rest-api/app/Providers/AppServiceProvider.php:34` hanya memberi batas tiga / 15 menit per IP+email.
Email baru mendapat bucket baru; email tidak terdaftar mencapai `Hash::make` di
`AuthController.php:192–208` (bcrypt default cost 12). Route publik/gateway sumber tidak menambah budget IP agregat.

Bukti lokal: delapan email sintetis berbeda dari IP sama memberi delapan HTTP 202/delapan panggilan hasher.
Hasher dimock; tidak melakukan DoS atau mengukur ambang gangguan layanan live. Klien tanpa akun dapat terus
memicu pekerjaan CPU mahal; batas email sama tetap bekerja. **Perbaikan:** budget agregat per IP sebelum DB/hash,
tetap pertahankan per IP+email, respons generik dan pertimbangan NAT bersama. Tes email berganti harus dibatasi.
CWE-770, kandidat `qa-auth-throttle`.

### QA-S2 — Medium: dokumen A diluncurkan setelah logout dan login B

`mobile/lib/features/warga/activity_proposals.dart:694–698` memanggil `launchUrl` sesudah await tanpa
recheck sesi asal; pemeriksaan mounted baru setelah launch. Reproduksi: A meminta dokumen → tahan respons →
pop sampai page disposed → helper logout menerima 204 → masuk B → lepaskan respons A. Launcher dipanggil
satu kali ketika B aktif. Kontrol saat A/page masih aktif juga lulus: **dua widget test**, tanpa exception.

Pengguna perangkat bersama berikutnya dapat melihat dokumen A. Memerlukan request sah yang tertunda dan
pergantian sesi, bukan IDOR arbitrer. Backend memeriksa pemilik/pengurus ketika membuat link, lalu download
hanya berdasarkan signature lima menit (`ActivityProposalController.php:78–94`). HTTP/native launcher memakai
fake; tidak membuka browser/dokumen warga nyata. **Perbaikan:** simpan epoch/akun; setelah await cek mounted dan
sesi yang sama sebelum launcher. Tes pop/logout/pergantian akun harus menghasilkan nol launch.
CWE-200, kandidat `qa-late-document-launch`.

### QA-S3 — Low: hash-work memberi petunjuk keberadaan email

`PasswordPolicy.php:40–46` berhenti sebelum bcrypt ketika akun tidak ada; login mobile/browser memakainya
(`AuthController.php:94–96,115–117`). Recovery juga melewati hash ketika token akun masih baru
(`PasswordReset.php:19–26`), sedangkan email tidak dikenal selalu melakukan dummy hash.

Bukti lokal: hash-check login dikenal/tidak dikenal 1/0, pesan gagal identik; hash-make recovery
known-recent/unknown 0/1, respons tetap 202. Ini petunjuk inferensi akun melalui sampling waktu, bukan sandi
bocor/login bypass. Akurasi klasifikasi jaringan belum diukur dan limit per IP+email membatasi sampling.
**Perbaikan:** sesudah budget agregat diperbaiki, samakan kerja hash pada jalur kegagalan/recovery;
dummy verification atas hash sintetis dapat digunakan, pertahankan respons generik.
CWE-208, kandidat `qa-login-enumeration`.

## Fungsional

| ID / prioritas | Bukti dan dampak | Lokasi / perbaikan |
| --- | --- | --- |
| QA-F1 / P2 | Tagihan Rp75.000, terbayar Rp50.000, sisa Rp25.000. Mencentang bulan bukti transfer mengisi **75000**, direproduksi widget test. Dapat menyesatkan; nominal masih dapat diedit dan belum membukukan uang | `mobile/lib/features/warga/dues_proof.dart:55–65,215–223`: gunakan sisa untuk saran nominal dan jelaskan cicilan |
| QA-F2 / P2 | `needsPrivacy=true` di halaman consent. Push menandai notifikasi dibaca dan membuka `AgendaByIdPage` di atas gate; widget test membuktikan detail aktif sementara consent belum diberikan | `mobile/lib/main.dart:55–61`, `notification_center.dart:36–51`: antrekan sampai consent, recheck sesi/epoch setelah await. Bukan kebocoran akun lain; guard kas server tetap bekerja |
| QA-F3 / Low, lama | Dialog pembatalan mengatakan semua tagihan kembali belum dibayar; pembayaran M18 lain dapat tetap melunasinya. Inspeksi sumber, bukan selisih ledger | `mobile/lib/features/pengurus/pengurus_pages.dart:675`: ganti dengan saldo tagihan dihitung ulang, gabungkan dalam paket mobile berikutnya |

## Validasi yang dijalankan ulang

Backend hanya PostgreSQL lokal `127.0.0.1:54329`, `rt05_test`, role runtime/migrator terpisah.
Tidak memakai `php artisan ... --env=testing` atau Neon untuk tes. Flutter memakai runtime resmi lokal, width 160.

| Lokasi / perintah | Hasil |
| --- | --- |
| `rest-api/`: `php artisan test` | **406/406**, 4.316 assertions, 162.289 ms |
| `rest-api/`: `php vendor/bin/pint --test` | PASS |
| `website/`: `php artisan test` | **40/40**, 538 assertions, 20.571 ms |
| `mobile/`: `dart format --output=none --set-exit-if-changed lib test` | **96 file, 0 berubah** |
| `mobile/`: `flutter analyze --no-pub` | Bersih |
| `mobile/`: `flutter test --no-pub --concurrency=2` | **308/308** |
| Root: `node infra/scripts/check-repo.mjs` | PASS, 1.069 file sebelum laporan ini |
| Root: `node --test infra/scripts/m15-evidence-lib.test.mjs` | **7/7** |
| Root: `node infra/scripts/check-m03-compose.mjs` | PASS |
| `composer audit` di `rest-api/` dan `website/` | Tidak ada advisory |
| `website/`: `npm.cmd audit --omit=dev` | 0 vulnerability dependency runtime |
| PoC lokal `php vendor/bin/phpunit --configuration phpunit.xml <QaAuthBoundaryTest.php>` | **3/3**, 9 assertions; hash-work/bucket |
| PoC lokal `flutter test --no-pub <mobile_private_document_late_launch_test.dart>` | **2/2**, kontrol dan race sesi |
| PoC lokal `flutter test --no-pub --concurrency=1 <qa_functional_test.dart>` | **2/2**, nominal cicilan dan push/consent |

Tujuh PoC berada di artifact scan, belum menjadi tes regresi repo. Lulus berarti **bug berhasil direproduksi**,
bukan diperbaiki. Suite 406 mencakup skenario rapat, partial/excess/relief/deposit/reversal, flag off, atomic rollback,
SQL runtime menolak perubahan nominal/history ilegal, rekonsiliasi, konkurensi proses PHP independen,
kesamaan JSON/CSV/XLSX dan skala 300 rumah / 3.600 tagihan. Pada run ini validasi skala 14.849 ms, laporan tahunan 7 query.

Uji beban **baca** staging, akun sintetis:
`node --require <v6-lookup.cjs> infra/scripts/m15-load-check.mjs 60 20`.
**899/899 expected**, 0 unexpected, 15 request/detik, p50 342 ms, **p95 656 ms** (gate <1.000 ms).
856 respons 200 dan 43 respons 404 yang eksplisit diharapkan untuk fixture admin tanpa rumah di `/household`
dan `/dues/my`. Skrip tidak menerima 4xx sembarang sebagai sukses.

## GitHub dan tindak lanjut

- Hanya PR **#26** terbuka, dokumentasi runtime Cloud Agent; tiga checks sukses, tidak digabung.
- Checks main/rilis 1020 sukses. Run 1019 canceled disengaja, bukan CI kode gagal yang belum ditangani.
- Issue **#47** masih terbuka; klasifikasi/gate sudah diperbaiki, tes 7/7 serta uji 20 pengguna di atas lulus.
  Catatan lama 404 belum dijelaskan sudah kedaluwarsa; audit tidak menutup issue otomatis.
- Issue **#57** adalah keputusan hosting production, bukan bug aplikasi.
- Ada komentar historis berbeda dari wiring aktual (FPM/FrankenPHP, nama route media, komentar link sekali-pakai
  meski reusable 5 menit); belum terbukti sebagai exploit terpisah. Selaraskan saat area terkait diperbaiki.
- Tidak ada issue/komentar publik, perbaikan implementasi, merge/deploy atau APK baru pada audit ini.

Berikutnya: auth budget+equal-cost dengan tes → mobile session/consent → nominal bukti+copy pembalikan →
regresi penuh dan staging/emulator → satu APK perbaikan bila gate lulus. Titipan tetap manual, keputusan bisnis
M18 tidak perlu ditanya ulang. Live grants/proxy dan UAT HP nyata tetap gate M16. Jangan menonaktifkan v2
staging atau rollback ke M17 setelah ledger v2 terpakai. PostgreSQL lokal dihentikan setelah audit;
pgdata yang sebelumnya ditolak penghapusannya dibiarkan, tanpa proses berjalan.
