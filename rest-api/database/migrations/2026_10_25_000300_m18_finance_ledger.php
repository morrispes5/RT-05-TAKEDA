<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE tarif_iuran ADD COLUMN dibatalkan_at timestamptz,
                ADD COLUMN dibatalkan_oleh_id uuid REFERENCES akun(id), ADD COLUMN alasan_pembatalan text,
                ADD COLUMN menggantikan_id uuid UNIQUE REFERENCES tarif_iuran(id);
            ALTER TABLE tarif_iuran DROP CONSTRAINT tarif_iuran_mulai_periode_unique;
            CREATE UNIQUE INDEX tarif_umum_aktif ON tarif_iuran(mulai_periode) WHERE dibatalkan_at IS NULL;
            CREATE TABLE tarif_rumah (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(), rumah_id uuid NOT NULL REFERENCES rumah(id),
                mode varchar(10) NOT NULL CHECK(mode IN ('khusus','umum')), nominal bigint,
                mulai_periode date NOT NULL CHECK(extract(day FROM mulai_periode)=1),
                alasan text NOT NULL CHECK(length(trim(alasan)) BETWEEN 5 AND 300),
                dibuat_oleh_id uuid NOT NULL REFERENCES akun(id), created_at timestamptz NOT NULL DEFAULT now(),
                dibatalkan_at timestamptz, dibatalkan_oleh_id uuid REFERENCES akun(id), alasan_pembatalan text,
                menggantikan_id uuid UNIQUE REFERENCES tarif_rumah(id),
                CHECK((mode='khusus' AND nominal IS NOT NULL AND nominal BETWEEN 1000 AND 10000000) OR (mode='umum' AND nominal IS NULL))
            );
            CREATE UNIQUE INDEX tarif_rumah_aktif ON tarif_rumah(rumah_id,mulai_periode) WHERE dibatalkan_at IS NULL;
            ALTER TABLE tagihan_iuran ADD COLUMN tarif_rumah_id uuid REFERENCES tarif_rumah(id);
            ALTER TABLE pembayaran_iuran ADD COLUMN nominal_sumbangan bigint NOT NULL DEFAULT 0 CHECK(nominal_sumbangan>=0),
                ADD COLUMN nominal_titipan bigint NOT NULL DEFAULT 0 CHECK(nominal_titipan>=0),
                ADD COLUMN versi_keuangan smallint NOT NULL DEFAULT 1 CHECK(versi_keuangan IN (1,2)),
                ADD CONSTRAINT pembayaran_komponen CHECK(nominal_sumbangan + nominal_titipan <= total_bayar);
            CREATE TABLE penggunaan_titipan (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(), rumah_id uuid NOT NULL REFERENCES rumah(id),
                nominal bigint NOT NULL CHECK(nominal>0), tanggal date NOT NULL,
                dicatat_oleh_id uuid NOT NULL REFERENCES akun(id), created_at timestamptz NOT NULL DEFAULT now(),
                dibatalkan_at timestamptz, dibatalkan_oleh_id uuid REFERENCES akun(id), alasan_pembatalan text,
                menggantikan_id uuid UNIQUE REFERENCES penggunaan_titipan(id)
            );
            ALTER TABLE alokasi_pembayaran ALTER COLUMN pembayaran_id DROP NOT NULL;
            ALTER TABLE alokasi_pembayaran ADD COLUMN penggunaan_titipan_id uuid REFERENCES penggunaan_titipan(id),
                ADD CONSTRAINT alokasi_sumber_tunggal CHECK((pembayaran_id IS NULL) <> (penggunaan_titipan_id IS NULL));
            CREATE UNIQUE INDEX alokasi_per_pembayaran ON alokasi_pembayaran(tagihan_id,pembayaran_id) WHERE dibatalkan_at IS NULL;
            CREATE UNIQUE INDEX alokasi_per_titipan ON alokasi_pembayaran(tagihan_id,penggunaan_titipan_id) WHERE dibatalkan_at IS NULL;
            DROP INDEX alokasi_satu_aktif_per_tagihan;
            CREATE TABLE keringanan_iuran (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(), tagihan_id uuid NOT NULL REFERENCES tagihan_iuran(id),
                pembayaran_id uuid REFERENCES pembayaran_iuran(id), nominal bigint NOT NULL CHECK(nominal>0),
                alasan text NOT NULL CHECK(length(trim(alasan)) BETWEEN 5 AND 300),
                dicatat_oleh_id uuid NOT NULL REFERENCES akun(id), created_at timestamptz NOT NULL DEFAULT now(),
                dibatalkan_at timestamptz, dibatalkan_oleh_id uuid REFERENCES akun(id), alasan_pembatalan text,
                menggantikan_id uuid UNIQUE REFERENCES keringanan_iuran(id)
            );
            CREATE INDEX keringanan_tagihan ON keringanan_iuran(tagihan_id);
            CREATE TABLE penyesuaian_tagihan (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(), tagihan_id uuid NOT NULL REFERENCES tagihan_iuran(id),
                delta bigint NOT NULL CHECK(delta<>0), alasan text NOT NULL CHECK(length(trim(alasan)) BETWEEN 5 AND 300),
                dicatat_oleh_id uuid NOT NULL REFERENCES akun(id), created_at timestamptz NOT NULL DEFAULT now(),
                membalik_id uuid UNIQUE REFERENCES penyesuaian_tagihan(id)
            );
            CREATE INDEX penyesuaian_tagihan_ref ON penyesuaian_tagihan(tagihan_id);
            CREATE TABLE mutasi_titipan (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(), rumah_id uuid NOT NULL REFERENCES rumah(id),
                pembayaran_id uuid NOT NULL REFERENCES pembayaran_iuran(id),
                lot_id uuid NOT NULL REFERENCES mutasi_titipan(id) DEFERRABLE INITIALLY DEFERRED,
                penggunaan_titipan_id uuid REFERENCES penggunaan_titipan(id),
                jenis varchar(12) NOT NULL CHECK(jenis IN ('masuk','pakai','pembalikan')),
                nominal bigint NOT NULL CHECK(nominal>0), arah smallint NOT NULL CHECK(arah IN (-1,1)),
                membalik_id uuid UNIQUE REFERENCES mutasi_titipan(id),
                dicatat_oleh_id uuid NOT NULL REFERENCES akun(id), created_at timestamptz NOT NULL DEFAULT now(),
                CHECK((jenis='masuk' AND arah=1 AND lot_id=id AND penggunaan_titipan_id IS NULL AND membalik_id IS NULL)
                   OR (jenis='pakai' AND arah=-1 AND penggunaan_titipan_id IS NOT NULL AND membalik_id IS NULL)
                   OR (jenis='pembalikan' AND membalik_id IS NOT NULL))
            );
            CREATE UNIQUE INDEX titipan_satu_kredit ON mutasi_titipan(pembayaran_id) WHERE jenis='masuk';
            CREATE INDEX titipan_lot ON mutasi_titipan(lot_id);
            ALTER TABLE konfirmasi_iuran ADD COLUMN pembayaran_id uuid UNIQUE REFERENCES pembayaran_iuran(id);

            CREATE OR REPLACE FUNCTION rt05_keuangan_lock() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('rt05:keuangan'));
                RETURN NULL;
            END $fn$;
            CREATE OR REPLACE FUNCTION rt05_batal_saja() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Ledger tidak boleh dihapus' USING ERRCODE='42501'; END IF;
                IF (to_jsonb(NEW)-ARRAY['dibatalkan_at','dibatalkan_oleh_id','alasan_pembatalan'])
                    IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['dibatalkan_at','dibatalkan_oleh_id','alasan_pembatalan'])
                    OR OLD.dibatalkan_at IS NOT NULL OR NEW.dibatalkan_at IS NULL
                    OR NEW.dibatalkan_oleh_id IS NULL OR length(trim(COALESCE(NEW.alasan_pembatalan,'')))<5 THEN
                    RAISE EXCEPTION 'Ledger hanya boleh dibatalkan sekali dengan alasan' USING ERRCODE='42501';
                END IF;
                RETURN NEW;
            END $fn$;
            CREATE OR REPLACE FUNCTION rt05_tagihan_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF (to_jsonb(NEW)-'status') IS DISTINCT FROM (to_jsonb(OLD)-'status') THEN
                    RAISE EXCEPTION 'Snapshot tagihan terkunci' USING ERRCODE='42501';
                END IF;
                RETURN NEW;
            END $fn$;
            CREATE OR REPLACE FUNCTION rt05_pembayaran_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Pembayaran tidak boleh dihapus' USING ERRCODE='42501'; END IF;
                IF (to_jsonb(NEW)-ARRAY['status','dibatalkan_at','dibatalkan_oleh_id','alasan_pembatalan'])
                    IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['status','dibatalkan_at','dibatalkan_oleh_id','alasan_pembatalan'])
                    OR NOT (OLD.status='tercatat' AND NEW.status='dibatalkan' AND OLD.dibatalkan_at IS NULL
                        AND NEW.dibatalkan_at IS NOT NULL AND NEW.dibatalkan_oleh_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'Pembayaran hanya boleh dibatalkan sekali' USING ERRCODE='42501';
                END IF;
                RETURN NEW;
            END $fn$;
            CREATE OR REPLACE FUNCTION rt05_alokasi_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF TG_OP='DELETE' THEN RAISE EXCEPTION 'Alokasi tidak boleh dihapus' USING ERRCODE='42501'; END IF;
                IF (to_jsonb(NEW)-'dibatalkan_at') IS DISTINCT FROM (to_jsonb(OLD)-'dibatalkan_at')
                    OR OLD.dibatalkan_at IS NOT NULL OR NEW.dibatalkan_at IS NULL THEN
                    RAISE EXCEPTION 'Alokasi hanya boleh dibatalkan sekali' USING ERRCODE='42501';
                END IF;
                RETURN NEW;
            END $fn$;

            CREATE OR REPLACE VIEW rt05_tagihan_saldo AS
            SELECT t.*, t.nominal_tagihan + COALESCE(j.delta,0) AS nominal_efektif,
                COALESCE(a.uang,0) AS terbayar_uang, COALESCE(a.titipan,0) AS terbayar_titipan,
                COALESCE(k.nominal,0) AS keringanan,
                t.nominal_tagihan + COALESCE(j.delta,0)-COALESCE(a.uang,0)-COALESCE(a.titipan,0)-COALESCE(k.nominal,0) AS sisa
            FROM tagihan_iuran t
            LEFT JOIN (SELECT tagihan_id, sum(nominal_alokasi) FILTER(WHERE pembayaran_id IS NOT NULL) AS uang,
                sum(nominal_alokasi) FILTER(WHERE penggunaan_titipan_id IS NOT NULL) AS titipan
                FROM alokasi_pembayaran WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) a ON a.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(nominal) AS nominal FROM keringanan_iuran WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) k ON k.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(delta) AS delta FROM penyesuaian_tagihan GROUP BY tagihan_id) j ON j.tagihan_id=t.id;

            CREATE OR REPLACE VIEW rt05_titipan_saldo AS
            SELECT l.id, l.rumah_id, l.pembayaran_id, l.created_at, sum(m.arah*m.nominal) AS saldo
            FROM mutasi_titipan l JOIN mutasi_titipan m ON m.lot_id=l.id WHERE l.jenis='masuk'
            GROUP BY l.id,l.rumah_id,l.pembayaran_id,l.created_at;

            CREATE OR REPLACE VIEW rt05_finance_violations AS
            SELECT 'tagihan_sisa_negatif'::text AS jenis, id FROM rt05_tagihan_saldo WHERE sisa<0 OR nominal_efektif<0
            UNION ALL SELECT 'tagihan_status',id FROM rt05_tagihan_saldo WHERE (sisa=0) <> (status='lunas')
            UNION ALL SELECT 'alokasi_sumber',a.id FROM alokasi_pembayaran a JOIN tagihan_iuran t ON t.id=a.tagihan_id
                LEFT JOIN pembayaran_iuran p ON p.id=a.pembayaran_id LEFT JOIN penggunaan_titipan u ON u.id=a.penggunaan_titipan_id
                WHERE t.rumah_id<>COALESCE(p.rumah_id,u.rumah_id) OR (a.dibatalkan_at IS NULL AND (p.status='dibatalkan' OR u.dibatalkan_at IS NOT NULL))
            UNION ALL SELECT 'pembayaran_komponen',p.id FROM pembayaran_iuran p WHERE p.status='tercatat' AND
                p.total_bayar<>p.nominal_sumbangan+p.nominal_titipan+COALESCE((SELECT sum(nominal_alokasi) FROM alokasi_pembayaran WHERE pembayaran_id=p.id AND dibatalkan_at IS NULL),0)
            UNION ALL SELECT 'pembayaran_kas',p.id FROM pembayaran_iuran p WHERE NOT EXISTS
                (SELECT 1 FROM transaksi_kas k WHERE k.pembayaran_id=p.id AND k.jenis='pemasukan' AND k.sumber='iuran' AND k.nominal=p.total_bayar)
            UNION ALL SELECT 'pembayaran_pembalikan',p.id FROM pembayaran_iuran p JOIN transaksi_kas k ON k.pembayaran_id=p.id
                WHERE (p.status='dibatalkan') <> EXISTS(SELECT 1 FROM transaksi_kas r WHERE r.membalik_id=k.id)
            UNION ALL SELECT 'pembalikan_kas',r.id FROM transaksi_kas r JOIN transaksi_kas k ON k.id=r.membalik_id WHERE r.nominal<>-k.nominal OR k.jenis='pembalikan'
            UNION ALL SELECT 'kas_iuran_sumber',k.id FROM transaksi_kas k WHERE k.sumber='iuran' AND k.jenis='pemasukan' AND k.pembayaran_id IS NULL
            UNION ALL SELECT 'keringanan_sumber',k.id FROM keringanan_iuran k JOIN tagihan_iuran t ON t.id=k.tagihan_id
                JOIN pembayaran_iuran p ON p.id=k.pembayaran_id WHERE t.rumah_id<>p.rumah_id OR (k.dibatalkan_at IS NULL AND p.status='dibatalkan')
            UNION ALL SELECT 'penyesuaian_pembalikan',j.id FROM penyesuaian_tagihan j JOIN penyesuaian_tagihan o ON o.id=j.membalik_id
                WHERE j.tagihan_id<>o.tagihan_id OR j.delta<>-o.delta OR o.membalik_id IS NOT NULL
            UNION ALL SELECT 'titipan_negatif',id FROM rt05_titipan_saldo WHERE saldo<0
            UNION ALL SELECT 'titipan_sumber',m.id FROM mutasi_titipan m JOIN mutasi_titipan l ON l.id=m.lot_id
                JOIN pembayaran_iuran p ON p.id=m.pembayaran_id LEFT JOIN penggunaan_titipan u ON u.id=m.penggunaan_titipan_id
                WHERE l.jenis<>'masuk' OR l.rumah_id<>m.rumah_id OR l.pembayaran_id<>m.pembayaran_id
                   OR p.rumah_id<>m.rumah_id OR (u.id IS NOT NULL AND u.rumah_id<>m.rumah_id)
            UNION ALL SELECT 'titipan_kredit',p.id FROM pembayaran_iuran p WHERE p.nominal_titipan<>
                COALESCE((SELECT sum(nominal) FROM mutasi_titipan WHERE pembayaran_id=p.id AND jenis='masuk'),0)
            UNION ALL SELECT 'titipan_pembayaran_batal',p.id FROM pembayaran_iuran p WHERE p.status='dibatalkan' AND
                COALESCE((SELECT sum(saldo) FROM rt05_titipan_saldo WHERE pembayaran_id=p.id),0)<>0
            UNION ALL SELECT 'titipan_kredit_pembalikan',m.id FROM mutasi_titipan m JOIN pembayaran_iuran p ON p.id=m.pembayaran_id
                WHERE m.jenis='masuk' AND (p.status='dibatalkan') <> EXISTS(SELECT 1 FROM mutasi_titipan r WHERE r.membalik_id=m.id)
            UNION ALL SELECT 'titipan_pembalikan',m.id FROM mutasi_titipan m JOIN mutasi_titipan o ON o.id=m.membalik_id
                WHERE o.jenis='pembalikan' OR m.lot_id<>o.lot_id OR m.nominal<>o.nominal OR m.arah<>-o.arah
                    OR m.penggunaan_titipan_id IS DISTINCT FROM o.penggunaan_titipan_id
            UNION ALL SELECT 'titipan_penggunaan',u.id FROM penggunaan_titipan u WHERE
                (CASE WHEN u.dibatalkan_at IS NULL THEN u.nominal ELSE 0 END)<>
                    COALESCE((SELECT sum(nominal_alokasi) FROM alokasi_pembayaran WHERE penggunaan_titipan_id=u.id AND dibatalkan_at IS NULL),0)
                OR (CASE WHEN u.dibatalkan_at IS NULL THEN u.nominal ELSE 0 END)<>
                    -COALESCE((SELECT sum(arah*nominal) FROM mutasi_titipan WHERE penggunaan_titipan_id=u.id),0);

            CREATE OR REPLACE FUNCTION rt05_finance_validate() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('rt05:keuangan'));
                IF EXISTS(SELECT 1 FROM rt05_finance_violations) THEN
                    RAISE EXCEPTION 'Invarian keuangan tidak terpenuhi' USING ERRCODE='23514';
                END IF;
                RETURN NULL;
            END $fn$;
            SQL);

        foreach (['tarif_iuran', 'tarif_rumah', 'keringanan_iuran', 'penggunaan_titipan'] as $table) {
            DB::unprepared("ALTER TABLE $table ADD CONSTRAINT {$table}_pembatalan_lengkap CHECK (
                (dibatalkan_at IS NULL AND dibatalkan_oleh_id IS NULL AND alasan_pembatalan IS NULL) OR
                (dibatalkan_at IS NOT NULL AND dibatalkan_oleh_id IS NOT NULL AND length(trim(alasan_pembatalan)) BETWEEN 5 AND 300 AND alasan_pembatalan IS NOT NULL))");
            DB::unprepared("CREATE TRIGGER {$table}_m18_immutable BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION rt05_batal_saja()");
        }
        foreach (['mutasi_titipan', 'penyesuaian_tagihan'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_m18_immutable BEFORE UPDATE OR DELETE ON $table FOR EACH ROW EXECUTE FUNCTION rt05_immutable_row()");
        }
        foreach (['tarif_iuran', 'tarif_rumah', 'tagihan_iuran', 'pembayaran_iuran', 'alokasi_pembayaran', 'transaksi_kas', 'keringanan_iuran', 'penggunaan_titipan', 'mutasi_titipan', 'penyesuaian_tagihan'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_m18_lock BEFORE INSERT OR UPDATE OR DELETE ON $table FOR EACH STATEMENT EXECUTE FUNCTION rt05_keuangan_lock()");
            if (! in_array($table, ['tarif_iuran', 'tarif_rumah'])) {
                DB::unprepared("CREATE CONSTRAINT TRIGGER {$table}_m18_check AFTER INSERT OR UPDATE OR DELETE ON $table DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt05_finance_validate()");
            }
        }
        DB::unprepared(<<<'SQL'
            DO $check$ BEGIN
                IF EXISTS(SELECT 1 FROM rt05_finance_violations) THEN
                    RAISE EXCEPTION 'Migrasi M18 ditolak: rekonsiliasi ledger lama harus bersih' USING ERRCODE='23514';
                END IF;
            END $check$;
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('M18 ledger tidak dihapus lewat rollback. Gunakan perbaikan maju atau runbook restore terverifikasi.');
    }
};
