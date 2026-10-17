# M11 — Deploy staging dan cek live (17 Oktober 2026)

Hanya project Docker `rt05-staging`, lewat SSH (`BatchMode`, `StrictHostKeyChecking=yes`). Tanpa production, DNS,
firewall, port, atau service lain. Cek live hanya membaca data: tidak membuat tarif sintetis, karena tarif tidak bisa
dihapus dari riwayat.

## Deploy

| Langkah | Hasil |
| --- | --- |
| Script `infra/scripts/deploy-staging-immutable.sh` dari `0713226` (merge PR #33) → `/root/.rt05/deploy-staging-immutable-m11.sh` mode 700 | SHA-256 diawali `aa50ca1a629ba5a7`, sama dengan lokal; `bash -n` OK |
| Deploy `07132266581c6efa696ad8003ce8f1ae3a21eadb` | `Selesai`, 0 baris GAGAL, tanpa migration baru |
| Pemeriksaan | Container `rt05-staging` memakai image `0713226`; `/health/ready` ready; total container host tetap 22 (service lain tidak disentuh) |

## Cek live

Skrip: [`infra/scripts/m11-stage-check.mjs`](../../../infra/scripts/m11-stage-check.mjs). Aktornya admin sintetis M06 dan
warga sintetis M07, dengan kredensial dari berkas privat di luar Git. Ringkasan:
[m11-live-summary-0713226.json](m11-live-summary-0713226.json).

| Kasus | Hasil |
| --- | --- |
| L01 tarif terdaftar (75.000) | PASS |
| L02 rekap bulan berjalan menurut WIB, jumlah = lunas + belum | PASS (11 rumah: 1 lunas, 10 belum; 2 rumah punya tunggakan sebelumnya) |
| L03 rekap tanpa email/nama | PASS |
| L04 periode tidak valid | PASS (422) |
| L05/L06 warga membuka rekap/tarif | PASS (403) |
| L07 warga melihat iuran sendiri | PASS (200) |

**7/7 PASS.** Angka rekap berasal dari data sintetis M07–M10 di staging, bukan data warga.
