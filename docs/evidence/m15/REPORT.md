# M15 — Bukti integrasi, keamanan, performa, dan kandidat APK (19 Oktober 2026)

Staging saja (`rt05-staging`, akun sintetis). Akses PC pemilik lewat Cloudflare WARP + IPv6 VPS. Keputusan: ADR31.
Ini **bukan** UAT mitra dan bukan uji HP nyata; dua hal itu menunggu pemilik (lihat "Belum").

## Deploy staging

| SHA | Isi | Hasil |
| --- | --- | --- |
| `6c7f864` | PR #43: draf kamera, SecurityHeaders, Sanctum `last_used_at` mati | Selesai, 0 GAGAL |
| `0717e51` | PR #44: IdleGuard, healthcheck web `/up`, APK per ABI | Selesai, 0 GAGAL; cek live M14 21/21 |
| `244a73c` | PR #45: mutex scheduler pendek | Selesai, 0 GAGAL; 9 container sehat |

Skrip deploy immutable diunggah via `scp`, SHA-256 `aa50ca1a629ba5a7…` diverifikasi di VPS sebelum dijalankan.

## APK rilis (kunci tetap)

| Run | Berkas | Hasil |
| --- | --- | --- |
| [39190119952](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/39190119952) | universal `rt05-takeda-staging-1004.apk` 63,8 MB | analyze, 219 tes, build, verifikasi v2 + sertifikat lulus |
| [39191579464](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/39191579464) | `rt05-takeda-staging-1005-arm64-v8a.apk` 22,4 MB, `…-armeabi-v7a.apk` 20,2 MB | lulus; dikirim ke pemilik (arm64) |

Diverifikasi ulang di PC: `sha256sum -c` OK, `apksigner` sertifikat
`dba8bc3f6179cb747aace1b9bada7abbc568583d26bb17649374f6b759e4069a` (= `RT05_RELEASE_CERT_SHA256`), skema v2,
`tech.morriz.takeda`, versionCode 3005 (arm64), tidak debuggable, `allowBackup=false`, izin: INTERNET, CAMERA,
WAKE_LOCK, ACCESS_NETWORK_STATE, POST_NOTIFICATIONS, C2DM RECEIVE. SHA-256 arm64:
`4d359b5761db49d09c1b698ce4d97eaebfde504459405a25c3ac43d73de5164c`.

## Keamanan

- `composer audit` rest-api dan website: 0 advisory. `npm audit` website: 0.
- GitHub secret scanning: 0 alert terbuka. Riwayat Git (`git log -p --all`) untuk pola Neon `npg_`, URL Postgres
  ber-password ke Neon, private key PEM, `private_key_id`, token `ghp_`, `APP_KEY` base64: 0 temuan.
- Repo publik; Dependabot alerts nonaktif (pengaturan repo, rekomendasi ke pemilik).
- Header live staging sebelum perbaikan: website tanpa HSTS dan tanpa proteksi framing (CMS `/pengurus` rawan
  clickjacking); `/reset-password` API sudah CSP + `X-Frame-Options: DENY`. Perbaikan `SecurityHeaders` (ADR31 butir 5).
- Cakupan tes yang ada: CSV formula injection, spoof/oversize upload, foto di-encode ulang tanpa metadata, anonim
  aduan/aspirasi termasuk pelapor pengurus, akses akun nonaktif/menunggu, CSRF/origin CMS.

## Performa (`infra/scripts/m15-load-check.mjs`, 20 pengguna, 60 dtk, campuran layar warga + website)

| Kondisi | Request | Error | P50 | P95 total | P95 API terburuk |
| --- | --- | --- | --- | --- | --- |
| Sebelum (staging `08f2a1e`) | 837 | 0 | 416 ms | 928 ms | 1160 ms (`/dues/my`) |
| Sesudah `6c7f864` | 844 | 0 | 420 ms | 781 ms | 989 ms (`/public/articles`) |

Berkas: [sebelum](load-20u-before-6c7f864.json), [sesudah](load-20u-after-6c7f864.json), [1 pengguna](load-1u-baseline.json).
Pengukuran di container API: koneksi baru Neon 130–440 ms, `select 1` ±55–65 ms (pooled = direct, TCP 17 ms).
Latensi didominasi jarak Indonesia→Neon Singapura × jumlah query (ADR31 butir 4).

## Kuota Neon dan notifikasi

- Sebelum IdleGuard: branch staging aktif ±95% waktu (48,2 CU-jam dalam 8,4 hari) → kuota 100 CU-jam/bulan project
  (dipakai juga branch production) diperkirakan habis ±28 Okt. Compute `active` terus sejak 17 Okt 14.51 UTC.
- Insiden: setelah deploy `0717e51` outbox macet (mutex 24 jam tertinggal); uji latensi 03:25 UTC **FAIL** (tidak
  sampai 120 dtk). Pulih dengan `schedule:clear-cache`; diperbaiki PR #45.
- `infra/scripts/m15-notify-latency.mjs` (pengumuman uji → inbox warga, lalu diarsipkan):

| Waktu (UTC) | Kondisi | Hasil |
| --- | --- | --- |
| 03:25 | setelah deploy `0717e51`, mutex macet | FAIL (null dalam 120 dtk) |
| 03:33 | aktif (penanda IdleGuard ada) | 44,4 dtk ✓ |
| 03:45 | idle (`active=false`) | 6,3 dtk ✓ |
| 03:50 | ±2 menit setelah deploy `244a73c` | 50,1 dtk ✓ |

- Neon setelah `244a73c` (Neon API, tanpa membangunkan compute): compute staging bangun ulang 03.20 UTC (sebelumnya
  aktif terus sejak 17 Okt), lalu `current_state: idle`, `suspended_at` 04.15.02 UTC, ±5 menit setelah `last_active`
  04.09.51. Sisa bangun terjadwal: sweep pengaman + pengingat agenda + rotasi token di menit :00/:05 tiap jam.

## Aksesibilitas otomatis (`website/scripts/a11y-live.mjs`, axe-core WCAG 2.1 A/AA, staging `244a73c`)

`/`, `/profil`, `/fasilitas`, `/dokumentasi`, `/artikel`, `/kontak`, `/pengurus/masuk` (CMS mode server) pada 1280 px
dan 360 px: **0 pelanggaran**. Keyboard: Tab pertama = "Lewati ke konten", isian email login CMS terjangkau dengan Tab.
Slideshow hero (interval, tanpa tombol jeda, reduced motion, tab tersembunyi) diuji `npm.cmd run test:preview` (PASS).
Ini bukan pengganti uji pembaca layar/TalkBack manual di HP.

## Belum (bukan PASS)

UAT mitra (peserta nyata, `docs/UAT_M15.md`), pemasangan APK rilis + uji HP nyata (kamera ditutup paksa, koneksi lambat,
push fg/bg/terminated), verifikasi developer Android (identitas pemilik), privacy notice final (kontak/retensi),
uji TalkBack/pembaca layar manual, latihan pindah PostgreSQL cadangan (ADR29 belum diimplementasi), uji shared hosting.
