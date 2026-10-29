# Rencana M19 — Tombol panik

Tanggal: **27 Oktober 2026**. Status: **staging terbatas; menunggu penerimaan HP nyata**.
Pemilik meminta eksekusi dan rilis uji sesudah rencana dicatat. Hasil lokal:
[LOCAL_IMPLEMENTATION.md](evidence/m19/LOCAL_IMPLEMENTATION.md); checkpoint rilis dan gate tersisa:
[RELEASE_1023.md](evidence/m19/RELEASE_1023.md).
Pemilik memprioritaskan M19 sesudah M18, sebelum M16 (ADR45). Ketentuan fitur berasal dari
**ADR43 butir 8** dan **ROADMAP M19** yang sudah ditulis pada sesi Claude. Dokumen ini merinci
pekerjaan dan bukti yang harus dikumpulkan; tidak menganggap rancangan teknis sebagai hasil uji.

## 1. Ketentuan yang sudah diputuskan

| Bagian | Perilaku |
| --- | --- |
| Akses Android | Tombol panik di aplikasi dan shortcut Android |
| Aktivasi | Tiga langkah (ADR48): tekan FAB → scroll ketentuan → geser untuk menyalakan / Batal |
| Jenis | Opsional setelah alarm: kebakaran, kemalingan, penyerangan, darurat serius lain |
| Penerima | Semua warga dan pengurus; pengujian hanya memakai penerima sintetis |
| Identitas | Tidak anonim: nama pelapor, rumah, jenis darurat |
| Pemberitahuan | Notifikasi bergaya alarm Android, mengikuti izin pengguna; kemampuan nyata dibuktikan di HP |
| Penanganan | Pengurus menandai selesai/palsu, dengan audit |
| Penyalahgunaan | Peringatan; setelah 3 penyalahgunaan tombol panik akun dinonaktifkan |
| Kendali RT | Pengurus dapat mematikan fitur |
| Peringatan | Bukan pengganti 112/damkar/polisi |

Aturan hak bisnis pengurus setara tetap berlaku. M19 tidak menambahkan lokasi GPS, panggilan otomatis,
WhatsApp massal, kontak baru, integrasi instansi, atau layanan berbayar. M20 tetap menangani iPhone/PWA.

## 2. Alur yang akan diperagakan

1. Warga membuka tombol/shortcut. Server dan aplikasi memeriksa sesi, persetujuan privasi, status akun,
   serta apakah fitur dan hak tombol panik masih aktif. Shortcut tidak melewati ketiga langkah aktivasi.
2. Warga membaca ketentuan, memilih salah satu jenis, lalu melihat ringkasan identitas/rumah dan pilihan
   Ya/Batal. Menekan Batal atau kembali sebelum konfirmasi tidak membuat laporan atau mengirim alarm.
3. Setelah Ya, aplikasi mengirim satu permintaan dengan identitas permintaan tetap untuk retry.
   Respons membedakan laporan tersimpan, pengiriman pemberitahuan masih diproses, dan kegagalan yang diketahui.
4. Penerima membuka notifikasi menuju detail kejadian setelah pemeriksaan sesi/privasi. Tidak ada tautan publik
   berisi identitas warga; data pribadi hanya dibuka kepada penerima yang berhak melalui API.
5. Pengurus membuka daftar/detail dan mencatat penyelesaian atau laporan palsu. Riwayat asal tetap tersimpan.
   Penanganan bersamaan/retry tidak boleh menggandakan hitungan penyalahgunaan.
6. Pada penyalahgunaan ketiga, server menolak aktivasi berikutnya untuk akun itu. Jika pengurus mematikan
   fitur, permintaan baru ditolak server juga, termasuk dari APK lama atau shortcut.

## 3. Rancangan teknis yang harus diperiksa sebelum kode

Gunakan pola yang sudah ada di `rest-api/` dan `mobile/`, terutama autentikasi, persetujuan privasi,
audit, inbox, outbox, FCM, retry dan pemeriksaan pergantian sesi dari perbaikan QA M18.
Inspeksi implementasi tersebut dahulu; nama tabel, endpoint, payload, dan izin Android belum ditetapkan di dokumen ini.

- Simpan kejadian, audit dan pekerjaan pemberitahuan secara atomik. Kegagalan FCM tidak menghapus laporan yang
  sudah tersimpan. Jangan menyatakan semua penerima menerima alarm hanya karena API/FCM menerima permintaan.
- Jaga idempotensi pada kirim, retry worker, penyelesaian dan pencatatan penyalahgunaan. Uji dua proses/request
  bersamaan, bukan hanya pemanggilan berurutan. Kunci dan constraint harus menjaga transisi status/hitungan.
