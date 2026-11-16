<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Review keamanan 16 Nov 2026 (ADR61):
 * - filter daftar yang tidak sah dijawab 422, bukan 500 (sebelumnya UUID/bulan salah sampai ke Postgres/Carbon);
 * - data inti tidak bisa dihapus permanen dari database; arsip + pulihkan tetap jalan.
 */
class M16ReviewKeamananTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    public function test_invalid_list_filters_from_resident_are_rejected_with_422(): void
    {
        $warga = $this->warga('rk1');

        $this->as($warga)->getJson('/api/v1/agendas?bulan=bukan-bulan')->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->as($warga)->getJson('/api/v1/agendas?bulan[]=2026-11')->assertStatus(422);
        $this->as($warga)->getJson('/api/v1/complaints?kategori_id=bukan-uuid')->assertStatus(422);
        $this->as($warga)->getJson('/api/v1/complaints?kategori_id[]=x')->assertStatus(422);

        // Filter yang sah tetap bekerja.
        $this->as($warga)->getJson('/api/v1/agendas?bulan=2026-11')->assertOk();
        $this->as($warga)->getJson('/api/v1/complaints?kategori_id=00000000-0000-4000-8000-000000000000')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_invalid_list_filters_from_pengurus_are_rejected_with_422(): void
    {
        $admin = $this->pengurus('rk-admin@contoh.test');

        foreach ([
            '/api/v1/admin/dues-charges?rumah_id=bukan-uuid',
            '/api/v1/admin/dues-charges?periode[]=2026-11',
            '/api/v1/admin/dues-payments?rumah_id=bukan-uuid',
            '/api/v1/admin/cash-transactions?jenis[]=masuk',
            '/api/v1/admin/houses?q[]=a',
            '/api/v1/admin/families?q[]=a',
            '/api/v1/admin/accounts?q[]=a',
            '/api/v1/admin/accounts?status[]=aktif',
            '/api/v1/admin/additional-account-requests?status[]=diajukan',
            '/api/v1/admin/audit-logs?entity_type[]=akun',
            '/api/v1/admin/dues-recap?periode[]=2026-11',
        ] as $url) {
            $status = $this->as($admin)->getJson($url)->getStatusCode();
            $this->assertSame(422, $status, "$url harus 422, dapat $status");
        }

        $this->as($admin)->getJson('/api/v1/admin/houses?q=uji')->assertOk();
        $this->as($admin)->getJson('/api/v1/admin/accounts?q=uji&status=aktif')->assertOk();
    }

    public function test_core_rows_cannot_be_hard_deleted_and_archive_stays_restorable(): void
    {
        $admin = $this->pengurus('rk-hapus@contoh.test');
        $warga = $this->warga('rk2');
        $rumahId = $warga->rumah()->id;

        foreach ([
            'akun' => fn () => DB::table('akun')->where('id', $warga->id)->delete(),
            'tautan_akun' => fn () => DB::table('tautan_akun')->where('akun_id', $warga->id)->delete(),
            'tagihan_iuran' => fn () => DB::table('tagihan_iuran')->where('rumah_id', $rumahId)->delete(),
            'penghunian' => fn () => DB::table('penghunian')->where('rumah_id', $rumahId)->delete(),
            'model Eloquent' => fn () => $warga->delete(),
        ] as $label => $delete) {
            try {
                DB::transaction($delete);
                $this->fail("Hapus permanen $label harus ditolak database.");
            } catch (QueryException $e) {
                $this->assertSame('42501', $e->getCode(), $label);
            }
        }
        $this->assertDatabaseHas('akun', ['id' => $warga->id, 'status' => 'aktif']);

        // Jalur resmi: arsip mencatat tanggal, baris tetap ada, dan pengurus dapat memulihkannya.
        $this->as($admin)->postJson("/api/v1/admin/accounts/{$warga->id}/archive", ['alasan' => 'Uji arsip review keamanan'])->assertSuccessful();
        $this->assertNotNull(DB::table('akun')->where('id', $warga->id)->value('archived_at'));
        $this->as($admin)->postJson("/api/v1/admin/accounts/{$warga->id}/restore", ['alasan' => 'Uji pulihkan review keamanan'])->assertSuccessful();
        $this->assertDatabaseHas('akun', ['id' => $warga->id, 'status' => 'nonaktif', 'archived_at' => null]);
    }

    public function test_truncate_is_blocked_even_for_schema_owner_and_explicit_permit_is_transaction_scoped(): void
    {
        // Tabel daun (tanpa FK masuk) agar yang menolak adalah trigger, bukan aturan FK. Selalu rollback.
        $owner = DB::connection('pgsql_migrations');
        $owner->beginTransaction();
        try {
            $owner->statement('TRUNCATE riwayat_aspirasi');
            $this->fail('TRUNCATE harus ditolak.');
        } catch (QueryException $e) {
            $this->assertSame('42501', $e->getCode());
        } finally {
            $owner->rollBack();
        }

        $warga = $this->warga('rk3');
        $pref = fn () => DB::table('permohonan_akun')->where('akun_id', $warga->id)->delete();
        $this->hapusPermanen($pref);
        $this->assertDatabaseMissing('permohonan_akun', ['akun_id' => $warga->id]);

        // Izin tidak bocor ke pernyataan berikutnya.
        try {
            DB::transaction(fn () => DB::table('tautan_akun')->where('akun_id', $warga->id)->delete());
            $this->fail('Izin hapus harus berakhir bersama pembersihan.');
        } catch (QueryException $e) {
            $this->assertSame('42501', $e->getCode());
        }
    }
}
