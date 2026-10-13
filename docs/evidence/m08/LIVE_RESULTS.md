# M08 — deploy staging dan API live (sintetis)

13 Oktober 2026 (waktu UTC dari mtime log server dan ringkasan). Lingkup hanya project Docker `rt05-staging` di VPS lewat SSH yang diotorisasi
(`BatchMode`, `StrictHostKeyChecking=yes`). Tanpa perubahan DNS, firewall, port, aplikasi lain,
production, env/secret, factory, atau tulis Neon langsung.

## Deploy

| Waktu UTC | SHA | Langkah | Hasil |
| --- | --- | --- | --- |
| sebelum 11:46 | `d790ec2…` | CI sebelum deploy | API `38391645016`, Infra `38391645068`, Mobile `38391645181` (verify + 2 APK), Repo `38391645139`: SUCCESS |
| 11:46 | | Script | Blob `infra/scripts/deploy-staging-immutable.sh` pada SHA kandidat (beda dari M07 hanya allowlist branch M08), disalin ke `/root/.rt05/deploy-staging-immutable-m08.sh` mode 700; SHA-256 `e67f7063631ea305dda1636f7147568af56188494d810b0282957d261790c32b` sama dengan lokal; `bash -n` OK |
| 11:46:05–11:47:41 | | Deploy | `Selesai`, 0 baris GAGAL. Migrasi `2026_10_14_000100_m08_foto_pengaduan_status … DONE` (171 ms), additive. Semua service `rt05-*:d790ec2…`, healthy; previous-sha `979634f` |
| sesudah 11:47 | | Runtime | `/health/ready` ready; heartbeat worker-default/media/exports/scheduler exit 0; `rt05:media-recover` 0/0/0 |
| 12:31:33 | `6b6987c…` | Percobaan pertama memakai SHA penuh yang salah ketik | Ditolak guard sebelum perubahan apa pun ("SHA is not an ancestor of an approved RT05 ref"); staging tetap `d790ec2` |
| 12:32:04–12:33:28 | | Deploy fix notifikasi (CI API/Infra SUCCESS; mobile tidak berubah, Mobile verify kemudian SUCCESS) | `Selesai`, 0 GAGAL, "Nothing to migrate". Semua service `6b6987c`; ready; heartbeat 4/4; previous-sha `d790ec2`; env staging mode 600 ukuran sama |

Rollback: `/root/.rt05/deploy-staging-immutable-m08.sh rollback` ke previous-sha. Skema M08 additive;
image M07 membaca/menulis foto dengan default `siap` (transkode sinkron lama).

## API live

Script: [`infra/scripts/m08-stage-acceptance.mjs`](../../../infra/scripts/m08-stage-acceptance.mjs)
(hanya origin staging, redirect ditolak, fixture/media/output privat di luar Git, ringkasan publik
dipindai terhadap kredensial). Aktor: admin uji M06; `a1` (utama aktif, pelapor), `a3` (aktif,
warga lain), `a2` (menunggu) dari state privat M07. Foto: gambar buatan generator dengan segmen
EXIF berisi penanda GPS sintetis, PNG, WebP, file spoof PHP, SVG ber-script, GIF, PNG dengan header
8000×7000, dan file 13 MB.

| Run | SHA | Hasil | Ringkasan publik |
| --- | --- | --- | --- |
| `Sintetis M08 C25EF8` (selesai 12:00:09) | `d790ec2` | **42/42 kasus, 129 assertion PASS**; foto awal diproses, siap 4 dtk; inbox 63 dtk | [m08-live-summary-d790ec2.json](m08-live-summary-d790ec2.json) |
| `Sintetis M08 CC3EAD` (selesai 12:35:10) | `6b6987c` | **42/42 kasus, 130 assertion PASS** (tambahan: tiap transisi disebut di inbox); siap 4 dtk; inbox 53 dtk | [m08-live-summary-6b6987c.json](m08-live-summary-6b6987c.json) |

Kasus: info API; akun menunggu 403 feed/create; kategori; create anonim dua foto (201, status
`diproses`, url null); worker-media → `siap`, tepi ≤1600; warga lain: pelapor null dan tanpa
ID/email/nama pelapor di detail/feed/riwayat/URL/nama berkas; unduhan `image/jpeg`, `nosniff`,
`private`, penanda EXIF/GPS dan `Exif` hilang; spoof/SVG/GIF 415, >50 MP 413, 13 MB 422; tanpa
laporan parsial; warga lain tidak dapat mengedit (403 `COMPLAINT_LOCKED`); ID foto lewat laporan
lain 404; edit pemilik 200 lalu versi basi 409; pengurus melihat pelapor; transisi invalid 422,
diproses, basi 409, pemilik terkunci 403, selesai, buka ulang tanpa alasan 422, dengan alasan 200;
riwayat aman ("Warga"/"Pengurus RT"); inbox pemilik tepat 3 tanpa identitas, warga lain tidak
menerima; arsip 204, ulang 409, status 409 `ALREADY_ARCHIVED`, tersembunyi dari pemilik 404,
riwayat tetap, filter arsip pengurus; nonaktifkan `a3` → token lama ditolak 401 → aktifkan lagi →
200.

Temuan run pertama yang memicu fix: lihat [ANDROID_RESULTS.md](ANDROID_RESULTS.md) §Temuan 1
(notifikasi status lama membaca status terkini). Run `d790ec2` lulus karena assertion lama hanya
menghitung notifikasi; assertion ditambah dan lulus di `6b6987c`.

## Drill worker-media (bersama Android)

12:18:05 container `rt05-staging-worker-media-1` dihentikan (`docker stop -t 180`, hanya container
itu); pengaduan Android dibuat; foto tetap `diproses` di perangkat. 12:18:47 dijalankan lagi:
healthy, heartbeat 0; foto `siap` setelah tarik-muat-ulang (12:18:55). Job bertahan di Redis
antrean selama worker mati; tidak perlu `rt05:media-recover`.

Data yang ditinggalkan di staging (sintetis): 2 laporan per run live + 1 laporan Android, beberapa
diarsipkan; akun `a3` aktif kembali. Tidak ada cleanup/hapus.

## Run ulang 13 Oktober 2026 (penerimaan akhir)

Staging tetap `6b6987c` (tanpa deploy baru; SSH baca-saja: semua image `rt05-*:6b6987c`, container
sehat, `/health/ready` ready). Script sama (`infra/scripts/m08-stage-acceptance.mjs`, head PR `a3d80e5`).

| Run | SHA | Hasil | Ringkasan publik |
| --- | --- | --- | --- |
| `Sintetis M08 615CCB` (selesai 13:27:06 UTC) | `6b6987c` | **42/42 kasus, 130 assertion PASS**; foto siap 4 dtk; inbox 42 dtk | [m08-live-summary-rerun-615CCB.json](m08-live-summary-rerun-615CCB.json) |

Drill worker-media diulang bersama Android putaran 2: container `rt05-staging-worker-media-1`
dihentikan 13:42:21 UTC dan dijalankan lagi 13:43:23 UTC (healthy dalam 20 dtk); foto `diproses`
lalu `siap` tanpa `rt05:media-recover`. Hanya container itu yang disentuh.