- Rancang batas laju akun dan agregat untuk menghindari banjir pemberitahuan; angka dan perilakunya ditentukan
  setelah inspeksi serta pengujian. Identitas/rumah diambil dari data server, tidak dipercaya dari input pemohon.
- Pengguna nonaktif, belum disetujui, belum setuju privasi, tanpa sesi, atau dengan hak panik dinonaktifkan
  tidak boleh mengirim lewat API langsung. Perubahan akun selama operasi asinkron tidak boleh membuka detail akun lama.
- Tentukan payload minimum, isi layar kunci, akses detail dan retensi tanpa mencatat identitas di log/URL publik.
  Periksa apakah teks privasi yang ada mencakup penyebaran identitas darurat ke semua warga; jika belum, perbarui
  teks dan persetujuan sebelum fitur aktif.
- Fitur uji harus dapat dibatasi ke lingkungan/penerima sintetis. Jangan mengirim alarm percobaan ke warga asli.
  Peluncuran awal nonaktif sampai gate yang relevan lulus dan pengurus siap mengoperasikannya.

## 4. Rincian yang belum diputuskan

Ketentuan bisnis pada bagian 1 tidak perlu ditanyakan ulang. Usulan berikut belum menjadi keputusan baru:

| Rincian | Usulan awal / pekerjaan sebelum aktivasi |
| --- | --- |
| Laporan palsu dan penyalahgunaan | Jangan otomatis menyamakan keduanya. Catat penilaian penyalahgunaan dan alasan pengurus secara eksplisit agar salah ketuk tidak dihitung diam-diam |
| Pemulihan akun setelah 3 penyalahgunaan | Belum ada keputusan; jangan membuat pemulihan otomatis atau mereset hitungan diam-diam |
| Nomor batas laju dan durasi bunyi | Kandidat lokal: 3 laporan akun/jam, 10 laporan RT/menit, satu laporan aktif per akun; alarm mengikuti bunyi kanal Android, tanpa loop atau bypass pengaturan HP. Evaluasi saat uji nyata |
| Notifikasi layar kunci | Periksa pengaturan privasi dan izin perangkat; identitas lengkap tetap tersedia di detail yang terautentikasi |
| Menonaktifkan fitur saat pengiriman berjalan | Definisikan nasib pekerjaan yang belum dikirim dan riwayat yang sudah tersimpan, lalu kunci dengan tes |
| Retensi/audit | Selaraskan dengan kebijakan data yang berlaku; riwayat penanganan tidak dihapus untuk menyembunyikan penyalahgunaan |

Rincian tersebut dicatat sebagai pekerjaan perencanaan, bukan alasan membuka ulang keputusan penerima, tiga
langkah aktivasi, atau prioritas M19. Jangan menyatakan fitur siap digunakan sebelum perilakunya ditetapkan dan diuji.

## 5. Matriks pengujian wajib

| Lapisan | Bukti yang diperlukan |
| --- | --- |
| Domain/API | Batal tidak mengirim; jenis/status tidak valid ditolak; identitas server; akses warga/pengurus/sesi nonaktif/privasi; fitur off; akun diblokir |
| Bersamaan/retry | Klik ganda/retry hanya satu kejadian; fanout tidak berganda; dua pengurus tidak menggandakan hitungan; ambang ketiga atomik; pembatasan laju |
| Gangguan | FCM/queue gagal setelah commit, retry dan pemulihan; notifikasi terlambat; fitur dimatikan saat job menunggu; tidak ada klaim pengiriman palsu |
| Mobile | Tiga langkah, Ya/Batal/Kembali, shortcut, layar kecil/skala teks, jaringan lambat/putus, sesi berganti dan persetujuan privasi |
| Android HP nyata | Izin diterima/ditolak, layar terkunci, aplikasi di latar/depan/ditutup, force-stop diuji terpisah, pengaturan bunyi/perangkat; hasil dan keterbatasan dicatat |
| Regresi | Auth/consent/inbox/push lama tetap berfungsi; keuangan M18 tetap konsisten, rekonsiliasi 0; kontrak API, ERD dan dokumentasi sesuai |
| Operasional | Pengurus menyelesaikan/palsu/penyalahgunaan, mematikan fitur; riwayat/audit terbaca; penerima sintetis tidak bocor ke warga asli |

Tes lokal/emulator tidak menggantikan bukti alarm di HP nyata. Angka 413 backend/326 Flutter/40 website pada
rilis 1021 merupakan baseline M18, **bukan hasil pengujian M19**.

## 6. Tahap pengerjaan dan penutupan

