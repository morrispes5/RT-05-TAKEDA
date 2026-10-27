# Diagnosis koneksi staging dari MyRepublic

Pemeriksaan langsung 18 Oktober 2026, sekitar 23.47–23.58 WIB. Lingkup: akses
staging RT05 dan portofolio dari PC pemilik serta inspeksi hosting baca-saja. Ini bukan penerimaan
M14, UAT Android, atau bukti perbaikan permanen ISP.

## Keluhan dan hasil

Pemilik melaporkan aplikasi gagal lewat Wi-Fi rumah MyRepublic dan berhasil
lewat internet kafe. Cloudflare WARP belum terpasang di HP. PC penguji memakai
Ethernet; nama ISP berasal dari konfirmasi pemilik, bukan pengukuran ASN.

| Pemeriksaan langsung | Hasil |
| --- | --- |
| `warp-cli status`, pengaturan, trace Cloudflare | Connected, healthy, Mode Warp, MASQUE, `warp=on` |
| DNS A staging | `187.77.113.136`; sama pada resolver PC dan Google `8.8.8.8` |
| DNS AAAA staging | `2a02:4780:59:7d0b::1` |
| Nameserver `morriz.tech` | `hermes.dns-parking.com`, `artemis.dns-parking.com`; domain mengarah langsung ke origin Hostinger |
| WARP aktif: IPv4 HTTPS staging, termasuk `--resolve` | TCP connect timeout 6–8 detik, HTTP 000 |
| WARP aktif: kontrol IPv4 `https://www.cloudflare.com` | HTTP 200 |
| WARP aktif: IPv6 `/health/ready` | HTTP 200, database ok |
| WARP aktif: IPv6 `/health/live` (endpoint bootstrap mobile) | HTTP 200, `{"status":"ok"}` |
| WARP aktif: curl tanpa memaksa keluarga alamat ke readiness | HTTP 200 |
| WARP dimatikan sementara: kontrol IPv4 Cloudflare | HTTP 200 |
| WARP dimatikan sementara: HTTPS staging dengan IP dipaksa memakai `--resolve` | TCP connect timeout 6 detik, HTTP 000; tidak bergantung resolusi DNS |
| WARP dimatikan sementara: DNS staging | A tetap benar |
| Traceroute native, maksimum 8 hop | Balasan sampai hop 6 (`103.75.97.250`), hop 7–8 timeout; tidak membuktikan lokasi persis paket dibuang |
| SSH IPv6, host key diverifikasi | Berhasil melalui alias existing `rt05-vps-v6` |
| Container RT05 | Sembilan container berjalan; seluruh delapan yang memiliki healthcheck healthy |
| VPS: listener HTTPS | IPv4 `0.0.0.0:443` dan IPv6 `[::]:443` tersedia |
| VPS: UFW / INPUT / DOCKER-USER | UFW inactive, INPUT ACCEPT, DOCKER-USER tanpa aturan tambahan; `fail2ban-client` tidak ditemukan |
| Panel Hostinger, halaman Firewall | Ringkasan 0 aturan; halaman menawarkan pembuatan firewall |
| VPS mengakses HTTPS origin sendiri, TLS tetap diverifikasi | IPv4 HTTP 200; IPv6 readiness ready/database ok |
| Pemulihan kondisi PC | WARP Connected/healthy kembali, trace `warp=on`, IPv6 mobile liveness HTTP 200 |

Permintaan awal ke `/api/v1/health` dan `/api/v1/health/live` menghasilkan 404;
path tersebut bukan endpoint bootstrap mobile. Pembuktian memakai path benar
`/health/live` dari `mobile/lib/core/health.dart`. HTTP lokal port 80 memberi 302
dan tidak digunakan sebagai bukti readiness.

## Kesimpulan terbatas

Gejala konsisten dengan gangguan jangkauan IPv4 pada jalur menuju VPS Hostinger.
Mengganti DNS saja tidak menyelesaikan tes IP langsung yang timeout. WARP di PC
memungkinkan jalur IPv6 yang bekerja; pada pemeriksaan ini IPv4 melalui WARP juga
tetap timeout. Tanpa capture dua sisi dan pemeriksaan operator, belum bisa
menentukan apakah sumber gangguan berada di MyRepublic, transit, atau jaringan
Hostinger. Tes dari VPS sendiri tidak membuktikan seluruh jalur IPv4 publik sehat.

Tidak ditemukan aturan blokir pada permukaan firewall yang diperiksa. Tidak ada
indikasi perlunya reboot VPS atau perubahan kode Flutter untuk hasil tes ini.
WARP hanya melayani perangkat tempat klien aktif; PC bukan router VPN untuk HP.

## Perluasan keluhan: portofolio PC dan pembuktian jaringan luar

Pemilik menegaskan masalah juga terjadi saat PC membuka portofolio, bukan hanya
aplikasi HP. Domain `morriztech.cloud` diverifikasi langsung: A menunjuk VPS yang
sama (`187.77.113.136`), tanpa AAAA, nameserver Rumahweb. Dari PC dengan WARP
MASQUE, akses IPv4 portofolio timeout. Memaksa **hanya alamat tujuan** lewat
`curl -6 --resolve morriztech.cloud:443:[2a02:4780:59:7d0b::1] https://morriztech.cloud/`
mendapat HTTP 200 dengan validasi TLS tetap aktif. Dari VPS sendiri, portofolio
melalui IPv4 maupun IPv6 mendapat HTTP 200. Tidak ada override hosts yang disimpan.

