<?php

namespace Tests\Feature;

use App\Domain\Community\Notifications;
use App\Domain\Operations\OutboxDispatcher;
use App\Models\Aspirasi;
use App\Models\OutboxEvent;
use App\Models\Pengaduan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * M09 (ROADMAP gate): aspirasi terpisah dari pengaduan; konfirmasi hanya sesudah commit; transisi
 * valid; anonimitas pada detail/feed/riwayat/notifikasi; edit pemilik; notifikasi tanpa duplikat;
 * filter dan pagination; arsip mempertahankan riwayat.
 */
class M09AspirationTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function aspiration($akun, bool $anon = true, string $judul = 'Usul taman bermain', string $isi = 'Mohon disediakan taman bermain anak.')
    {
        return $this->as($akun)->postJson('/api/v1/aspirations', ['judul' => $judul, 'isi' => $isi, 'sembunyikan_identitas' => $anon]);
    }

    private function setStatus(string $id, string $to, int $version, ?string $note = null)
    {
        return $this->postJson("/api/v1/admin/aspirations/$id/status", ['status' => $to, 'expected_version' => $version, 'catatan' => $note]);
    }

    public function test_create_is_confirmed_only_with_all_records_in_one_transaction_and_matches_contract(): void
    {
        $a = $this->warga('100');
        $res = $this->aspiration($a)->assertCreated()
            ->assertJsonPath('data.status', 'diajukan')->assertJsonPath('data.versi', 1)
            ->assertJsonPath('data.milik_saya', true)->assertJsonPath('data.pengirim.id', $a->id);
        $id = $res->json('data.id');

        $this->assertSame([], OpenApi::errors($res->json(), 'AspirationEnvelope'));
        $this->assertNotNull($res->json('data.created_at'));
        $this->assertSame(1, DB::table('riwayat_aspirasi')->where('aspirasi_id', $id)->where('status_ke', 'diajukan')->count());
        $this->assertTrue(DB::table('audit_logs')->where('entity_id', $id)->where('action', 'aspirasi.dibuat')->exists());
        $this->assertSame(1, OutboxEvent::where('aggregate_id', $id)->where('type', 'aspirasi.dibuat')->count());
        $this->assertSame(0, Pengaduan::count());
    }

    public function test_invalid_input_is_rejected_without_any_write(): void
    {
        $a = $this->warga('101');
        $this->as($a);
        $this->postJson('/api/v1/aspirations', ['isi' => 'tanpa judul'])->assertStatus(422);
        $this->postJson('/api/v1/aspirations', ['judul' => 'tanpa isi'])->assertStatus(422);
        $this->postJson('/api/v1/aspirations', ['judul' => str_repeat('a', 151), 'isi' => 'x'])->assertStatus(422);
        $this->postJson('/api/v1/aspirations', ['judul' => 'x', 'isi' => str_repeat('a', 5001)])->assertStatus(422);
        $this->postJson('/api/v1/aspirations', ['judul' => 'x', 'isi' => 'y', 'sembunyikan_identitas' => 'mungkin'])->assertStatus(422);

        $this->assertSame(0, Aspirasi::count());
        $this->assertSame(0, DB::table('riwayat_aspirasi')->count());
        $this->assertSame(0, OutboxEvent::where('type', 'aspirasi.dibuat')->count());
    }

    public function test_anonymous_aspiration_leaks_no_identity_to_other_residents_anywhere(): void
    {
        $a = $this->warga('102');
        $b = $this->warga('103');
        $admin = $this->pengurus();
        $id = $this->aspiration($a, true)->json('data.id');
        $this->as($admin)->postJson("/api/v1/admin/aspirations/$id/status", ['status' => 'ditinjau', 'expected_version' => 1, 'catatan' => 'Dibahas rapat'])->assertOk();
        OutboxDispatcher::dispatch();

        $urls = ["/api/v1/aspirations/$id", '/api/v1/aspirations', "/api/v1/aspirations/$id/history", '/api/v1/notifications'];
        foreach ($urls as $url) {
            $body = $this->as($b)->getJson($url)->assertOk()->getContent();
            foreach ([$a->id, $a->email, 'uji102', 'Uji Kepala 102'] as $needle) {
                $this->assertStringNotContainsString($needle, $body, "$url membocorkan $needle");
            }
        }
        $this->as($b)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.pengirim', null)->assertJsonPath('data.milik_saya', false);
        $history = $this->as($b)->getJson("/api/v1/aspirations/$id/history")->json('data');
        $this->assertSame(['nama' => 'Warga', 'peran' => 'warga'], $history[0]['aktor']);
        $this->assertSame(['nama' => 'Pengurus RT', 'peran' => 'pengurus'], $history[1]['aktor']);

        // Pemilik dan pengurus tetap melihat pengirim (BR09).
        $this->as($a)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.pengirim.id', $a->id);
        $this->as($admin)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.pengirim.id', $a->id);
        // Notifikasi pengurus tentang aspirasi baru tidak memuat identitas dan tidak sampai ke warga lain.
        $this->assertSame(0, DB::table('notifikasi')->where('penerima_id', $b->id)->where('jenis', 'aspirasi')->count());
        $this->assertStringNotContainsString('Uji Kepala 102', (string) DB::table('notifikasi')->where('penerima_id', $admin->id)->pluck('isi')->join(' '));
    }

    public function test_non_anonymous_aspiration_shows_sender_and_toggle_is_respected(): void
    {
        $a = $this->warga('104');
        $b = $this->warga('105');
        $id = $this->aspiration($a, false)->json('data.id');
        $this->as($b)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.pengirim.id', $a->id);

        $this->as($a)->patchJson("/api/v1/aspirations/$id", ['versi' => 1, 'sembunyikan_identitas' => true])->assertOk()->assertJsonPath('data.versi', 2);
        $this->as($b)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.pengirim', null);
    }

    public function test_anonymous_aspiration_by_a_pengurus_leaves_no_indirect_identity(): void
    {
        $sender = $this->pengurus('pengirim-pengurus@contoh.test');
        $other = $this->pengurus('pengurus-lain@contoh.test');
        $b = $this->warga('106');
        $id = $this->aspiration($sender, true)->assertCreated()->json('data.id');
        $this->as($other);
        $this->setStatus($id, 'ditinjau', 1)->assertOk();

        $history = $this->as($b)->getJson("/api/v1/aspirations/$id/history")->json('data');
        $this->assertSame('Warga', $history[0]['aktor']['nama']);
        foreach (["/api/v1/aspirations/$id", '/api/v1/aspirations', "/api/v1/aspirations/$id/history"] as $url) {
            $body = $this->as($b)->getJson($url)->getContent();
            $this->assertStringNotContainsString($sender->id, $body, $url);
            $this->assertStringNotContainsString('pengirim-pengurus', $body, $url);
        }
        $this->as($other)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.pengirim.id', $sender->id);
    }

    public function test_pending_and_inactive_accounts_are_denied_on_every_endpoint(): void
    {
        $a = $this->warga('107');
        $id = $this->aspiration($a, false)->json('data.id');
        $pending = $this->pemohon('107', 'tambahan107@contoh.test');
        $inactive = $this->warga('108');
        $inactive->forceFill(['status' => 'nonaktif'])->save();

        foreach ([$pending, $inactive] as $akun) {
            $this->as($akun)->getJson('/api/v1/aspirations')->assertForbidden();
            $this->getJson("/api/v1/aspirations/$id")->assertForbidden();
            $this->getJson("/api/v1/aspirations/$id/history")->assertForbidden();
            $this->patchJson("/api/v1/aspirations/$id", ['versi' => 1, 'judul' => 'x'])->assertForbidden();
            $this->postJson('/api/v1/aspirations', ['judul' => 'x', 'isi' => 'y'])->assertForbidden();
        }
        $this->assertSame(1, Aspirasi::count());
    }

    public function test_only_pengurus_can_change_status_or_archive(): void
    {
        $a = $this->warga('109');
        $id = $this->aspiration($a, false)->json('data.id');
        $this->as($a);
        $this->setStatus($id, 'ditinjau', 1)->assertForbidden();
        $this->postJson("/api/v1/admin/aspirations/$id/archive", ['alasan' => 'x'])->assertForbidden();
        $this->assertSame('diajukan', Aspirasi::find($id)->status);
    }

    public function test_owner_boundary_and_edit_only_while_submitted(): void
    {
        $a = $this->warga('110');
        $b = $this->warga('111');
        $admin = $this->pengurus();
        $id = $this->aspiration($a, true)->json('data.id');

        $this->as($b)->patchJson("/api/v1/aspirations/$id", ['versi' => 1, 'judul' => 'diambil alih'])->assertForbidden()->assertJsonPath('error.code', 'ASPIRATION_LOCKED');
        $this->as($a)->patchJson("/api/v1/aspirations/$id", ['versi' => 1, 'judul' => 'Judul diperbaiki'])->assertOk()->assertJsonPath('data.judul', 'Judul diperbaiki');

        $this->as($admin);
        $this->setStatus($id, 'ditinjau', 2)->assertOk();
        $this->as($a)->patchJson("/api/v1/aspirations/$id", ['versi' => 3, 'judul' => 'Terlambat'])->assertForbidden()->assertJsonPath('error.code', 'ASPIRATION_LOCKED');
        $this->assertSame('Judul diperbaiki', Aspirasi::find($id)->judul);
        $this->as($a)->patchJson('/api/v1/aspirations/'.fake()->uuid(), ['versi' => 1, 'judul' => 'x'])->assertNotFound();
    }

    public function test_stale_versions_conflict_on_owner_edit_and_on_status_change(): void
    {
        $a = $this->warga('112');
        $admin = $this->pengurus();
        $id = $this->aspiration($a, true)->json('data.id');

        $this->as($a)->patchJson("/api/v1/aspirations/$id", ['versi' => 1, 'isi' => 'Isi baru'])->assertOk()->assertJsonPath('data.versi', 2);
        $this->patchJson("/api/v1/aspirations/$id", ['versi' => 1, 'isi' => 'Dari perangkat lama'])->assertStatus(409)->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->patchJson("/api/v1/aspirations/$id", ['isi' => 'Tanpa versi'])->assertStatus(422);
        $this->patchJson("/api/v1/aspirations/$id", ['versi' => 2, 'isi' => 'Dari perangkat lama'])->assertOk()->assertJsonPath('data.isi', 'Dari perangkat lama');

        $this->as($admin);
        $this->setStatus($id, 'ditinjau', 1)->assertStatus(409)->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->setStatus($id, 'ditinjau', 3)->assertOk()->assertJsonPath('data.versi', 4);
        $this->setStatus($id, 'selesai', 3)->assertStatus(409);
    }

    public function test_status_flow_is_valid_only_and_reopen_requires_a_reason(): void
    {
        $a = $this->warga('113');
        $admin = $this->pengurus();
        $id = $this->aspiration($a, false)->json('data.id');
        $this->as($admin);

        $this->setStatus($id, 'selesai', 1)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
        $this->setStatus($id, 'diproses', 1)->assertStatus(422);
        $this->setStatus($id, 'diajukan', 1)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
        $this->postJson("/api/v1/admin/aspirations/$id/status", ['status' => 'ditinjau'])->assertStatus(422);
        $this->setStatus($id, 'ditinjau', 1, 'Ditampung')->assertOk()->assertJsonPath('data.status', 'ditinjau');
        $this->setStatus($id, 'diajukan', 2)->assertStatus(422);
        $this->setStatus($id, 'selesai', 2, 'Sudah dilaksanakan')->assertOk()->assertJsonPath('data.status', 'selesai');
        $this->setStatus($id, 'ditinjau', 3)->assertStatus(422)->assertJsonPath('error.code', 'REOPEN_REASON_REQUIRED');
        $this->setStatus($id, 'ditinjau', 3, '   ')->assertStatus(422)->assertJsonPath('error.code', 'REOPEN_REASON_REQUIRED');
        $this->setStatus($id, 'ditinjau', 3, 'Usulan lanjutan dari warga')->assertOk()->assertJsonPath('data.status', 'ditinjau');

        $history = DB::table('riwayat_aspirasi')->where('aspirasi_id', $id)->orderBy('created_at')->orderBy('status_ke')->get();
        $this->assertSame(4, $history->count());
        $this->assertTrue(DB::table('audit_logs')->where('entity_id', $id)->where('action', 'aspirasi.status')->count() === 3);
    }

    public function test_archive_hides_from_residents_keeps_history_and_blocks_changes(): void
    {
        $a = $this->warga('114');
        $b = $this->warga('115');
        $admin = $this->pengurus();
        $id = $this->aspiration($a, false)->json('data.id');
        $this->as($admin);
        $this->setStatus($id, 'ditinjau', 1)->assertOk();
        $this->postJson("/api/v1/admin/aspirations/$id/archive", ['alasan' => ''])->assertStatus(422);
        $this->postJson("/api/v1/admin/aspirations/$id/archive", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/aspirations/$id/archive", ['alasan' => 'Duplikat usulan lain'])->assertNoContent();

        $this->postJson("/api/v1/admin/aspirations/$id/archive", ['alasan' => 'lagi'])->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_ARCHIVED');
        $this->setStatus($id, 'selesai', 2)->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_ARCHIVED');
        $this->getJson('/api/v1/aspirations')->assertJsonCount(0, 'data');
        $listed = $this->getJson('/api/v1/aspirations?arsip=1')->assertJsonPath('data.0.id', $id);
        $this->assertNotNull($listed->json('data.0.archived_at'));
        $this->assertNotNull($this->getJson("/api/v1/aspirations/$id")->json('data.archived_at'));
        $this->getJson("/api/v1/aspirations/$id/history")->assertJsonCount(2, 'data');
        $this->assertTrue(DB::table('audit_logs')->where('entity_id', $id)->where('action', 'aspirasi.diarsipkan')->exists());

        foreach ([$a, $b] as $warga) {
            $this->as($warga)->getJson("/api/v1/aspirations/$id")->assertNotFound();
            $this->getJson("/api/v1/aspirations/$id/history")->assertNotFound();
            $this->getJson('/api/v1/aspirations?arsip=1')->assertJsonCount(0, 'data');
        }
        $this->as($a)->getJson('/api/v1/aspirations?milik_saya=1')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/aspirations/$id", ['versi' => 2, 'judul' => 'x'])->assertNotFound();
        $this->assertSame(1, Aspirasi::count());
    }

    public function test_archived_at_is_reported_only_to_pengurus_and_active_items_report_null(): void
    {
        $a = $this->warga('116');
        $admin = $this->pengurus();
        $id = $this->aspiration($a, false)->json('data.id');
        $this->as($a)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.archived_at', null);
        $res = $this->as($admin)->getJson("/api/v1/aspirations/$id")->assertJsonPath('data.archived_at', null);
        $this->assertSame([], OpenApi::errors($res->json(), 'AspirationEnvelope'));
        $this->assertSame([], OpenApi::errors($this->getJson('/api/v1/aspirations')->json(), 'AspirationListEnvelope'));
        $this->assertSame([], OpenApi::errors($this->getJson("/api/v1/aspirations/$id/history")->json(), 'ReportHistoryEnvelope'));
    }

    public function test_status_notifications_name_each_transition_reach_only_the_sender_and_never_duplicate(): void
    {
        $a = $this->warga('117');
        $b = $this->warga('118');
        $admin = $this->pengurus();
        $admin2 = $this->pengurus('pengurus2@contoh.test');
        $id = $this->aspiration($a, true)->json('data.id');
        $this->as($admin);
        $this->setStatus($id, 'ditinjau', 1)->assertOk();
        $this->setStatus($id, 'selesai', 2)->assertOk();
        $this->setStatus($id, 'ditinjau', 3, 'Dibuka ulang')->assertOk();

        OutboxDispatcher::dispatch();

        $labels = DB::table('notifikasi')->where('penerima_id', $a->id)->where('aspirasi_id', $id)->pluck('isi')
            ->map(fn ($t) => preg_match('/berstatus (\w+)\./', $t, $m) ? $m[1] : null)->sort()->values()->all();
        $this->assertSame(['Ditinjau', 'Ditinjau', 'Selesai'], $labels);
        $this->assertSame(0, DB::table('notifikasi')->where('penerima_id', $b->id)->where('aspirasi_id', $id)->count());
        // Aspirasi baru → satu notifikasi per pengurus aktif, bukan untuk warga.
        $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $admin->id)->where('aspirasi_id', $id)->where('judul', 'Aspirasi baru')->count());
        $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $admin2->id)->where('aspirasi_id', $id)->where('judul', 'Aspirasi baru')->count());
        $this->assertSame(0, DB::table('notifikasi')->where('penerima_id', $a->id)->where('judul', 'Aspirasi baru')->count());

        // Replay event (pengiriman ganda): UNIQUE(event_id, penerima) menahan duplikat.
        $before = DB::table('notifikasi')->count();
        foreach (OutboxEvent::where('aggregate_id', $id)->get() as $event) {
            Notifications::handle($event);
            Notifications::handle($event);
        }
        $this->assertSame($before, DB::table('notifikasi')->count());
    }

    public function test_feed_filters_pagination_and_ordering(): void
    {
        $a = $this->warga('119');
        $b = $this->warga('120');
        $admin = $this->pengurus();
        $ids = [];
        foreach (range(1, 25) as $n) {
            $ids[$n] = $this->aspiration($n % 5 === 0 ? $b : $a, false, "Usulan nomor $n")->json('data.id');
        }
        // created_at sama dalam satu transaksi tes: urutkan eksplisit agar urutan terbaru-dahulu terukur.
        foreach ($ids as $n => $id) {
            DB::table('aspirasi')->where('id', $id)->update(['created_at' => now()->subMinutes(100 - $n)]);
        }
        $this->as($admin);
        $this->setStatus($ids[3], 'ditinjau', 1)->assertOk();
        $this->setStatus($ids[4], 'ditinjau', 1)->assertOk();
        $this->setStatus($ids[4], 'selesai', 2)->assertOk();

        $this->as($a);
        $page1 = $this->getJson('/api/v1/aspirations?per_page=10')->assertOk();
        $this->assertSame(['page' => 1, 'per_page' => 10, 'total' => 25, 'last_page' => 3], collect($page1->json('meta'))->only(['page', 'per_page', 'total', 'last_page'])->all());
        $this->assertSame('Usulan nomor 25', $page1->json('data.0.judul'));
        $this->assertCount(10, $page1->json('data'));
        $page3 = $this->getJson('/api/v1/aspirations?per_page=10&page=3')->assertOk();
        $this->assertCount(5, $page3->json('data'));
        $this->assertSame('Usulan nomor 1', $page3->json('data.4.judul'));
        $this->assertNull($page3->json('links.next'));
        $this->assertSame(100, $this->getJson('/api/v1/aspirations?per_page=500')->json('meta.per_page'));
        $this->assertSame(1, $this->getJson('/api/v1/aspirations?per_page=0')->json('meta.per_page'));

        $this->assertSame(1, $this->getJson('/api/v1/aspirations?status=ditinjau')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/aspirations?status=selesai')->json('meta.total'));
        $this->assertSame(23, $this->getJson('/api/v1/aspirations?status=diajukan')->json('meta.total'));
        $this->assertSame(20, $this->getJson('/api/v1/aspirations?milik_saya=1')->json('meta.total'));
        $this->assertSame(5, $this->as($b)->getJson('/api/v1/aspirations?milik_saya=1')->json('meta.total'));
        $this->as($a)->getJson('/api/v1/aspirations?status=dihapus')->assertStatus(422);
        $this->getJson('/api/v1/aspirations?status=diproses')->assertStatus(422);
        // Pengaduan memakai alur sendiri; filter status tidak valid ditolak dengan cara yang sama.
        $this->getJson('/api/v1/complaints?status=ditinjau')->assertStatus(422);
        $this->getJson('/api/v1/complaints?status=diproses')->assertOk();
    }

    public function test_aspirations_and_complaints_are_separate_domains(): void
    {
        $a = $this->warga('121');
        $id = $this->aspiration($a, false)->json('data.id');
        $this->as($a)->getJson("/api/v1/complaints/$id")->assertNotFound();
        $this->assertSame(0, Pengaduan::count());
        $this->assertSame(0, DB::table('riwayat_pengaduan')->count());
        $this->assertFalse(array_key_exists('foto', $this->getJson("/api/v1/aspirations/$id")->json('data')));
        $this->assertFalse(array_key_exists('kategori', $this->getJson("/api/v1/aspirations/$id")->json('data')));
    }
}
