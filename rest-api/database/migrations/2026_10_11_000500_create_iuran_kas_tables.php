<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ERD v1.1 domain 05 — tarif → tagihan → alokasi ← pembayaran → transaksi_kas.
| Penyesuaian v1 (ADR17): rupiah bigint integer (bukan decimal), status tagihan eksplisit,
| pembatalan lewat pembalikan (bukan edit), saldo = SUM(transaksi_kas.nominal) bertanda.
| Pembayaran offline dicatat pengurus; tidak ada payment gateway.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_iuran', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('dibuat_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->bigInteger('nominal');
            $table->date('mulai_periode')->unique();
            $table->text('alasan');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE tarif_iuran ADD CONSTRAINT tarif_nominal_positif CHECK (nominal > 0)');
        DB::statement('ALTER TABLE tarif_iuran ADD CONSTRAINT tarif_awal_bulan CHECK (extract(day from mulai_periode) = 1)');

        Schema::create('tagihan_iuran', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tarif_id')->constrained('tarif_iuran')->restrictOnDelete();
            $table->foreignUuid('rumah_id')->constrained('rumah')->restrictOnDelete();
            $table->date('periode');
            // Snapshot tarif saat tagihan dibuat; perubahan tarif berikutnya tidak mengubahnya.
            $table->bigInteger('nominal_tagihan');
            $table->date('jatuh_tempo');
            $table->string('status', 20)->default('belum_bayar');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['rumah_id', 'periode']);
            $table->index(['periode', 'status']);
        });
        DB::statement('ALTER TABLE tagihan_iuran ADD CONSTRAINT tagihan_nominal_positif CHECK (nominal_tagihan > 0)');
        DB::statement('ALTER TABLE tagihan_iuran ADD CONSTRAINT tagihan_periode_awal_bulan CHECK (extract(day from periode) = 1)');
        DB::statement("ALTER TABLE tagihan_iuran ADD CONSTRAINT tagihan_status_check CHECK (status IN ('belum_bayar', 'lunas'))");

        Schema::create('pembayaran_iuran', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('rumah_id')->constrained('rumah')->restrictOnDelete();
            $table->foreignUuid('diterima_oleh_id')->constrained('akun')->restrictOnDelete();
            $table->string('nomor_bukti', 50)->unique();
            $table->date('tanggal_bayar');
            $table->bigInteger('total_bayar');
            $table->string('status', 20)->default('tercatat');
            $table->text('catatan')->nullable();
            $table->uuid('menggantikan_id')->nullable()->unique();
            $table->foreignUuid('dibatalkan_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->timestampTz('dibatalkan_at')->nullable();
            $table->text('alasan_pembatalan')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE pembayaran_iuran ADD CONSTRAINT pembayaran_menggantikan_fk FOREIGN KEY (menggantikan_id) REFERENCES pembayaran_iuran (id) ON DELETE RESTRICT');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS pembayaran_nomor_seq');
        DB::statement('ALTER TABLE pembayaran_iuran ADD CONSTRAINT pembayaran_total_positif CHECK (total_bayar > 0)');
        DB::statement("ALTER TABLE pembayaran_iuran ADD CONSTRAINT pembayaran_status_check CHECK (status IN ('tercatat', 'dibatalkan'))");
        DB::statement("ALTER TABLE pembayaran_iuran ADD CONSTRAINT pembayaran_pembatalan_lengkap CHECK (status <> 'dibatalkan' OR (dibatalkan_at IS NOT NULL AND dibatalkan_oleh_id IS NOT NULL AND alasan_pembatalan IS NOT NULL))");

        Schema::create('alokasi_pembayaran', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('tagihan_id')->constrained('tagihan_iuran')->restrictOnDelete();
            $table->foreignUuid('pembayaran_id')->constrained('pembayaran_iuran')->restrictOnDelete();
            $table->bigInteger('nominal_alokasi');
            $table->timestampTz('dibatalkan_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE alokasi_pembayaran ADD CONSTRAINT alokasi_nominal_positif CHECK (nominal_alokasi > 0)');
        // Satu tagihan hanya boleh punya satu alokasi aktif (mencegah bayar ganda).
        DB::statement('CREATE UNIQUE INDEX alokasi_satu_aktif_per_tagihan ON alokasi_pembayaran (tagihan_id) WHERE dibatalkan_at IS NULL');

        Schema::create('transaksi_kas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('jenis', 20);
            $table->string('sumber', 20);
            $table->string('kategori', 60)->nullable();
            // Rupiah bertanda: pemasukan/saldo_awal positif, pengeluaran negatif, pembalikan = -asal.
            $table->bigInteger('nominal');
            $table->date('tanggal');
            $table->text('keterangan');
            $table->foreignUuid('pembayaran_id')->nullable()->unique()->constrained('pembayaran_iuran')->restrictOnDelete();
            $table->uuid('membalik_id')->nullable()->unique();
            $table->foreignUuid('dicatat_oleh_id')->constrained('akun')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('tanggal');
        });
        DB::statement('ALTER TABLE transaksi_kas ADD CONSTRAINT kas_membalik_fk FOREIGN KEY (membalik_id) REFERENCES transaksi_kas (id) ON DELETE RESTRICT');
        DB::statement("ALTER TABLE transaksi_kas ADD CONSTRAINT kas_jenis_check CHECK (jenis IN ('pemasukan', 'pengeluaran', 'pembalikan', 'saldo_awal'))");
        DB::statement("ALTER TABLE transaksi_kas ADD CONSTRAINT kas_sumber_check CHECK (sumber IN ('iuran', 'lainnya'))");
        DB::statement(<<<'SQL'
            ALTER TABLE transaksi_kas ADD CONSTRAINT kas_tanda_sesuai_jenis CHECK (
                (jenis IN ('pemasukan', 'saldo_awal') AND nominal > 0)
                OR (jenis = 'pengeluaran' AND nominal < 0)
                OR (jenis = 'pembalikan' AND nominal <> 0 AND membalik_id IS NOT NULL)
            )
        SQL);
        DB::statement("ALTER TABLE transaksi_kas ADD CONSTRAINT kas_iuran_dari_pembayaran CHECK (NOT (jenis = 'pemasukan' AND sumber = 'iuran') OR pembayaran_id IS NOT NULL)");
        DB::statement("CREATE UNIQUE INDEX kas_satu_saldo_awal ON transaksi_kas ((true)) WHERE jenis = 'saldo_awal'");

        Schema::create('riwayat_transaksi_kas', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('transaksi_kas_id')->constrained('transaksi_kas')->restrictOnDelete();
            $table->foreignUuid('diubah_oleh_id')->constrained('akun')->restrictOnDelete();
            $table->string('aksi', 20);
            $table->jsonb('sebelum')->nullable();
            $table->jsonb('sesudah');
            $table->text('alasan');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE riwayat_transaksi_kas ADD CONSTRAINT riwayat_kas_aksi_check CHECK (aksi IN ('dicatat', 'dibalik'))");

        // Ledger kas immutable: koreksi selalu lewat baris pembalikan baru.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_immutable_row() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                RAISE EXCEPTION '% bersifat immutable; gunakan pembalikan', TG_TABLE_NAME USING ERRCODE = 'insufficient_privilege';
            END;
            $fn$;
            CREATE TRIGGER transaksi_kas_immutable BEFORE UPDATE OR DELETE ON transaksi_kas
                FOR EACH ROW EXECUTE FUNCTION rt05_immutable_row();
            CREATE TRIGGER riwayat_transaksi_kas_immutable BEFORE UPDATE OR DELETE ON riwayat_transaksi_kas
                FOR EACH ROW EXECUTE FUNCTION rt05_immutable_row();
        SQL);

        // Tarif awal Rp75.000/rumah/bulan (BR10), berlaku sejak periode go-live pencatatan.
        DB::table('tarif_iuran')->insert([
            'nominal' => 75000,
            'mulai_periode' => '2026-10-01',
            'alasan' => 'Tarif awal RT 05 Taman Kedaung: Rp75.000 per rumah per bulan',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_transaksi_kas');
        Schema::dropIfExists('transaksi_kas');
        Schema::dropIfExists('alokasi_pembayaran');
        Schema::dropIfExists('pembayaran_iuran');
        Schema::dropIfExists('tagihan_iuran');
        Schema::dropIfExists('tarif_iuran');
        DB::statement('DROP SEQUENCE IF EXISTS pembayaran_nomor_seq');
        DB::unprepared('DROP FUNCTION IF EXISTS rt05_immutable_row()');
    }
};
