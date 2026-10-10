# Bukti baseline frontend yang diterima

Pemilik menerima desain pada 10 Oktober 2026, setelah PR #8 (`0658d5d4351eb6d1409baab5835cdc5e137533db`). File berikut merupakan snapshot laporan yang sebelumnya berada di `artifacts/`; tidak berisi kredensial, foto perangkat, atau draf warga.

- `local-qa.json`: 95 kasus responsif, 41 Axe, navigasi, editor, isolasi/reset/pemulihan, serta slideshow otomatis tanpa tombol jeda.
- `live-web.json`: pemeriksaan alias Vercel pada 10 Oktober 2026, 15:28 WIB; 19 HTML/26 aset cocok, HP lulus, tanpa error browser.
- `live-slideshow.json`: uji interval 3 detik, tanpa tombol jeda, autoplay setelah memilih foto, hover/keyboard, reduced motion, viewport, dan fallback tanpa JavaScript pada alias utama.

CI baseline: https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/38037782062

Laporan ini mencatat versi desain, bukan deploy masa depan. Commit paket/handoff juga memuat dokumentasi baru; commit dan hash arsip yang tepat tersedia dalam manifest release. Untuk memverifikasi perubahan berikutnya, jalankan script pemeriksaan lagi dan perhatikan versi/URL yang diperiksa.
