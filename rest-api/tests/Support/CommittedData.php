<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tes multi-proses tidak bisa memakai DatabaseTransactions: proses lain hanya melihat data yang
 * sudah commit. Trait ini mencatat waktu mulai lalu menghapus baris identitas yang dibuat sejak
 * itu (urutan aman FK), sehingga tes lain tetap melihat database bersih hasil migration.
 * Seed migration (kategori, tarif) tidak disentuh.
 */
trait CommittedData
{
    private string $committedSince;

    /** Tabel anak lebih dulu agar FK RESTRICT tidak menolak penghapusan. */
    private const COMMITTED_TABLES = [
        'perangkat_push', 'personal_access_tokens', 'password_reset_tokens', 'notifikasi', 'preferensi_notifikasi', 'pengumuman',
        'permohonan_akun', 'audit_logs', 'outbox_events', 'token_registrasi', 'tautan_akun', 'keanggotaan_keluarga', 'riwayat_transaksi_kas', 'transaksi_kas',
        'konfirmasi_iuran', 'mutasi_titipan', 'keringanan_iuran', 'penyesuaian_tagihan', 'alokasi_pembayaran', 'penggunaan_titipan', 'pembayaran_iuran', 'idempotency_requests',
        'tagihan_iuran', 'tarif_rumah', 'akun',
        'penghunian', 'warga', 'keluarga', 'rumah',
    ];

    protected function markCommittedData(): void
    {
        $this->committedSince = DB::selectOne("SELECT (clock_timestamp() - interval '1 second')::text AS t")->t;
    }

    /**
     * Memakai koneksi pemilik schema (*_test, dijaga tests/bootstrap.php) karena audit_logs
     * append-only. Trigger dimatikan hanya di dalam transaksi pembersihan ini dan dinyalakan
     * lagi sebelum commit, jadi sesi lain tidak pernah melihat audit tanpa proteksi.
     */
    protected function purgeCommittedData(): void
    {
        DB::connection('pgsql_migrations')->transaction(function ($db) {
            foreach (['tarif_rumah', 'keringanan_iuran', 'penggunaan_titipan', 'mutasi_titipan', 'penyesuaian_tagihan'] as $table) {
                $db->statement("ALTER TABLE $table DISABLE TRIGGER {$table}_m18_immutable");
            }
            $db->statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_no_update_delete');
            // Buku kas append-only (trigger) hanya dibuka di dalam transaksi pembersihan database *_test.
            $db->statement('ALTER TABLE transaksi_kas DISABLE TRIGGER transaksi_kas_immutable');
            $db->statement('ALTER TABLE riwayat_transaksi_kas DISABLE TRIGGER riwayat_transaksi_kas_immutable');
            // Audit M16: tagihan/pembayaran/alokasi juga dikunci; dibuka hanya di transaksi pembersihan ini.
            $db->statement('ALTER TABLE alokasi_pembayaran DISABLE TRIGGER alokasi_pembayaran_terkunci');
            $db->statement('ALTER TABLE pembayaran_iuran DISABLE TRIGGER pembayaran_iuran_terkunci');
            foreach (self::COMMITTED_TABLES as $table) {
                $db->table($table)->where('created_at', '>=', $this->committedSince)->delete();
            }
            // Drain deferred ledger/FK checks before ALTER TABLE restores immutable triggers.
            $db->statement('SET CONSTRAINTS ALL IMMEDIATE');
            $db->statement('ALTER TABLE pembayaran_iuran ENABLE TRIGGER pembayaran_iuran_terkunci');
            $db->statement('ALTER TABLE alokasi_pembayaran ENABLE TRIGGER alokasi_pembayaran_terkunci');
            $db->statement('ALTER TABLE riwayat_transaksi_kas ENABLE TRIGGER riwayat_transaksi_kas_immutable');
            $db->statement('ALTER TABLE transaksi_kas ENABLE TRIGGER transaksi_kas_immutable');
            $db->statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_no_update_delete');
            foreach (['tarif_rumah', 'keringanan_iuran', 'penggunaan_titipan', 'mutasi_titipan', 'penyesuaian_tagihan'] as $table) {
                $db->statement("ALTER TABLE $table ENABLE TRIGGER {$table}_m18_immutable");
            }
        });
        DB::connection('pgsql_migrations')->disconnect();
    }
}
