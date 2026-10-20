# Rilis staging 1008 — 20 Oktober 2026

Waktu dalam WIB. Hanya staging dan akun sintetis; bukan production.

## Yang dirilis

| Langkah | Waktu | Hasil |
| --- | --- | --- |
| PR #55 merge (`e79a10c`) — foto sampul artikel dari CMS tampil di website | 20 Okt 14.22 | CI hijau; website 16 tes, `test:preview` exit 0 |
| PR #50 merge (`4376b44`) — #46 outbox tetap dipantau, #48 draf pengaduan tidak hilang, #47 skrip bukti menegakkan gate | 20 Okt 14.30 | `main` digabung ke branch (merge, tanpa force-push); lokal rest-api 321/321 (3288 assertion), pint OK, Flutter analyze bersih + 230 tes, `m15-evidence-lib` 7/7; CI 9/9 |
| Deploy staging immutable `4376b44` | 14.30–14.32 | `>>> Selesai`, 0 GAGAL, 8 kontainer healthy + gateway; skrip SHA-256 `aa50ca1a…` |
| APK rilis 1008 (run [39386989406](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/39386989406)) | selesai 14.38 | `sha256sum -c` OK (arm64 + armeabi-v7a); sertifikat `dba8bc3f…069a` = kunci rilis; `tech.morriz.takeda` versionCode 3008 |
| Unggah ke Drive tim (versi baru file yang sama, link tetap) | 14.42 | Versi saat ini 1008; versi 1007 dan 1006 tetap di riwayat |

SHA-256 arm64: `ca7054f51327e44ded7759af7b0710147173b5993ab60dc972d1589952ecc416` (22,4 MB), juga tersaji di
`https://takeda-staging.morriz.tech/unduh/rt05-takeda-staging-1008-arm64-v8a.apk`.

## Cek live setelah rilis

| Cek | Hasil | Berkas |
| --- | --- | --- |
| `/health/ready` | `ready`, database ok | — |
| UI CMS staging `website/scripts/cms-ui-live.mjs` (kini mengunggah foto sampul) | **17/17 PASS**, termasuk C05b foto sampul asli tampil di website (bukan SVG) | [staging-cms-ui-17.json](staging-cms-ui-17.json) |
| API CMS `infra/scripts/m14-stage-check.mjs` | **21/21 PASS** | [staging-cms-api-21.json](staging-cms-api-21.json) |
| Vercel `scripts/check-live.mjs` | 21/21 HTML sama dengan `preview/` di `main`, 32 aset, 0 error | — |
| Notifikasi `infra/scripts/m15-notify-latency.mjs` (±16 menit pasca-deploy) | 55,3 dtk (target ≤60) ✓; pengumuman uji diarsipkan | — |

## Penyebab temuan UAT E (foto artikel tetap SVG)

Dibaca dari `konten` staging (SELECT saja): artikel uji tim terbit 19 Okt 22.43 WIB dengan `cover` di snapshot terbit,
artinya unggah foto asli berhasil. Website (`SiteContent::article()` dan tiga template) tidak membawa sampul dan selalu
menggambar SVG. Diperbaiki PR #55 (ADR33 butir 7). Artikel tim itu sudah diarsipkan oleh tim sendiri.

## Aktivitas uji tim 19–20 Oktober (dari `audit_logs` staging, tanpa identitas)

Rentang: 19 Okt 19.59 – 20 Okt 00.45 WIB. 5 akun berbeda login di aplikasi (20 login), 1 akun login CMS web (3 kali).
APK yang dipakai: build 1005 sampai ±21.40, lalu 1006. **APK 1007 tidak sempat diuji**; sesudah 00.45 hanya ada dua
aktivitas kecil (08.21, 09.36) lalu uji sintetis agent mulai 12.07.

| Area | Aksi tercatat |
| --- | --- |
| Pengaduan | 5 dibuat (3 akun), 5 ubah status, 1 diubah pemilik, 1 foto dihapus, 1 diarsipkan |
| Aspirasi | 2 dibuat, 2 ubah status |
| Usulan kegiatan | 6 dibuat (2 akun), 6 ubah status, 1 diubah pengusul |
| Agenda dan pengumuman | 5 agenda, 2 pengumuman terbit |
| Iuran dan kas | 6 tagihan dibuat, 4 pembayaran dicatat, 2 pembayaran dibalik, 3 transaksi kas |
| Rumah, akun, keluarga | 1 rumah dibuat, 10 rumah diubah, 3 registrasi utama, 3 registrasi tambahan, 2 permohonan disetujui, 1 ditolak, 2 keluarga diverifikasi, 1 warga dan 1 akun diarsipkan, 1 reset kata sandi, 9 lihat token, 1 rotasi token |
| CMS web | 4 konten dibuat, 4 diubah, 7 diterbitkan, 4 diarsipkan, 9 foto diunggah |

Belum terlihat di aktivitas (perlu diuji tim dengan 1008): tambah foto pengaduan setelah dibuat, *Minta perbaikan*
permohonan akun, ekspor CSV, pemilih bulan *Mulai tagih*, foto sampul artikel tampil di website.
