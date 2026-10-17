<?php

namespace Tests\Feature;

use App\Domain\Community\Push\FcmGateway;
use App\Domain\Community\Push\PushGateway;
use App\Domain\Community\PushDelivery;
use App\Domain\Operations\OutboxDispatcher;
use App\Models\Agenda;
use App\Models\Akun;
use App\Models\Notifikasi;
use App\Models\OutboxEvent;
use App\Models\PerangkatPush;
use App\Support\Outbox;
use App\Support\SharedHosting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * M10: agenda/pengumuman, inbox, token perangkat, push FCM HTTP v1, pengingat H-1, catch-up jadwal
 * shared. Push hanya pemberitahuan: provider gagal tidak mengubah event maupun inbox.
 */
class M10CommunityPushTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private const SEND_URL = 'https://fcm.googleapis.com/v1/projects/rt05-uji/messages:send';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** Sesi mobile nyata (bearer), bukan Sanctum::actingAs: perangkat terikat token akses. */
    private function bearer(Akun $akun): string
    {
        return $akun->createToken('android')->plainTextToken;
    }

    private function register(string $token, string $device, string $fcm)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->putJson("/api/v1/devices/$device", ['fcm_token' => $fcm, 'platform' => 'android']);
    }

    private function fcm(string $suffix): string
    {
        return 'fcm-uji_'.$suffix.':'.str_repeat('A1b2', 10);
    }

    private function configureFcm(): void
    {
        // Kunci RSA sekali pakai, dibuat saat tes (tidak ada kunci di repo). PHP Windows butuh
        // openssl.cnf bawaan distribusi bila OPENSSL_CONF tidak diset.
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        $opts = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA] + (is_file($cnf) && ! getenv('OPENSSL_CONF') ? ['config' => $cnf] : []);
        $key = openssl_pkey_new($opts);
        $this->assertNotFalse($key, 'openssl_pkey_new');
        openssl_pkey_export($key, $pem, null, $opts);
        config(['rt05.push.driver' => 'fcm', 'rt05.push.fcm' => [
            'project_id' => null,
            'credentials_path' => null,
            'credentials_base64' => base64_encode(json_encode([
                'type' => 'service_account', 'project_id' => 'rt05-uji',
                'client_email' => 'push@rt05-uji.iam.gserviceaccount.com', 'private_key' => $pem,
            ])),
            'timeout' => 5, 'concurrency' => 10,
        ]]);
        $this->app->forgetInstance(PushGateway::class);
    }

    /** @param array<string, array{0:int,1:array}> $byToken status/body FCM per token */
    private function fakeFcm(array $byToken = []): void
    {
        Http::fake(function (HttpRequest $request) use ($byToken) {
            if (str_starts_with($request->url(), 'https://oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'akses-uji', 'expires_in' => 3600]);
            }
            $token = $request->data()['message']['token'] ?? '';
            [$status, $body] = $byToken[$token] ?? [200, ['name' => 'projects/rt05-uji/messages/1']];

            return Http::response($body, $status);
        });
    }

    private function announce(Akun $admin, string $judul = 'Kerja bakti Minggu'): string
    {
        Sanctum::actingAs($admin);
        $id = $this->postJson('/api/v1/admin/announcements', ['judul' => $judul, 'isi' => 'Kumpul pukul 07.00 di lapangan.'])->assertCreated()->json('data.id');
        $this->app['auth']->forgetGuards();
        OutboxDispatcher::dispatch();

        return $id;
    }

    private function inboxOf(Akun $akun, string $jenis): ?Notifikasi
    {
        return Notifikasi::where('penerima_id', $akun->id)->where('jenis', $jenis)->latest('created_at')->first();
    }

    public function test_device_registration_is_bound_to_the_mobile_session_and_never_echoes_the_token(): void
    {
        $a = $this->warga('101');
        $b = $this->warga('102');
        $tokenA = $this->bearer($a);
        $fcm = $this->fcm('a');

        $res = $this->register($tokenA, 'hp-sri-0001', $fcm)->assertOk()
            ->assertJsonPath('data.device_identifier', 'hp-sri-0001')->assertJsonPath('data.platform', 'android');
        $this->assertSame([], OpenApi::errors($res->json(), 'DeviceEnvelope'));
        $this->assertStringNotContainsString($fcm, $res->getContent());
        $raw = DB::table('perangkat_push')->where('device_identifier', 'hp-sri-0001')->first();
        $this->assertNotSame($fcm, $raw->fcm_token, 'token terenkripsi di database');
        $this->assertSame($fcm, PerangkatPush::where('device_identifier', 'hp-sri-0001')->first()->fcm_token);

        // Re-register idempotent; token sama di HP lain pindah, tidak ganda.
        $this->register($tokenA, 'hp-sri-0001', $fcm)->assertOk();
        $this->register($this->bearer($b), 'hp-baru-0002', $fcm)->assertOk();
        $this->assertSame(1, PerangkatPush::count());
        $this->assertSame($b->id, PerangkatPush::first()->akun_id);

        // Validasi dan sesi cookie/non-bearer.
        $this->register($tokenA, 'x', $fcm)->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenA)->putJson('/api/v1/devices/hp-sri-0001', ['fcm_token' => $fcm, 'platform' => 'ios'])->assertStatus(422);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($a);
        $this->putJson('/api/v1/devices/hp-sri-0003', ['fcm_token' => $this->fcm('c'), 'platform' => 'android'])
            ->assertStatus(403)->assertJsonPath('error.code', 'MOBILE_SESSION_REQUIRED');
    }

    public function test_logout_and_delete_remove_the_device_and_others_cannot_delete_it(): void
    {
        $a = $this->warga('103');
        $b = $this->warga('104');
        $tokenA = $this->bearer($a);
        $this->register($tokenA, 'hp-a-00001', $this->fcm('a'))->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->bearer($b))->deleteJson('/api/v1/devices/hp-a-00001')->assertNoContent();
        $this->assertSame(1, PerangkatPush::count(), 'akun lain tidak bisa menghapus');

        $this->app['auth']->forgetGuards();
        $this->withToken($tokenA)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertSame(0, PerangkatPush::count(), 'logout menghapus token akses dan perangkatnya');

        $tokenA2 = $this->bearer($a);
        $this->register($tokenA2, 'hp-a-00001', $this->fcm('a'))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenA2)->deleteJson('/api/v1/devices/hp-a-00001')->assertNoContent();
        $this->assertSame(0, PerangkatPush::count());
    }

    public function test_pending_account_may_register_a_device(): void
    {
        $pending = $this->pemohon('105', 'tambahan105@contoh.test');
        $this->register($this->bearer($pending), 'hp-pending-01', $this->fcm('p'))->assertOk();
    }

    public function test_push_reaches_registered_devices_respects_preferences_and_carries_only_ids(): void
    {
        $this->configureFcm();
        $this->fakeFcm();
        $sri = $this->warga('106');
        $budi = $this->warga('107');
        $tanpaHp = $this->warga('108');
        $nonaktif = $this->warga('109');
        $admin = $this->pengurus();
        $this->register($this->bearer($sri), 'hp-sri-00106', $this->fcm('sri'))->assertOk();
        $this->register($this->bearer($budi), 'hp-budi-0107', $this->fcm('budi'))->assertOk();
        $this->register($this->bearer($nonaktif), 'hp-off-00109', $this->fcm('off'))->assertOk();
        $nonaktif->forceFill(['status' => 'nonaktif'])->save();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($budi);
        $this->patchJson('/api/v1/notification-preferences', ['pengumuman' => false])->assertOk()->assertJsonPath('data.pengumuman', false);

        $id = $this->announce($admin);

        $sent = Http::recorded(fn (HttpRequest $r) => $r->url() === self::SEND_URL);
        $this->assertCount(1, $sent, 'hanya HP Sri: Budi mematikan pengumuman, akun nonaktif tidak dikirimi');
        [$request] = $sent->first();
        $message = $request->data()['message'];
        $this->assertSame($this->fcm('sri'), $message['token']);
        $this->assertSame('Bearer akses-uji', $request->header('Authorization')[0]);
        $this->assertSame(['notifikasi_id', 'jenis', 'ref_type', 'ref_id'], array_keys($message['data']));
        $this->assertSame(['pengumuman', 'pengumuman', $id], [$message['data']['jenis'], $message['data']['ref_type'], $message['data']['ref_id']]);
        $this->assertSame('rt05-'.$message['data']['notifikasi_id'], $message['android']['notification']['tag']);

        $this->assertSame('terkirim', $this->inboxOf($sri, 'pengumuman')->push_status);
        $this->assertSame('dilewati', $this->inboxOf($budi, 'pengumuman')->push_status, 'preferensi push mati');
        $this->assertNotNull($this->inboxOf($budi, 'pengumuman'), 'inbox tetap ada walau push dimatikan');
        $this->assertSame('dilewati', $this->inboxOf($tanpaHp, 'pengumuman')->push_status);

        // Token akses OAuth di-cache: pengumuman kedua tidak meminta token lagi.
        $this->announce($admin, 'Pengumuman kedua');
        $this->assertCount(1, Http::recorded(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://oauth2.googleapis.com/')));
    }

    public function test_provider_failures_never_touch_the_event_or_the_inbox(): void
    {
        $this->configureFcm();
        $gone = $this->fcm('gone');
        $down = $this->fcm('down');
        $this->fakeFcm([
            $gone => [404, ['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]]],
            $down => [503, ['error' => ['status' => 'UNAVAILABLE']]],
        ]);
        $a = $this->warga('110');
        $b = $this->warga('111');
        $this->register($this->bearer($a), 'hp-gone-0110', $gone)->assertOk();
        $this->register($this->bearer($b), 'hp-down-0111', $down)->assertOk();

        $this->announce($this->pengurus());

        $event = OutboxEvent::where('type', 'pengumuman.terbit')->latest('created_at')->first();
        $this->assertNotNull($event->completed_at, 'event selesai walau push gagal');
        $this->assertNull($event->failed_at);
        $this->assertSame(0, PerangkatPush::where('device_identifier', 'hp-gone-0110')->count(), 'token UNREGISTERED dihapus');
        $this->assertSame('dilewati', $this->inboxOf($a, 'pengumuman')->push_status);
        $this->assertNull($this->inboxOf($b, 'pengumuman')->push_status, '503 → menunggu retry');

        // Percobaan habis → gagal; inbox tetap.
        PushDelivery::giveUp($event->id);
        $this->assertSame('gagal', $this->inboxOf($b, 'pengumuman')->push_status);
        $this->assertSame(2, Notifikasi::where('event_id', $event->id)->whereIn('penerima_id', [$a->id, $b->id])->count());
    }

    public function test_without_credentials_push_is_skipped_and_inbox_still_works(): void
    {
        Http::fake();
        config(['rt05.push.fcm.credentials_base64' => null, 'rt05.push.fcm.credentials_path' => null]);
        $this->app->forgetInstance(PushGateway::class);
        $a = $this->warga('112');
        $this->register($this->bearer($a), 'hp-a-000112', $this->fcm('a'))->assertOk();

        $this->announce($this->pengurus());

        Http::assertNothingSent();
        $this->assertSame('dilewati', $this->inboxOf($a, 'pengumuman')->push_status);
        $this->assertFalse(app(PushGateway::class)->configured());
    }

    public function test_push_recover_requeues_lost_push_jobs(): void
    {
        $this->configureFcm();
        $this->fakeFcm();
        $a = $this->warga('113');
        $this->register($this->bearer($a), 'hp-a-000113', $this->fcm('a'))->assertOk();
        $this->announce($this->pengurus());
        $n = $this->inboxOf($a, 'pengumuman');
        // Simulasi job push hilang: status belum terisi dan sudah lewat 10 menit.
        DB::table('notifikasi')->where('id', $n->id)->update(['push_status' => null, 'push_at' => null, 'created_at' => now()->subMinutes(15)]);

        $this->artisan('rt05:push-recover')->assertSuccessful();

        $this->assertSame('terkirim', $n->refresh()->push_status);
    }

    public function test_agenda_times_require_an_explicit_zone_and_are_stored_utc(): void
    {
        $admin = $this->pengurus();
        Sanctum::actingAs($admin);
        $base = ['nama' => 'Badminton warga', 'deskripsi' => 'Terbuka untuk semua.', 'lokasi' => 'Lapangan RT 05'];
        $day = now('Asia/Jakarta')->addDays(6)->format('Y-m-d');

        $this->postJson('/api/v1/admin/agendas', $base + ['mulai_at' => "{$day} 09:00"])->assertStatus(422)->assertJsonPath('error.fields.mulai_at.0', 'Waktu mulai harus memuat zona waktu (mis. 2026-10-23T09:00:00+07:00).');
        $this->postJson('/api/v1/admin/agendas', $base + ['mulai_at' => now()->subHour()->toIso8601String()])->assertStatus(422);
        $this->postJson('/api/v1/admin/agendas', $base + ['mulai_at' => "{$day}T09:00:00+07:00", 'selesai_at' => "{$day}T08:00:00+07:00"])->assertStatus(422);

        $res = $this->postJson('/api/v1/admin/agendas', $base + ['mulai_at' => "{$day}T09:00:00+07:00", 'selesai_at' => "{$day}T11:00:00+07:00"])->assertCreated();
        $this->assertSame("{$day}T02:00:00Z", $res->json('data.mulai_at'));
        $this->assertSame([], OpenApi::errors($res->json(), 'AgendaEnvelope'));
        $ics = $this->get('/api/v1/agendas/'.$res->json('data.id').'/calendar')->assertOk()->getContent();
        $this->assertStringContainsString('DTSTART:'.str_replace('-', '', $day).'T020000Z', $ics);
        $this->assertStringContainsString('ctz=Asia%2FJakarta', $res->json('data.google_calendar_url'));
    }

    public function test_reminder_h1_is_sent_once_with_catch_up_and_changed_agenda_is_handled(): void
    {
        $admin = $this->pengurus();
        $sri = $this->warga('114');
        Sanctum::actingAs($admin);
        $start = now()->addDays(3)->setTime(2, 0); // 09.00 WIB
        $id = $this->postJson('/api/v1/admin/agendas', ['nama' => 'Kerja bakti', 'deskripsi' => 'Bersih saluran.', 'lokasi' => 'Pos ronda', 'mulai_at' => $start->toIso8601String()])
            ->assertCreated()->json('data.id');
        OutboxDispatcher::dispatch();

        // Belum H-1: tidak ada pengingat.
        $this->artisan('rt05:pengingat-agenda')->assertSuccessful();
        $this->assertSame(0, OutboxEvent::where('type', 'agenda.pengingat')->count());

        // Cron mati semalaman; tick pertama di dalam jendela 24 jam mengejar (catch-up), sekali saja.
        $this->travelTo($start->copy()->subHours(10));
        $this->artisan('rt05:pengingat-agenda')->assertSuccessful();
        $this->artisan('rt05:pengingat-agenda')->assertSuccessful();
        $this->assertSame(1, OutboxEvent::where('type', 'agenda.pengingat')->count(), 'dedup');
        OutboxDispatcher::dispatch();
        $reminder = Notifikasi::where('penerima_id', $sri->id)->where('jenis', 'agenda')->where('judul', 'like', 'Pengingat%')->get();
        $this->assertCount(1, $reminder);

        // Waktu diubah: warga diberi tahu, pengingat lama tidak berlaku, pengingat baru menyusul H-1.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($admin);
        $newStart = $start->copy()->addDays(2);
        $this->putJson("/api/v1/admin/agendas/$id", ['nama' => 'Kerja bakti', 'deskripsi' => 'Bersih saluran.', 'lokasi' => 'Pos ronda', 'mulai_at' => $newStart->toIso8601String()])->assertOk();
        OutboxDispatcher::dispatch();
        $this->assertSame(1, Notifikasi::where('penerima_id', $sri->id)->where('judul', 'Jadwal agenda berubah')->count());
        $this->assertNull(Agenda::find($id)->pengingat_terkirim_at);

        // Pengingat basi (dicatat untuk waktu lama) diproses terlambat → tanpa inbox.
        $stale = Outbox::record('agenda.pengingat', 'agenda', $id, ['mulai_at' => $start->toIso8601String()]);
        OutboxDispatcher::dispatch();
        $this->assertNotNull($stale->refresh()->completed_at);
        $this->assertSame(0, Notifikasi::where('event_id', $stale->id)->count());

        // Ubah deskripsi saja: tanpa notifikasi.
        $this->putJson("/api/v1/admin/agendas/$id", ['nama' => 'Kerja bakti', 'deskripsi' => 'Bawa cangkul.', 'lokasi' => 'Pos ronda', 'mulai_at' => $newStart->toIso8601String()])->assertOk();
        $this->assertSame(1, OutboxEvent::where('type', 'agenda.diubah')->count());

        $this->travelTo($newStart->copy()->subHours(20));
        $this->artisan('rt05:pengingat-agenda')->assertSuccessful();
        OutboxDispatcher::dispatch();
        $this->assertSame(2, Notifikasi::where('penerima_id', $sri->id)->where('judul', 'like', 'Pengingat%')->count());

        // Agenda dibuat sudah di dalam jendela H-1: notifikasi "Agenda baru" cukup, tanpa pengingat.
        $soon = $this->postJson('/api/v1/admin/agendas', ['nama' => 'Rapat warga', 'deskripsi' => 'Rapat bulanan.', 'lokasi' => 'Balai', 'mulai_at' => now()->addHours(5)->toIso8601String()])
            ->assertCreated()->json('data.id');
        $this->artisan('rt05:pengingat-agenda')->assertSuccessful();
        $this->assertSame(0, OutboxEvent::where('type', 'agenda.pengingat')->where('aggregate_id', $soon)->count());
        $this->assertNotNull(Agenda::find($soon)->pengingat_terkirim_at);

        // Downtime sampai agenda dimulai: pengingat tidak lagi dibuat.
        $this->travelTo($newStart->copy()->addMinutes(5));
        $late = Outbox::record('agenda.pengingat', 'agenda', $id, ['mulai_at' => $newStart->toIso8601String()]);
        OutboxDispatcher::dispatch();
        $this->assertSame(0, Notifikasi::where('event_id', $late->id)->count());
    }

    public function test_archived_agenda_link_from_a_notification_is_safe_404(): void
    {
        $admin = $this->pengurus();
        $sri = $this->warga('115');
        Sanctum::actingAs($admin);
        $id = $this->postJson('/api/v1/admin/agendas', ['nama' => 'Senam pagi', 'deskripsi' => 'Senam.', 'lokasi' => 'Lapangan', 'mulai_at' => now()->addDays(4)->toIso8601String()])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/agendas/$id/archive")->assertNoContent();
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($sri);
        $this->getJson("/api/v1/agendas/$id")->assertNotFound();
        $this->getJson('/api/v1/agendas/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/v1/agendas/bukan-uuid')->assertNotFound();
    }

    public function test_system_broadcast_reaches_active_accounts_only(): void
    {
        $a = $this->warga('116');
        $pending = $this->pemohon('117', 'tambahan117@contoh.test');
        $this->artisan('rt05:siaran-sistem', ['kategori' => 'pembaruan_aplikasi', '--judul' => 'Versi baru aplikasi', '--isi' => 'Perbarui aplikasi RT05 TAKEDA dari pengurus.'])->assertSuccessful();
        $this->artisan('rt05:siaran-sistem', ['kategori' => 'promo', '--judul' => 'X', '--isi' => 'Y'])->assertFailed();
        OutboxDispatcher::dispatch();
        $this->assertSame('Versi baru aplikasi', $this->inboxOf($a, 'sistem')?->judul);
        $this->assertNull($this->inboxOf($pending, 'sistem'));
    }

    public function test_shared_schedule_catches_up_when_the_cron_tick_is_late(): void
    {
        $task = 'uji-'.Str::random(6);
        Cache::store('file')->forget('rt05:shared:ran:'.$task);
        $this->assertTrue(SharedHosting::dueEvery($task, 3600), 'belum pernah jalan');
        SharedHosting::markRan($task);
        $this->assertFalse(SharedHosting::dueEvery($task, 3600));
        $this->travel(59)->minutes();
        $this->assertTrue(SharedHosting::dueEvery($task, 3600), 'tick terlambat tetap mengejar');

        $this->assertTrue(SharedHosting::dueSlot($task, '2026-10-20'));
        SharedHosting::markRan($task, '2026-10-20');
        $this->assertFalse(SharedHosting::dueSlot($task, '2026-10-20'));
        $this->assertTrue(SharedHosting::dueSlot($task, '2026-10-21'));
        Cache::store('file')->forget('rt05:shared:ran:'.$task);
        Cache::store('file')->forget('rt05:shared:slot:'.$task);
    }

    public function test_fcm_credentials_are_read_from_env_and_rejected_when_incomplete(): void
    {
        $this->assertNull(FcmGateway::credentials(['credentials_base64' => base64_encode('{"type":"authorized_user"}')]));
        $this->assertNull(FcmGateway::credentials(['credentials_base64' => 'bukan-base64!!']));
        $this->assertNull(FcmGateway::credentials([]));
        $ok = FcmGateway::credentials(['project_id' => 'override', 'credentials_base64' => base64_encode(json_encode([
            'type' => 'service_account', 'project_id' => 'rt05-uji', 'client_email' => 'a@b.c', 'private_key' => 'k',
        ]))]);
        $this->assertSame('override', $ok['project_id']);
    }
}