Uji protokol WARP WireGuard tidak mempertahankan koneksi; portofolio, staging,
dan kontrol internet semuanya gagal. Override percobaan dihapus dengan
`warp-cli tunnel protocol reset`. Kondisi akhir: Mode Warp, MASQUE dari network
policy, Connected/healthy. Setelah pemulihan, portofolio IPv4 masih timeout,
RT05 IPv6 HTTP 200. Rute eksklusi WARP tidak mencakup IP VPS. Percobaan curl
`--interface Ethernet` gagal bind dan **bukan** bukti tambahan jalur native.

**Pembanding eksternal baru**, diperiksa pada 18 Oktober sekitar 23.58 WIB:
API Check-Host HTTP untuk masing-masing URL dari tiga node, alamat tujuan
tercantum `187.77.113.136` (IPv4) pada seluruh hasil. Ini enam pemeriksaan,
bukan hasil historis M13.

| URL | Jakarta (id1) | Singapura (sg1) | Nuremberg (de1) | Laporan publik |
| --- | --- | --- | --- | --- |
| `https://morriztech.cloud/` | 200, 0,138 dtk | 200, 0,723 dtk | 200, 2,735 dtk | [51d3a7c5kd78](https://check-host.net/check-report/51d3a7c5kd78) |
| `https://takeda-staging.morriz.tech/health/live` | 200, 0,293 dtk | 200, 0,363 dtk | 200, 0,791 dtk | [51d3a7cak650](https://check-host.net/check-report/51d3a7cak650) |

Hasil memperkuat diagnosis **gangguan keterjangkauan IPv4 pada jalur dari
koneksi rumah menuju Hostinger**; server IPv4 dapat diakses oleh jaringan luar
tersebut. Belum membuktikan gangguan spesifik pada satu operator atau seluruh
pelanggan MyRepublic. IPv4 WARP yang ikut gagal juga berarti penyebab tunggal
di MyRepublic belum dapat dipastikan. Pemilik meminta diagnosis provider
diutamakan, Cloudflare sebagai cadangan; akun Cloudflare tersedia menurut
pemilik, tetapi tidak diperiksa atau diubah pada sesi ini.

## Tindak lanjut yang bisa diuji

1. HP: pasang aplikasi resmi [1.1.1.1 + WARP](https://play.google.com/store/apps/details?id=com.cloudflare.onedotonedotonedotone),
   aktifkan mode **WARP**, lalu uji Wi-Fi MyRepublic → browser
   `https://takeda-staging.morriz.tech/health/live` → aplikasi RT05 → Coba lagi.
   DNS-only berbeda dari mode WARP. Hasil HP **belum diuji**; tidak menjanjikan
   WARP pasti berhasil pada perangkat tersebut.
2. Pemulihan jalur tanpa VPN: sampaikan bukti ke dukungan MyRepublic. Draf pesan:
   "Dari koneksi rumah MyRepublic, HTTPS ke 187.77.113.136:443 untuk
   morriztech.cloud dan takeda-staging.morriz.tech mengalami TCP connect timeout,
   termasuk saat DNS dilewati. Internet umum normal. Pada 18 Oktober 2026
   sekitar 23.58 WIB, pemeriksaan HTTP IPv4 dari Jakarta, Singapura, dan Jerman
   mendapat 200 untuk kedua layanan. Traceroute dari rumah menjawab sampai
   103.75.97.250, kemudian tidak ada balasan. Mohon periksa routing/peering dan
   jalur balik ke IP VPS Hostinger tersebut. Bukti portofolio:
   https://check-host.net/check-report/51d3a7c5kd78; bukti RT05:
   https://check-host.net/check-report/51d3a7cak650."
   Pesan **belum dikirim**; traceroute bukan bukti operator tertentu bersalah.
3. Kandidat mitigasi layanan: proxy HTTPS Cloudflare di depan staging agar klien
   menghubungi edge Cloudflare. Saat ini DNS masih di Hostinger; bukan sekadar
   menyalakan WARP pada VPS. Sebelum cutover, inventaris semua record domain dan
   DNSSEC, tentukan akses akun serta cakupan perubahan domain, siapkan rollback,
   TLS Full (strict), dan bypass cache untuk API/auth/health. Jangan memasang
   challenge interaktif pada jalur mobile. Validasi login, bearer, CSRF/cookie CMS,
   unggah media, rate limit/IP klien, dan dua jaringan sesudahnya. Efektivitas
   terhadap koneksi rumah **belum dibuktikan**. Perubahan nameserver seluruh
   `morriz.tech` bisa memengaruhi layanan lain dan tidak dilakukan pada diagnosis ini.

Referensi resmi: [mode WARP](https://developers.cloudflare.com/warp-client/warp-modes/),
[pengaturan Android](https://developers.cloudflare.com/1.1.1.1/setup/android/),
[proxy DNS Cloudflare](https://developers.cloudflare.com/dns/proxy-status/).

## Perubahan dan batas

WARP PC diputus sementara hanya untuk tes, lalu dihubungkan dan diverifikasi
kembali. Hosting diperiksa baca-saja. Tidak ada perubahan DNS, firewall, proxy,
container, env, database, APK, atau kode aplikasi. HP tidak tersambung melalui ADB.
Hasil dan langkah berikut dicatat di PROGRESS. Branch diagnosis
`codex/m14-isp-diagnosis` berasal dari `6728a07`; tidak commit atau push.

Validasi dokumentasi: `node infra/scripts/check-repo.mjs` OK (895 berkas);
`git diff --check` bersih. Tidak menjalankan ulang tes bisnis untuk diagnosis
dan perubahan dokumentasi ini.

Catatan 27 Okt 2026: laporan ini dimasukkan ke `main` apa adanya lewat branch `codex/m14-catatan-myrepublic`
sebagai riwayat; isinya tidak diubah sejak 18 Oktober.
