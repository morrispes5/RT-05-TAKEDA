<?php

namespace App\Http\Controllers;

use App\Domain\Content\Contents;
use App\Domain\Content\Media;
use App\Http\Responses\Api;
use App\Models\Konten;
use App\Models\KontenMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Konten publik M13 (FR: profil, fasilitas, artikel, album). Publik: hanya snapshot terbit, tanpa
 * akun. Pengurus: draf, terbit, arsip dengan versi. `{type}` dibatasi articles|albums|facilities.
 */
class ContentController extends Controller
{
    /** Jenis konten dari `defaults('type')` route; parameter URI (slug/id) dioper terpisah. */
    private function type(Request $request): string
    {
        return (string) ($request->route()?->defaults['type'] ?? abort(404));
    }

    private function jenis(string $type): string
    {
        return Contents::TYPES[$type] ?? abort(404);
    }

    private function find(string $type, string $id): Konten
    {
        return Konten::where('jenis', $this->jenis($type))->findOrFail($id);
    }

    // ───────────────────────────── Publik (tanpa akun)

    public function publicIndex(Request $request): JsonResponse
    {
        $type = $this->type($request);
        $q = Konten::where('jenis', $this->jenis($type))->where('status', 'terbit')
            ->orderByRaw('CASE WHEN urutan > 0 THEN urutan END NULLS LAST')->orderByDesc('published_at')->orderBy('slug_terbit');

        return $this->cached(Api::paginate($request, $q, fn (Konten $k) => Contents::publicView($k)));
    }

    public function publicShow(Request $request, string $slug): JsonResponse
    {
        $type = $this->type($request);
        $jenis = $this->jenis($type);
        $k = Konten::where('jenis', $jenis)->where('status', 'terbit')->where('slug_terbit', $slug)->first();
        if (! $k) {
            // Slug lama dari konten yang masih terbit → redirect permanen ke slug baru.
            $moved = Konten::where('status', 'terbit')->whereKey(
                DB::table('konten_slug_lama')->where('jenis', $jenis)->where('slug', $slug)->value('konten_id')
            )->first();
            abort_unless($moved, 404);

            return Api::data(['redirect_slug' => $moved->slug_terbit], 301)
                ->header('Location', "/api/v1/public/$type/{$moved->slug_terbit}")->header('Cache-Control', 'public, max-age=300');
        }

        return $this->cached(Api::data(Contents::publicView($k)), $k);
    }

    public function publicProfile(): JsonResponse
    {
        $k = Konten::where('jenis', 'profil')->where('status', 'terbit')->firstOrFail();

        return $this->cached(Api::data(Contents::publicView($k)), $k);
    }

    public function publicMedia(string $id): Response
    {
        $m = KontenMedia::findOrFail($id);
        abort_unless(Media::isPublic($m), 404);

        return Storage::disk('local')->response($m->storage_key, $m->id.'.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function cached(JsonResponse $r, ?Konten $k = null): JsonResponse
    {
        $r->header('Cache-Control', 'public, max-age=60');
        if ($k) {
            $r->setEtag(sha1($k->id.':'.$k->versi_terbit), true);
        }

        return $r;
    }

    // ───────────────────────────── Pengurus

    public function index(Request $request): JsonResponse
    {
        $type = $this->type($request);
        $data = $request->validate(['status' => ['nullable', 'in:draf,terbit,arsip']]);
        $q = Konten::where('jenis', $this->jenis($type))
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'arsip'))
            ->orderByRaw('COALESCE(updated_at, created_at) DESC');

        return Api::paginate($request, $q, fn (Konten $k) => Contents::adminView($k));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $type = $this->type($request);

        return Api::data(Contents::adminView($this->find($type, $id)));
    }

    private function body(Request $request): array
    {
        return $request->validate([
            'slug' => ['required', 'string', 'max:120'],
            'draf' => ['required', 'array'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $type = $this->type($request);
        $data = $this->body($request);

        return Api::data(Contents::adminView(Contents::create($request->user(), $this->jenis($type), $data['slug'], $data['draf'])), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $type = $this->type($request);
        $data = $this->body($request) + $request->validate(['versi' => ['required', 'integer', 'min:1']]);

        return Api::data(Contents::adminView(Contents::update($request->user(), $this->find($type, $id), $data['versi'], $data['slug'], $data['draf'])));
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $type = $this->type($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1']]);

        return Api::data(Contents::adminView(Contents::publish($request->user(), $this->find($type, $id), $data['versi'])));
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $type = $this->type($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1']]);

        return Api::data(Contents::adminView(Contents::archive($request->user(), $this->find($type, $id), $data['versi'])));
    }

    public function restore(Request $request, string $id): JsonResponse
    {
        $type = $this->type($request);
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1']]);

        return Api::data(Contents::adminView(Contents::restore($request->user(), $this->find($type, $id), $data['versi'])));
    }

    public function profile(): JsonResponse
    {
        return Api::data(Contents::adminView(Konten::where('jenis', 'profil')->firstOrFail()));
    }

    /** PUT /admin/site-profile — simpan dan terbitkan fakta profil dalam satu langkah (versi wajib). */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate(['versi' => ['required', 'integer', 'min:1'], 'draf' => ['required', 'array']]);
        $k = Konten::where('jenis', 'profil')->firstOrFail();
        $k = DB::transaction(function () use ($request, $k, $data) {
            $saved = Contents::update($request->user(), $k, $data['versi'], 'profil', $data['draf']);

            return Contents::publish($request->user(), $saved, $saved->versi);
        });

        return Api::data(Contents::adminView($k));
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate(['foto' => ['required', 'file']]);
        $m = Media::upload($request->user(), $request->file('foto'));

        return Api::data(Media::view($m) + ['status' => $m->status, 'pratinjau_url' => '/api/v1/admin/content-media/'.$m->id.'/file'], 201);
    }

    /** Pratinjau draf untuk pengurus (belum tentu publik). Foto statis website → redirect ke path /images/…. */
    public function mediaFile(string $id): Response
    {
        $m = KontenMedia::findOrFail($id);
        if ($m->sumber === 'statis') {
            return redirect($m->path_statis, 302, ['Cache-Control' => 'private, no-store']);
        }

        return Storage::disk('local')->response($m->storage_key, $m->id.'.jpg', ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }
}
