# Strategi pengujian dan UAT

## Prinsip

Uji aturan dan alur nyata, bukan tes yang hanya menyalin implementasi. Perubahan dokumen cukup diperiksa konsistensi/link; tidak memerlukan aplikasi dibangun. Implementasi keuangan, izin, worker recovery, serta migration wajib diuji perilaku.

## Layer

| Layer | Pemeriksaan |
| --- | --- |
| Domain/API | PHPUnit pada Postgres; action, policy, validation, transaction, constraints |
| Contract | OpenAPI schemas/examples terhadap response endpoint aktif |
| Web | Existing Laravel/Pint/build/export/browser; CMS real API |
| Mobile | analyze, unit state/repo, widget forms, integration feature |
| Infra | Compose validation, build images, health, network/storage, worker restart |
| End-to-end | Perangkat/browser →API→Neon→worker→hasil terlihat |
| UAT | Mitra/warga melaksanakan tugas dan menilai dengan catatan nyata |

## Skenario kritis

### Identitas/keluarga

Token valid/invalid/revoked/leading zero; rotasi manual+scheduled; registrasi collision rumah/email; akun tambahan pending/approve/reject; akun inactive dengan token valid; role mass-assignment ditolak; primary house link concurrent ditolak; pindah penghuni mempertahankan histori.

Lupa password response generik; reset token expiry/reuse; reset mencabut session/token lain; rate limit; CMS CSRF missing/correct; pending user tidak membaca data warga.

### Pengaduan/aspirasi

Warga A membuat laporan/foto; B membaca tanpa identitas jika privat; admin membaca identitas; B tidak edit A; owner edit hanya submitted; status invalid/stale version ditolak; histori+notifikasi setelah commit; archive mempertahankan histori; upload file spoof/oversized/metadata.

### Iuran/keuangan

1. Rumah satu, tiga akun: charge tetap satu per bulan.
2. Generate period dua kali atau concurrent: satu charge.
3. Tarif 75.000 bulan A, 80.000 bulan B: charge A tidak berubah.
4. Multi-month nominal campuran mengikuti snapshot, bukan jumlah bulan×tarif terbaru.
5. Pencatatan offline sukses: payment/allocations/cash/audit/outbox semua ada.
6. Nominal parsial/berlebih, charge paid, periode invalid: reject tanpa partial write.
7. Idempotency same key/same payload: response replay, satu posting.
8. Same key/different payload:409.
9. Dua admin bayar charge sama bersamaan dengan key berbeda: tepat satu sukses, satu conflict.
10. Timeout client setelah commit→retry key sama: tidak ganda.
11. DB failure sebelum commit: semua rollback.
12. Queue down setelah commit: ledger tetap satu, side effect pending dapat pulih.
13. Reverse sekali: charge unpaid, reversal ledger sign benar; reverse kedua ditolak/replay.
14. Correct atomik dengan replacement: histori asal tetap, saldo hasil benar.
15. Kas manual tidak dapat memposting iuran kedua; opening balance dedup.
16. Rumah/keluarga diarsipkan: histori pembayaran dan kas tetap benar.
17. Histori ≥12 bulan; ekspor aggregate sama API/ledger.
18. CSV formula injection tersanitasi.

Concurrency diuji dengan transaksi/proses independen pada Postgres, bukan sekadar memanggil service dua kali dalam satu test transaction.

### Worker/scheduler

Kill/restart setelah enqueue; outbox published tapi completion hilang; lease expired; duplicate event; Redis AOF restart; queue lost→replay; failed push/email; media failed; export retry; scheduled overlap; H-1 reminder berubah agenda; tidak mengirim reminder untuk agenda lewat.

### Publikasi

Browser A admin publish→browser B public membaca revision; draft edit tidak berubah public; stale save conflict; archive hilang publik; private media denied; initial JSON import repeat aman; API unavailable last-good fallback tanpa draft; no secrets/draft in static export.

### Android

Perangkat nyata: fresh install signed APK, login/token expiry/logout, keyboard/form, kamera/galeri, permission denial, koneksi lambat/lost, push foreground/background/terminated, deep link policy, multi-month finance admin, accessibility label/font scaling.

## Acceptance matrix

Setiap issue menghubungkan FR dan BR ke test identifier serta bukti. Contoh: FR13/BR11–BR17 →FIN-05..FIN-14. Kriteria selesai milestone ROADMAP dipenuhi oleh hasil nyata, bukan placeholder screenshot.

## UAT

Peserta: pengurus dan warga yang disepakati mitra; jumlah tidak dikarang. Gunakan data sintetis pada staging dahulu. Tugas:

- Temukan fasilitas/album tanpa login.
- Daftar dan periksa keluarga.
- Kirim pengaduan privat; admin proses; warga lihat status.
- Kirim aspirasi; lihat agenda/add-to-calendar/pengumuman.
- Admin catat dua bulan offline; warga lihat status; admin koreksi.
- Lihat kas/transparansi; ekspor; publish artikel/album.

Catat success/failure, waktu, kebingungan, bug, dan penilaian kemudahan 1–5. Target ≥80% peserta memberikan penilaian membantu/mudah sesuai definisi survei yang dicatat. Jangan membuat nilai UAT palsu.

## Release gate M15/M16

Alur kritis lulus; tidak ada temuan kritis/high data/keuangan; staging memakai provider nyata untuk reset/push; backup restore drill; APK signed dipasang; DNS/TLS valid; rollback drill; privacy notice/data mitra valid; pending feature diumumkan.

P95 API diuji 20 pengguna concurrent workload realistis, warm/cold Neon terpisah. Target ≤1 detik nonupload; hasil jika meleset dijelaskan dan diatasi atau keputusan target dicatat. Tidak memperbanyak load test ke production tanpa ruang lingkup.

## Bukti

Simpan laporan CI/command, test IDs, screenshot berguna, anonymized UAT, dan restore/rollback report di docs/evidence/ atau artifact CI. Dilarang credential/data asli warga pada repo public. PROGRESS mencatat perintah, exit/result, commit, environment, dan keterbatasan.
