<?php

namespace Tests\Feature;

use App\Domain\Operations\OutboxDispatcher;
use App\Models\Agenda;
use App\Models\UsulanKegiatan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * Usulan kegiatan (docs/USULAN_KEGIATAN.md): form tervalidasi, dokumen privat PDF/TXT/gambar,
 * keputusan pengurus, persetujuan langsung menjadi satu agenda resmi dan semua warga diberi tahu.
 */
class ActivityProposalTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'jenis' => 'olahraga',
            'nama' => 'Badminton bersama di lapangan RT 05',
            'deskripsi' => 'Badminton warga, terbuka untuk semua umur. Susunan acara terlampir.',
            'lokasi' => 'Lapangan RT 05',
            'mulai_at' => now()->addDays(7)->setTime(9, 0)->toIso8601String(),
            'selesai_at' => now()->addDays(7)->setTime(11, 0)->toIso8601String(),
            'perkiraan_peserta' => 20,
            'kebutuhan' => 'Izin memakai lapangan dan pengumuman ke warga.',
            'pernyataan' => '1',
        ];
    }

    private function propose($akun, array $over = [])
    {
        return $this->as($akun)->post('/api/v1/activity-proposals', $this->payload($over), ['Accept' => 'application/json']);
    }

    private function decide(string $id, string $status, int $version, ?string $note = null)
    {
        return $this->postJson("/api/v1/admin/activity-proposals/$id/status", ['status' => $status, 'expected_version' => $version, 'catatan' => $note]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('susunan-acara.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    }

    private function txt(string $content = "Susunan acara:\n1. Pemanasan\n2. Pertandingan\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('catatan.txt', $content);
    }

    public function test_approval_creates_exactly_one_agenda_and_notifies_every_active_resident(): void
    {
        $sri = $this->warga('71');
        $lain = $this->warga('72');
        $nonaktif = $this->warga('70');
        $nonaktif->forceFill(['status' => 'nonaktif'])->save();
        $admin = $this->pengurus();
        $created = $this->propose($sri, ['dokumen' => [$this->pdf(), $this->txt()]])->assertCreated()
            ->assertJsonPath('data.status', 'diajukan')->assertJsonPath('data.agenda_id', null)
            ->assertJsonPath('data.jenis', 'olahraga')->assertJsonPath('data.perkiraan_peserta', 20)
            ->assertJsonPath('data.pengusul.id', $sri->id)->assertJsonPath('data.milik_saya', true);
        $this->assertNotNull($created->json('data.pengusul.rumah'), 'kode rumah pengusul untuk pengurus');
        $this->assertSame([], OpenApi::errors($created->json(), 'ActivityProposalEnvelope'));
        $id = $created->json('data.id');
        $this->assertNotNull(UsulanKegiatan::find($id)->pernyataan_at);
        $this->assertSame(0, Agenda::count(), 'usulan bukan agenda sebelum disetujui');
        $this->as($lain)->getJson('/api/v1/agendas')->assertJsonCount(0, 'data');

        $this->as($admin);
        $this->decide($id, 'ditinjau', 1)->assertOk();
        $approved = $this->decide($id, 'disetujui', 2, 'Silakan, lapangan kosong.')->assertOk()
            ->assertJsonPath('data.status', 'disetujui');
        $agendaId = $approved->json('data.agenda_id');
        $this->assertNotNull($agendaId);
        $this->assertSame(1, Agenda::count());
        $agenda = Agenda::find($agendaId);
        $this->assertSame(['Badminton bersama di lapangan RT 05', 'Lapangan RT 05'], [$agenda->nama, $agenda->lokasi]);
        $this->assertTrue(DB::table('audit_logs')->where('entity_id', $agendaId)->where('action', 'agenda.dibuat')->exists());

        // Persetujuan ulang atau keputusan lain setelah menjadi agenda ditolak; tidak ada agenda kedua.
        $this->decide($id, 'disetujui', 3)->assertStatus(409)->assertJsonPath('error.code', 'PROPOSAL_SCHEDULED');
        $this->decide($id, 'ditolak', 3, 'x')->assertStatus(409);
        $this->assertSame(1, Agenda::count());

        OutboxDispatcher::dispatch();
        foreach ([$sri, $lain, $admin] as $akun) {
            $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $akun->id)->where('agenda_id', $agendaId)->count(), 'satu notifikasi agenda per warga aktif');
        }
        $this->assertSame(0, DB::table('notifikasi')->where('penerima_id', $nonaktif->id)->where('agenda_id', $agendaId)->count());
        $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $sri->id)->where('usulan_kegiatan_id', $id)->where('judul', 'Usulan kegiatan disetujui')->count());
        $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $admin->id)->where('usulan_kegiatan_id', $id)->where('judul', 'Usulan kegiatan baru')->count());
        $this->as($lain)->getJson('/api/v1/agendas')->assertJsonPath('data.0.id', $agendaId);
        $this->assertSame(0, DB::table('notifikasi')->where('penerima_id', $lain->id)->whereNotNull('usulan_kegiatan_id')->count(), 'warga lain tidak menerima notifikasi usulan');
    }

    public function test_revision_loop_requires_a_note_returns_to_submitted_and_tells_pengurus(): void
    {
        $sri = $this->warga('73');
        $admin = $this->pengurus();
        $id = $this->propose($sri)->json('data.id');
        $this->as($admin);
        $this->decide($id, 'perlu_revisi', 1)->assertStatus(422)->assertJsonPath('error.code', 'DECISION_NOTE_REQUIRED');
        $this->decide($id, 'perlu_revisi', 1, 'Tambahkan perkiraan peserta dan jam selesai.')->assertOk()->assertJsonPath('data.status', 'perlu_revisi');

        $this->as($sri)->patchJson("/api/v1/activity-proposals/$id", $this->payload(['versi' => 1, 'perkiraan_peserta' => 30]))->assertStatus(409);
        $this->patchJson("/api/v1/activity-proposals/$id", $this->payload(['versi' => 2, 'perkiraan_peserta' => 30]))
            ->assertOk()->assertJsonPath('data.status', 'diajukan')->assertJsonPath('data.perkiraan_peserta', 30);

        OutboxDispatcher::dispatch();
        $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $admin->id)->where('usulan_kegiatan_id', $id)->where('judul', 'Usulan kegiatan diperbaiki')->count());
        $this->assertSame(1, DB::table('notifikasi')->where('penerima_id', $sri->id)->where('usulan_kegiatan_id', $id)->where('isi', 'like', '%Perlu revisi%')->count());

        $this->as($admin);
        $this->decide($id, 'ditolak', 3)->assertStatus(422);
        $this->decide($id, 'ditolak', 3, 'Bentrok dengan kerja bakti.')->assertOk()->assertJsonPath('data.status', 'ditolak');
        $this->decide($id, 'disetujui', 4)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_STATUS_TRANSITION');
        $this->as($sri)->patchJson("/api/v1/activity-proposals/$id", $this->payload(['versi' => 4]))->assertForbidden()->assertJsonPath('error.code', 'PROPOSAL_LOCKED');
        $this->assertSame(0, Agenda::count());
    }

    public function test_form_rules_are_enforced_by_the_server_without_partial_writes(): void
    {
        $sri = $this->warga('74');
        $this->as($sri);
        $cases = [
            'jenis' => ['jenis' => 'pesta'],
            'nama' => ['nama' => 'Ab'],
            'deskripsi' => ['deskripsi' => 'terlalu pendek'],
            'lokasi' => ['lokasi' => 'X'],
            'mulai kurang 24 jam' => ['mulai_at' => now()->addHours(10)->toIso8601String(), 'selesai_at' => now()->addHours(12)->toIso8601String()],
            'selesai sebelum mulai' => ['selesai_at' => now()->addDays(7)->setTime(8, 0)->toIso8601String()],
            'lebih 12 jam' => ['selesai_at' => now()->addDays(8)->setTime(9, 0)->toIso8601String()],
            'tanpa selesai' => ['selesai_at' => null],
            'peserta' => ['perkiraan_peserta' => 5000],
            'pernyataan' => ['pernyataan' => '0'],
        ];
        foreach ($cases as $label => $over) {
            $res = $this->post('/api/v1/activity-proposals', array_merge($this->payload(), $over), ['Accept' => 'application/json']);
            $this->assertSame(422, $res->status(), $label);
        }
        $msg = $this->post('/api/v1/activity-proposals', array_merge($this->payload(), ['pernyataan' => '0']), ['Accept' => 'application/json'])->json();
        $this->assertStringContainsString('pernyataan', json_encode($msg));
        $this->assertSame(0, UsulanKegiatan::count());
        $this->assertSame(0, DB::table('riwayat_usulan_kegiatan')->count());
    }

    public function test_documents_accept_pdf_txt_and_images_reject_spoofs_and_stay_private(): void
    {
        $sri = $this->warga('75');
        $lain = $this->warga('76');
        $admin = $this->pengurus();
        $created = $this->propose($sri, ['dokumen' => [$this->pdf(), $this->txt(), UploadedFile::fake()->image('poster.png', 300, 200)]])->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame(['application/pdf', 'text/plain', 'image/jpeg'], collect($created->json('data.dokumen'))->pluck('mime')->all());
        $txtDoc = $created->json('data.dokumen.1.id');

        $this->as($sri)->get("/api/v1/activity-proposals/$id/documents/$txtDoc")->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=dokumen-2.txt')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->as($admin)->get("/api/v1/activity-proposals/$id/documents/$txtDoc")->assertOk();
        $this->as($lain)->get("/api/v1/activity-proposals/$id/documents/$txtDoc")->assertNotFound();
        $this->as($lain)->getJson("/api/v1/activity-proposals/$id")->assertNotFound();
        foreach (Storage::disk('local')->allFiles('usulan') as $file) {
            $this->assertStringNotContainsString('susunan', $file);
            $this->assertStringNotContainsString('catatan', $file);
        }

        $this->as($sri);
        $spoofs = [
            'html sebagai pdf' => UploadedFile::fake()->createWithContent('berkas.pdf', '<html>bukan pdf</html>'),
            'biner sebagai txt' => UploadedFile::fake()->createWithContent('berkas.txt', "teks\x00biner"),
            'docx' => UploadedFile::fake()->createWithContent('berkas.docx', "PK\x03\x04[Content_Types].xml"),
        ];
        foreach ($spoofs as $label => $file) {
            $this->assertSame(415, $this->propose($sri, ['dokumen' => [$file]])->status(), $label);
        }
        $this->assertSame(413, $this->propose($sri, ['dokumen' => [$this->txt(str_repeat('a', 1024 * 1024 + 10))]])->status());
        $this->assertSame(422, $this->propose($sri, ['dokumen' => [$this->pdf(), $this->pdf(), $this->pdf(), $this->pdf()]])->status());
        $this->assertSame(1, UsulanKegiatan::count(), 'tanpa usulan parsial');
    }

    public function test_access_boundaries_for_other_residents_pending_and_inactive_accounts(): void
    {
        $sri = $this->warga('77');
        $lain = $this->warga('78');
        $id = $this->propose($sri)->json('data.id');
        $this->as($lain)->getJson('/api/v1/activity-proposals')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/activity-proposals/$id")->assertNotFound();
        $this->getJson("/api/v1/activity-proposals/$id/history")->assertNotFound();
        $this->patchJson("/api/v1/activity-proposals/$id", $this->payload(['versi' => 1]))->assertNotFound();
        $this->postJson("/api/v1/admin/activity-proposals/$id/status", ['status' => 'disetujui', 'expected_version' => 1])->assertForbidden();

        $pending = $this->pemohon('77', 'tambahan-usulan@contoh.test');
        $this->as($pending)->postJson('/api/v1/activity-proposals', $this->payload())->assertForbidden();
        $this->getJson('/api/v1/activity-proposals')->assertForbidden();
        $sri->forceFill(['status' => 'nonaktif'])->save();
        $this->as($sri)->getJson("/api/v1/activity-proposals/$id")->assertForbidden();
        $this->as($lain)->getJson('/api/v1/activity-proposals?status=dihapus')->assertStatus(422);
    }

    public function test_past_dates_cannot_be_approved_and_overlaps_are_shown_to_pengurus_only(): void
    {
        $sri = $this->warga('79');
        $admin = $this->pengurus();
        $id = $this->propose($sri)->json('data.id');
        $mulai = UsulanKegiatan::find($id)->mulai_at;
        Agenda::create(['dibuat_oleh_id' => $admin->id, 'nama' => 'Kerja bakti', 'deskripsi' => 'd', 'lokasi' => 'Lapangan RT 05',
            'mulai_at' => $mulai->copy()->addMinutes(30), 'selesai_at' => $mulai->copy()->addHours(3)]);
        Agenda::create(['dibuat_oleh_id' => $admin->id, 'nama' => 'Rapat malam', 'deskripsi' => 'd', 'lokasi' => 'Balai',
            'mulai_at' => $mulai->copy()->addHours(10)]);

        $shown = $this->as($admin)->getJson("/api/v1/activity-proposals/$id")->assertOk();
        $this->assertSame(['Kerja bakti'], collect($shown->json('data.agenda_bentrok'))->pluck('nama')->all());
        $this->assertArrayNotHasKey('agenda_bentrok', $this->as($sri)->getJson("/api/v1/activity-proposals/$id")->json('data'));

        UsulanKegiatan::whereKey($id)->update(['mulai_at' => now()->subHour(), 'selesai_at' => now()->addHour()]);
        $this->as($admin);
        $this->decide($id, 'disetujui', 1)->assertStatus(422)->assertJsonPath('error.code', 'PROPOSAL_DATE_PASSED');
        $this->assertSame('diajukan', UsulanKegiatan::find($id)->status);
        $this->assertSame(2, Agenda::count());
    }

    public function test_signed_download_link_is_short_lived_and_only_issued_to_entitled_accounts(): void
    {
        $sri = $this->warga('81');
        $lain = $this->warga('82');
        $admin = $this->pengurus();
        $created = $this->propose($sri, ['dokumen' => [$this->txt("Rundown badminton\n")]])->assertCreated();
        [$id, $doc] = [$created->json('data.id'), $created->json('data.dokumen.0.id')];

        $this->as($lain)->postJson("/api/v1/activity-proposals/$id/documents/$doc/link")->assertNotFound();
        $link = $this->as($admin)->postJson("/api/v1/activity-proposals/$id/documents/$doc/link")->assertOk();
        $url = $link->json('data.url');
        $this->assertStringStartsWith('/api/v1/activity-proposals/documents/'.$doc.'/download?', $url);
        $this->assertStringNotContainsString($sri->id, $url);

        // Tanpa token: tanda tangan cukup; diubah atau kedaluwarsa ditolak.
        app('auth')->forgetGuards();
        $this->get($url)->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=dokumen-1.txt')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->get('/api/v1/activity-proposals/documents/'.$doc.'/download')->assertForbidden();
        $this->travel(6)->minutes();
        $this->getJson($url)->assertForbidden()->assertJsonPath('error.code', 'INVALID_SIGNATURE');
    }

    public function test_owner_appends_a_document_by_spoofing_patch_while_submitted(): void
    {
        $sri = $this->warga('80');
        $id = $this->propose($sri)->json('data.id');

        $this->as($sri)->post('/api/v1/activity-proposals/'.$id, $this->payload([
            '_method' => 'PATCH',
            'versi' => 1,
            'dokumen' => [$this->pdf()],
        ]), ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.versi', 2)
            ->assertJsonPath('data.dokumen.0.mime', 'application/pdf');
    }
}