1. Inspeksi kode yang ada dan lengkapi rancangan kontrak, status, pembatasan laju, privasi, izin dan uji perangkat.
2. Kerjakan DB/domain/audit/idempotensi beserta tes; kemudian API, OpenAPI dan ERD.
3. Tambahkan tombol/shortcut, aktivasi, detail, pengelolaan pengurus dan pemberitahuan Android; uji alur lengkap.
4. Jalankan regresi lokal dan uji staging sintetis. Kumpulkan perubahan dalam satu kandidat APK M19 setelah QA.
5. Uji HP nyata dan kesiapan pengurus. Catat bukti, keterbatasan dan kegagalan; status M19 hanya passed jika
   gate terpenuhi. Rilis APK mengikuti runbook distribusi yang ada, tanpa mengirim percobaan ke warga asli.

Urutan aktif: **M17 → M18 → M19 → M16 → M20** (ADR45). Jadwal pindah VPS staging 2 Nov dan target beta 8 Nov
dari ADR43 tetap catatan rencana, tidak otomatis menjadi bukti kesiapan atau izin production. Gate M16 tetap berlaku.

Sesi perencanaan ini bekerja dari dokumen lokal, tanpa fetch/pull/push atau akses remote `origin` sesuai instruksi pemilik.

## 7. Default teknis kandidat lokal (27 Oktober)

Ini pilihan implementasi terbatas untuk diuji, bukan tambahan keputusan bisnis pemilik:

- `kejadian_darurat` menyimpan referensi akun/rumah, jenis, status, versi, penanganan dan penilaian penyalahgunaan.
  Nama/alamat dibaca dari data master, bukan snapshot baru yang lolos anonimisasi. Penanganan terminal sekali;
  salah penilaian belum punya jalur edit/reset otomatis. Alasan wajib dan hanya terlihat pengurus.
- Semua warga/pengurus aktif penerima yang setuju privasi 27 Oktober masuk fanout. Staging memakai audience
  restricted + allowlist UUID akun sintetis di env privat; allowlist kosong tidak bisa diaktifkan. Audience all
  memerlukan konfigurasi operator secara eksplisit. Akun baru tidak menerima replay kejadian sebelum akun dibuat.
- Detail warga: kejadian komunitas 24 jam terakhir sejak akun dibuat + riwayat sendiri; pengurus seluruh riwayat.
  Tombol akun dinonaktifkan setelah 3 penilaian eksplisit, tetapi layanan lain/penerimaan tetap tersedia.
  Penilaian menghasilkan peringatan inbox/push sistem, tanpa membunyikan alarm darurat baru.
- Idempotency-Key melindungi kirim/penanganan/pengaturan. Cache kirim/penanganan hanya ID; respons replay
  memuat detail terkini sesuai policy. Trigger mempertahankan laporan asal dan hasil terminal, termasuk saat SQL
  salah mencoba mengubah/menghapusnya. Migration down menolak penghapusan jika riwayat sudah ada.
- Event, inbox dan provider adalah status berbeda. Detail menampilkan antrean, diproses, gagal/sebagian gagal,
  dilewati atau provider menerima. Provider menerima **bukan** bukti HP berbunyi. Fitur mati, laporan terminal,
  atau usia lewat 10 menit menahan alarm yang belum dikirim. TTL provider memakai sisa jendela 10 menit;
  pengiriman yang sudah diterima jaringan/provider tidak bisa ditarik kembali.
- Teks privasi 27 Oktober khusus M19 menjelaskan penyebaran identitas darurat. API/worker/website harus memakai
  flag yang sama. Teks 25/26 Oktober tetap tersedia; APK lama tidak dapat menyetujui versi baru tanpa teksnya.
- Shortcut membuka langkah awal setelah sesi/privasi sah; tidak pernah mengirim sendiri. Kanal Android terpisah,
  teks generik di layar kunci, ref UUID, bunyi sistem alarm dan getar, tanpa izin full-screen/override DND/lokasi.
  Force-stop, izin, latar/terkunci dan suara tetap menunggu bukti HP nyata.
- Penegasan Home 27 Okt: akses cepat ada di Beranda warga **dan pengurus**. Selain tekan lama ikon Android,
  ada tombol meminta launcher menambahkan shortcut ke layar utama HP. Pengguna mengonfirmasi dialog Android;
  permintaan diterima bukan bukti shortcut sudah dipasang. Tetap membuka langkah awal, bukan langsung mengirim.

Rujukan teknis primer: [kanal notifikasi Android](https://developer.android.com/develop/ui/compose/notifications/channels),
[shortcut Android](https://developer.android.com/develop/ui/compose/system/shortcuts/creating-shortcuts), dan
[payload FCM HTTP v1](https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#AndroidNotification).
Ketentuan produk tetap mengikuti ADR43, bukan diturunkan dari dokumentasi platform.
