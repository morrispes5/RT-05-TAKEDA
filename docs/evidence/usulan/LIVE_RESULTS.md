# Usulan kegiatan — deploy staging dan uji API live (sintetis)

16 Oktober 2026 (UTC). Requirement: [USULAN_KEGIATAN.md](../../USULAN_KEGIATAN.md). Hanya project Docker
`rt05-staging` lewat SSH (`BatchMode`, `StrictHostKeyChecking=yes`). Tanpa production, DNS, firewall, port,
atau service lain. Uji dijalankan dari PC sebagai skrip Node tanpa emulator (atas permintaan pemilik agar hemat RAM).

## Deploy

| Waktu UTC | Langkah | Hasil |
| --- | --- | --- |
| 03:21 | Script `infra/scripts/deploy-staging-immutable.sh` dari `ed6fc53` (merge PR #27) disalin ke `/root/.rt05/deploy-staging-immutable-usulan.sh` mode 700 | SHA-256 `aa50ca1a629ba5a7b0c29b7c24ac357118a96f5ded6246a35f55e693b6c52c75` sama dengan lokal; `bash -n` OK |
| 03:21–03:32 | Deploy `ed6fc537a2a0daeccb0f14d0062f5904fece2b29` (sebelumnya `617e179`) | `Selesai`, 0 baris GAGAL. Migration additive: `2026_10_15_000100_create_jobs_table` DONE, `2026_10_15_000200_create_usulan_kegiatan_tables` DONE |
| 03:33 | Pemeriksaan | 9 container `rt05-staging` sehat pada image `ed6fc53`; `/health/ready` ready; 13 container lain tidak disentuh |

Rollback: `/root/.rt05/deploy-staging-immutable-usulan.sh rollback`.

## API live

Skrip: [`infra/scripts/usulan-stage-acceptance.mjs`](../../../infra/scripts/usulan-stage-acceptance.mjs). Aktor sintetis:
admin uji M06, `a1` (pengusul), `a3` (warga lain), `a2` (menunggu). Dokumen TXT/PDF dibuat di memori.

| Run | SHA | Hasil | Ringkasan |
| --- | --- | --- | --- |
| `Sintetis USULAN ED6ABE` | `ed6fc53` | Berhenti di U06 karena **pengecekan skrip salah** (mencari kata `proposal` yang memang ada di path `/activity-proposals/`); aplikasi menjawab benar (201). Satu usulan uji tertinggal berstatus diajukan | — |
| `Sintetis USULAN 3E1FC3` (selesai 03:34:09) | `ed6fc53` | **24 kasus, 50 assertion PASS**; notifikasi 42 dtk | [usulan-live-summary-ed6fc53.json](usulan-live-summary-ed6fc53.json) |

Yang dibuktikan: akun menunggu 403; pernyataan wajib dan batas 24 jam 422 dengan pesan per field; HTML bernama
`.pdf` dan DOCX 415; usulan dengan TXT + PDF 201 tanpa nama berkas asli di URL; warga lain 404 untuk usulan dan
tautan dokumen; usulan tidak tampil sebagai agenda sebelum disetujui; pengurus melihat pengusul dan daftar bentrok;
revisi tanpa catatan 422, dengan catatan 200; edit pemilik versi basi 409, lalu kembali Diajukan; tautan unduh
bertanda tangan bekerja tanpa token, signature diubah 403 `INVALID_SIGNATURE`; **persetujuan langsung membuat tepat
satu agenda**, keputusan kedua 409 `PROPOSAL_SCHEDULED`; agenda tampil untuk warga lain; notifikasi: warga lain dan
pengusul masing-masing satu notifikasi agenda, pengusul "perlu revisi" dan "disetujui", pengurus "usulan baru" dan
"diperbaiki", warga lain tanpa notifikasi usulan dan tanpa identitas pengusul. Agenda uji diarsipkan di akhir;
usulan dan riwayatnya tetap.

Temuan saat uji: skrip mencetak URL bertanda tangan utuh ke log/ringkasan. Diperbaiki (disamarkan) sebelum disimpan;
tautan itu sudah kedaluwarsa (5 menit) dan hanya untuk dokumen sintetis.

Belum: uji Android nyata (ditunda atas permintaan pemilik agar tidak memakai RAM), push FCM (M10).
