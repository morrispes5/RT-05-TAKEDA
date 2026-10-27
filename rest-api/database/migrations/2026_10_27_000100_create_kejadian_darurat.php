<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kejadian_darurat', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->foreignUuid('pelapor_id')->constrained('akun')->restrictOnDelete();
            $t->foreignUuid('rumah_id')->constrained('rumah')->restrictOnDelete();
            $t->string('jenis', 30);
            $t->string('status', 20)->default('aktif');
            $t->boolean('penyalahgunaan')->default(false);
            $t->foreignUuid('ditangani_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $t->text('catatan_pengurus')->nullable();
            $t->timestampTz('selesai_at')->nullable();
            $t->unsignedInteger('versi')->default(1);
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['pelapor_id', 'created_at']);
            $t->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_jenis_check CHECK (jenis IN ('kebakaran','kemalingan','penyerangan','lainnya'))");
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_status_check CHECK (status IN ('aktif','selesai','palsu'))");
        DB::statement('ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_versi_check CHECK (versi >= 1)');
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_penanganan_check CHECK ((status = 'aktif' AND selesai_at IS NULL AND ditangani_oleh_id IS NULL AND NOT penyalahgunaan AND catatan_pengurus IS NULL) OR (status <> 'aktif' AND selesai_at IS NOT NULL AND selesai_at >= created_at AND ditangani_oleh_id IS NOT NULL AND catatan_pengurus IS NOT NULL AND length(trim(catatan_pengurus)) > 0 AND (NOT penyalahgunaan OR status = 'palsu')))");
        DB::statement("CREATE UNIQUE INDEX darurat_satu_aktif_per_akun ON kejadian_darurat(pelapor_id) WHERE status = 'aktif'");
        DB::table('system_settings')->insert(['key' => 'darurat.fitur', 'value' => json_encode(['aktif' => false]), 'version' => 1, 'updated_at' => now()]);
        Schema::table('notifikasi', fn (Blueprint $t) => $t->foreignUuid('kejadian_darurat_id')->nullable()->constrained('kejadian_darurat')->restrictOnDelete());
        $this->notificationChecks(true);
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_guard_darurat() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Riwayat darurat tidak boleh dihapus' USING ERRCODE = '23514';
                END IF;
                IF OLD.status <> 'aktif' OR NEW.status = 'aktif'
                    OR (NEW.id, NEW.pelapor_id, NEW.rumah_id, NEW.jenis, NEW.created_at)
                        IS DISTINCT FROM (OLD.id, OLD.pelapor_id, OLD.rumah_id, OLD.jenis, OLD.created_at)
                    OR NEW.versi <> OLD.versi + 1 THEN
                    RAISE EXCEPTION 'Transisi riwayat darurat tidak valid' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER kejadian_darurat_immutable BEFORE UPDATE OR DELETE ON kejadian_darurat
                FOR EACH ROW EXECUTE FUNCTION rt05_guard_darurat();
        SQL);
    }

    private function notificationChecks(bool $panic): void
    {
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT notifikasi_jenis_check');
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT notifikasi_satu_konteks');
        $refs = ['pengumuman' => 'pengumuman_id', 'agenda' => 'agenda_id', 'pengaduan' => 'pengaduan_id', 'aspirasi' => 'aspirasi_id', 'iuran' => 'tagihan_id', 'usulan' => 'usulan_kegiatan_id'];
        if ($panic) {
            $refs['darurat'] = 'kejadian_darurat_id';
        }
        $cols = implode(',', $refs);
        $kinds = implode(',', array_map(fn ($v) => "'$v'", array_keys($refs)));
        $cases = ["(jenis = 'sistem' AND num_nonnulls($cols) = 0)"];
        foreach ($refs as $kind => $col) {
            if ($kind === 'iuran') {
                // M15b receipt confirmations have no charge reference; preserve that existing contract.
                $other = implode(',', array_diff($refs, [$col]));
                $cases[] = "(jenis = 'iuran' AND num_nonnulls($other) = 0)";

                continue;
            }
            $cases[] = "(jenis = '$kind' AND $col IS NOT NULL AND num_nonnulls($cols) = 1)";
        }
        DB::statement("ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_jenis_check CHECK (jenis IN ('sistem',$kinds))");
        DB::statement('ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_satu_konteks CHECK ('.implode(' OR ', $cases).')');
    }

    public function down(): void
    {
        if (DB::table('kejadian_darurat')->exists()) {
            throw new RuntimeException('Riwayat darurat sudah ada; rollback yang menghapus riwayat ditolak.');
        }
        $this->notificationChecks(false);
        Schema::table('notifikasi', fn (Blueprint $t) => $t->dropConstrainedForeignId('kejadian_darurat_id'));
        DB::table('system_settings')->where('key', 'darurat.fitur')->delete();
        Schema::dropIfExists('kejadian_darurat');
        DB::statement('DROP FUNCTION IF EXISTS rt05_guard_darurat()');
    }
};
