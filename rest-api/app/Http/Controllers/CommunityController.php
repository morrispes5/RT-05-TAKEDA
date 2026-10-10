<?php

namespace App\Http\Controllers;

use App\Domain\Community\Calendar;
use App\Http\Responses\Api;
use App\Http\Responses\Present;
use App\Models\Agenda;
use App\Models\Notifikasi;
use App\Models\Pengumuman;
use App\Models\PreferensiNotifikasi;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Agenda, pengumuman, dan inbox notifikasi (FR10, FR11, FR20). */
class CommunityController extends Controller
{
    public function agendas(Request $request): JsonResponse
    {
        $q = Agenda::query()->whereNull('archived_at')
            ->when($request->query('bulan'), function ($q, $bulan) {
                $start = Carbon::createFromFormat('!Y-m', $bulan, 'Asia/Jakarta')->startOfMonth();
                $q->whereBetween('mulai_at', [$start->copy()->utc(), $start->copy()->endOfMonth()->utc()]);
            }, fn ($q) => $q->where('mulai_at', '>=', now()->subDays(1)))
            ->orderBy('mulai_at');

        return Api::paginate($request, $q, fn (Agenda $a) => Present::agenda($a));
    }

    public function agenda(string $id): JsonResponse
    {
        return Api::data(Present::agenda(Agenda::whereNull('archived_at')->findOrFail($id)));
    }

    public function agendaCalendar(string $id): Response
    {
        $a = Agenda::whereNull('archived_at')->findOrFail($id);

        return response(Calendar::ics($a), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="agenda-rt05.ics"',
        ]);
    }

    private function agendaRules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:160'],
            'deskripsi' => ['required', 'string', 'max:5000'],
            'lokasi' => ['required', 'string', 'max:200'],
            'mulai_at' => ['required', 'date'],
            'selesai_at' => ['nullable', 'date', 'after_or_equal:mulai_at'],
        ];
    }

    public function storeAgenda(Request $request): JsonResponse
    {
        $data = $request->validate($this->agendaRules());
        $agenda = DB::transaction(function () use ($request, $data) {
            $a = Agenda::create($data + ['dibuat_oleh_id' => $request->user()->id]);
            Audit::record($request->user(), 'agenda.dibuat', 'agenda', $a->id);
            Outbox::record('agenda.dibuat', 'agenda', $a->id);

            return $a;
        });

        return Api::data(Present::agenda($agenda), 201);
    }

    public function updateAgenda(Request $request, string $id): JsonResponse
    {
        $agenda = Agenda::whereNull('archived_at')->findOrFail($id);
        $data = $request->validate($this->agendaRules());
        DB::transaction(function () use ($request, $agenda, $data) {
            $timeChanged = Carbon::parse($data['mulai_at'])->ne($agenda->mulai_at);
            $agenda->fill($data);
            if ($timeChanged) {
                $agenda->pengingat_terkirim_at = null; // pengingat H-1 dijadwalkan ulang untuk waktu baru.
            }
            $agenda->save();
            Audit::record($request->user(), 'agenda.diubah', 'agenda', $agenda->id);
        });

        return Api::data(Present::agenda($agenda->refresh()));
    }

    public function archiveAgenda(Request $request, string $id): JsonResponse
    {
        $agenda = Agenda::whereNull('archived_at')->findOrFail($id);
        DB::transaction(function () use ($request, $agenda) {
            $agenda->forceFill(['archived_at' => now()])->save();
            Audit::record($request->user(), 'agenda.diarsipkan', 'agenda', $agenda->id);
        });

        return Api::noContent();
    }

    public function announcements(Request $request): JsonResponse
    {
        $q = Pengumuman::query()->whereNull('archived_at')->whereNotNull('published_at')->orderByDesc('published_at');

        return Api::paginate($request, $q, fn (Pengumuman $p) => Present::pengumuman($p));
    }

    public function announcement(string $id): JsonResponse
    {
        return Api::data(Present::pengumuman(Pengumuman::whereNull('archived_at')->whereNotNull('published_at')->findOrFail($id)));
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $data = $request->validate(['judul' => ['required', 'string', 'max:160'], 'isi' => ['required', 'string', 'max:10000']]);
        $p = DB::transaction(function () use ($request, $data) {
            $p = Pengumuman::create($data + ['dibuat_oleh_id' => $request->user()->id, 'published_at' => now()]);
            Audit::record($request->user(), 'pengumuman.terbit', 'pengumuman', $p->id);
            Outbox::record('pengumuman.terbit', 'pengumuman', $p->id);

            return $p;
        });

        return Api::data(Present::pengumuman($p), 201);
    }

    public function updateAnnouncement(Request $request, string $id): JsonResponse
    {
        $p = Pengumuman::whereNull('archived_at')->findOrFail($id);
        $data = $request->validate(['judul' => ['required', 'string', 'max:160'], 'isi' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($request, $p, $data) {
            $p->fill($data)->save();
            Audit::record($request->user(), 'pengumuman.diubah', 'pengumuman', $p->id);
        });

        return Api::data(Present::pengumuman($p->refresh()));
    }

    public function archiveAnnouncement(Request $request, string $id): JsonResponse
    {
        $p = Pengumuman::whereNull('archived_at')->findOrFail($id);
        DB::transaction(function () use ($request, $p) {
            $p->forceFill(['archived_at' => now()])->save();
            Audit::record($request->user(), 'pengumuman.diarsipkan', 'pengumuman', $p->id);
        });

        return Api::noContent();
    }

    public function notifications(Request $request): JsonResponse
    {
        $q = Notifikasi::where('penerima_id', $request->user()->id)
            ->when($request->boolean('belum_dibaca'), fn ($q) => $q->whereNull('dibaca_at'))
            ->orderByDesc('created_at');
        $unread = Notifikasi::where('penerima_id', $request->user()->id)->whereNull('dibaca_at')->count();

        $response = Api::paginate($request, $q, fn (Notifikasi $n) => Present::notifikasi($n));
        $body = $response->getData(true);
        $body['meta']['belum_dibaca'] = $unread;

        return $response->setData($body);
    }

    public function readNotification(Request $request, string $id): JsonResponse
    {
        $n = Notifikasi::where('penerima_id', $request->user()->id)->findOrFail($id);
        if (! $n->dibaca_at) {
            $n->forceFill(['dibaca_at' => now()])->save();
        }

        return Api::data(Present::notifikasi($n));
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        $count = Notifikasi::where('penerima_id', $request->user()->id)->whereNull('dibaca_at')->update(['dibaca_at' => now()]);

        return Api::data(['ditandai' => $count]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $pref = PreferensiNotifikasi::firstOrCreate(['akun_id' => $request->user()->id]);

        return Api::data($pref->only(['pengumuman', 'agenda', 'pengaduan', 'aspirasi', 'iuran']));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate(array_fill_keys(['pengumuman', 'agenda', 'pengaduan', 'aspirasi', 'iuran'], ['sometimes', 'boolean']));
        $pref = PreferensiNotifikasi::firstOrCreate(['akun_id' => $request->user()->id]);
        $pref->fill($data)->save();

        return Api::data($pref->only(['pengumuman', 'agenda', 'pengaduan', 'aspirasi', 'iuran']));
    }
}
