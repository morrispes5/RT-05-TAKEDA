<?php

namespace Tests\Feature;

use App\Domain\Operations\OutboxDispatcher;
use App\Models\Agenda;
use App\Models\UsulanKegiatan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Usulan kegiatan: keputusan pengurus, dokumen privat, satu agenda resmi. */
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
            'nama' => 'Senam bersama',
            'deskripsi' => 'Senam rutin warga di lapangan.',
            'lokasi' => 'Lapangan RT',
            'mulai_at' => now()->addDays(3)->toIso8601String(),
            'selesai_at' => now()->addDays(3)->addHour()->toIso8601String(),
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pendukung.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    }

    public function test_only_an_approved_proposal_becomes_one_official_agenda(): void
    {
        $warga = $this->warga('71');
        $lain = $this->warga('72');
        $admin = $this->pengurus();
        $created = $this->as($warga)->post('/api/v1/activity-proposals', $this->payload([
            'dokumen' => [$this->pdf()],
        ]), ['Accept' => 'application/json'])->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame('diajukan', $created->json('data.status'));
        $this->assertNull($created->json('data.agenda_id'));
        $doc = $created->json('data.dokumen.0.id');

        $this->as($warga)->postJson("/api/v1/admin/activity-proposals/$id/status", [
            'status' => 'disetujui', 'expected_version' => 1,
        ])->assertForbidden();
        $this->as($lain)->getJson("/api/v1/activity-proposals/$id")->assertNotFound();
        $this->as($lain)->get("/api/v1/activity-proposals/$id/documents/$doc")->assertNotFound();
        $this->as($warga)->get("/api/v1/activity-proposals/$id/documents/$doc")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=dokumen-1.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->as($admin)->postJson("/api/v1/admin/activity-proposals/$id/schedule", ['expected_version' => 1])->assertStatus(422);
        $this->assertSame(0, Agenda::count());

        $this->as($admin)->postJson("/api/v1/admin/activity-proposals/$id/status", [
            'status' => 'perlu_revisi', 'catatan' => 'Tambahkan perkiraan peserta.', 'expected_version' => 1,
        ])->assertOk()->assertJsonPath('data.status', 'perlu_revisi');
        $this->as($warga)->patch('/api/v1/activity-proposals/'.$id, $this->payload([
            'deskripsi' => 'Senam rutin, perkiraan 20 warga.',
            'versi' => 2,
        ]))->assertOk()->assertJsonPath('data.status', 'diajukan');

        $this->as($admin)->postJson("/api/v1/admin/activity-proposals/$id/status", [
            'status' => 'ditolak', 'expected_version' => 3,
        ])->assertStatus(422);
        $this->as($admin)->postJson("/api/v1/admin/activity-proposals/$id/status", [
            'status' => 'disetujui', 'catatan' => 'Sesuai rencana RT.', 'expected_version' => 3,
        ])->assertOk()->assertJsonPath('data.status', 'disetujui');

        $scheduled = $this->as($admin)->postJson("/api/v1/admin/activity-proposals/$id/schedule", ['expected_version' => 4])->assertOk();
        $agendaId = $scheduled->json('data.agenda_id');
        $this->assertNotNull($agendaId);
        $this->as($admin)->postJson("/api/v1/admin/activity-proposals/$id/schedule", ['expected_version' => 5])->assertStatus(409);
        $this->assertSame(1, Agenda::count());
        $this->assertSame(1, UsulanKegiatan::count());

        $this->as($lain)->getJson('/api/v1/agendas/'.$agendaId)->assertOk()->assertJsonPath('data.nama', 'Senam bersama');
        $this->as($lain)->getJson('/api/v1/activity-proposals')->assertOk()->assertJsonCount(0, 'data');
        $this->as($admin)->putJson('/api/v1/admin/agendas/'.$agendaId, [
            'nama' => 'Senam bersama (resmi)', 'deskripsi' => 'Jadwal resmi.', 'lokasi' => 'Lapangan RT',
            'mulai_at' => now()->addDays(4)->toIso8601String(),
            'selesai_at' => now()->addDays(4)->addHour()->toIso8601String(),
        ])->assertOk();
        $this->assertSame('Senam bersama', UsulanKegiatan::find($id)->nama);
        $this->assertSame('Senam bersama (resmi)', Agenda::find($agendaId)->nama);

        OutboxDispatcher::dispatch();
        $this->as($warga)->getJson('/api/v1/notifications')->assertOk()->assertJsonFragment(['jenis' => 'usulan']);
        $this->as($lain)->getJson('/api/v1/notifications')->assertOk()->assertJsonFragment(['jenis' => 'agenda']);
        $this->assertTrue(
            collect($this->as($admin)->getJson("/api/v1/activity-proposals/$id/history")->json('data'))->contains(fn ($row) => $row['catatan'] === 'Sesuai rencana RT.')
        );
    }

    public function test_owner_appends_a_document_by_spoofing_patch(): void
    {
        $warga = $this->warga('74');
        $id = $this->as($warga)->post('/api/v1/activity-proposals', $this->payload(), ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        $this->as($warga)->post('/api/v1/activity-proposals/'.$id, $this->payload([
            '_method' => 'PATCH',
            'versi' => 1,
            'dokumen' => [$this->pdf()],
        ]), ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.versi', 2)
            ->assertJsonPath('data.dokumen.0.mime', 'application/pdf');
    }

    public function test_spoofed_document_is_rejected_and_pending_account_cannot_propose(): void
    {
        $warga = $this->warga('73');
        $this->as($warga)->post('/api/v1/activity-proposals', $this->payload([
            'dokumen' => [UploadedFile::fake()->createWithContent('berkas.pdf', '<html>bukan pdf</html>')],
        ]), ['Accept' => 'application/json'])->assertStatus(415);
        $this->assertSame(0, UsulanKegiatan::count());

        $pending = $this->pemohon('73', 'tambahan-usulan@contoh.test');
        $this->as($pending)->postJson('/api/v1/activity-proposals', $this->payload())->assertForbidden();
    }
}
