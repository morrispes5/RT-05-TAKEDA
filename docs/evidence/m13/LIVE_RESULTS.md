# M13 — Deploy staging, impor katalog, dan cek live (18 Oktober 2026)

Hanya project Docker `rt05-staging`. Akses dari PC pemilik lewat **Cloudflare WARP + IPv6 VPS** (`2a02:4780:59:7d0b::1`,
alias SSH `rt05-vps-v6`, host key diverifikasi), karena sejak ganti ISP jalur IPv4 PC ke semua server Hostinger putus
(lihat PROGRESS). Tanpa perubahan DNS, firewall, port, atau service lain.

## Deploy

| Langkah | Hasil |
| --- | --- |
| Skrip deploy dari `88c7302` (main: M13 `aa72ce1` + docs ADR29) → `/root/.rt05/deploy-staging-immutable-m13.sh` | SHA-256 diawali `aa50ca1a629ba5a7`; `bash -n` OK |
| Deploy `88c73023789c32faefeb34533c3f07f8c75dca99` | `Selesai`, 0 GAGAL; migration `2026_10_17_000200_create_konten_tables` DONE |
| Pemeriksaan | 9 container `rt05-staging` sehat; `/health/ready` ready |
| `php artisan rt05:konten-impor` dua kali | 13 baru lalu 0 baru / 13 dilewati (idempotent di staging) |

Catatan host: `morriz-commerce-uat-db` (proyek lain) berhenti sejak 17 Okt 14.04 UTC dengan restart policy `no`;
tidak terkait deploy RT05 dan tidak disentuh.

## Cek live

Skrip [`infra/scripts/m13-stage-check.mjs`](../../../infra/scripts/m13-stage-check.mjs) (dijalankan dengan preload yang
mengarahkan host staging ke IPv6). Ringkasan: [m13-live-summary-88c7302.json](m13-live-summary-88c7302.json).

**14/14 PASS**: artikel publik hasil impor; album 17 Agustus tanpa tahun dengan foto statis; urutan fasilitas katalog;
profil (ketua tampil, sekretaris "Nama belum dipublikasikan"); respons publik tanpa ID akun; warga 403 di admin
konten; draf tidak bocor (404); terbit 200; versi basi 409; snapshot stabil; arsip hilang dari publik (404). Artikel
uji sintetis diarsipkan di akhir.
