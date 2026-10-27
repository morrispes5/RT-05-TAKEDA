<?php

namespace App\Domain\Community;

use App\Domain\Community\Push\PushGateway;
use App\Domain\Community\Push\PushMessage;
use App\Domain\Community\Push\PushResult;
use App\Models\Akun;
use App\Models\KejadianDarurat;
use App\Models\PerangkatPush;
use App\Support\Privacy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Push setelah inbox commit (M10). Inbox adalah sumber data final; push hanya pemberitahuan dan
 * boleh gagal tanpa mengubah event/notifikasi. Status per notifikasi: terkirim (minimal satu HP
 * menerima), dilewati (tanpa kredensial, preferensi mati, tanpa HP terdaftar, kedaluwarsa), atau
 * gagal (ditolak provider/percobaan habis). Baris dikunci FOR UPDATE SKIP LOCKED sehingga job ganda
 * tidak mengirim dua kali; duplikat dari provider tetap mungkin dan digabung lewat tag Android.
 */
final class PushDelivery
{
    /** Jenis tanpa kolom preferensi (status usulan, sistem) selalu di-push. */
    public const PREFERENCE = ['pengumuman' => 'pengumuman', 'agenda' => 'agenda', 'pengaduan' => 'pengaduan', 'aspirasi' => 'aspirasi', 'iuran' => 'iuran'];

    private const REFS = ['pengumuman_id', 'agenda_id', 'pengaduan_id', 'aspirasi_id', 'tagihan_id', 'usulan_kegiatan_id', 'kejadian_darurat_id'];

    /** @return 'done'|'retry'|'more' */
    public static function deliver(string $eventId, PushGateway $gateway, ?float $deadline = null): string
    {
        $deadline ??= microtime(true) + max(1, (int) config('rt05.push.run_seconds', 15));
        $pending = fn () => DB::table('notifikasi')->where('event_id', $eventId)->whereNull('push_status');

        if (! $gateway->configured()) {
            $pending()->update(['push_status' => 'dilewati', 'push_at' => now()]);

            return 'done';
        }
        $pending()->where('created_at', '<', now()->subHours(max(1, (int) config('rt05.push.max_age_hours', 24))))
            ->update(['push_status' => 'dilewati', 'push_at' => now()]);

        $seen = [];
        $retry = false;
        while (microtime(true) < $deadline) {
            $batch = DB::transaction(function () use ($pending, $seen, $gateway) {
                $rows = $pending()->when($seen !== [], fn ($q) => $q->whereNotIn('id', $seen))
                    ->orderBy('id')->limit(max(1, (int) config('rt05.push.batch', 50)))
                    ->lock('for update skip locked')->get();

                return $rows->isEmpty() ? null : self::batch($rows, $gateway);
            });
            if ($batch === null) {
                break;
            }
            $seen = array_merge($seen, $batch['ids']);
            $retry = $retry || $batch['retry'];
        }

        if ($pending()->when($seen !== [], fn ($q) => $q->whereNotIn('id', $seen))->exists()) {
            return 'more';
        }

        return $retry ? 'retry' : 'done';
    }

    /** Percobaan habis: sisa notifikasi event ini ditandai gagal (inbox tidak berubah). */
    public static function giveUp(string $eventId): void
    {
        DB::table('notifikasi')->where('event_id', $eventId)->whereNull('push_status')
            ->update(['push_status' => 'gagal', 'push_at' => now()]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array{ids: list<string>, retry: bool}
     */
    private static function batch(Collection $rows, PushGateway $gateway): array
    {
        $recipients = $rows->pluck('penerima_id')->unique()->values()->all();
        $prefs = DB::table('preferensi_notifikasi')->whereIn('akun_id', $recipients)->get()->keyBy('akun_id');
        $devices = self::devices($recipients);

        $status = [];
        $messages = [];
        $broken = [];
        foreach ($rows as $n) {
            $alarmTtl = 600;
            if ($n->jenis === 'darurat') {
                Emergencies::lock();
                $e = KejadianDarurat::find($n->kejadian_darurat_id);
                $a = Akun::find($n->penerima_id);
                if (! $e || ! Emergencies::alarmEligible($e) || ! $a?->isAktif() || $a->privasi_versi !== Privacy::PANIC_VERSION
                    || (config('panic.audience') !== 'all' && ! in_array($n->penerima_id, config('panic.recipient_ids', []), true))) {
                    $status[$n->id] = 'dilewati';

                    continue;
                }
                $alarmTtl = max(1, (int) floor(config('panic.alarm_age_minutes') * 60 - $e->created_at->diffInSeconds(now())));
            }
            $column = self::PREFERENCE[$n->jenis] ?? null;
            if ($column && isset($prefs[$n->penerima_id]) && ! $prefs[$n->penerima_id]->{$column}) {
                $status[$n->id] = 'dilewati';

                continue;
            }
            foreach ($devices->get($n->penerima_id, collect()) as $d) {
                try {
                    $token = $d->fcm_token; // terdekripsi; APP_KEY berganti → tidak dapat dipakai.
                } catch (Throwable) {
                    $broken[] = $d->id;

                    continue;
                }
                $messages[$n->id.'|'.$d->id] = new PushMessage($token, $n->judul, $n->isi, self::data($n), 'rt05-'.$n->id, $n->jenis === 'darurat', $alarmTtl);
            }
            if (! collect(array_keys($messages))->contains(fn ($k) => str_starts_with($k, $n->id.'|'))) {
                $status[$n->id] = 'dilewati';
            }
        }

        $results = $gateway->send($messages);
        $byNotif = [];
        foreach ($messages as $key => $_) {
            [$notifId, $deviceId] = explode('|', $key, 2);
            $result = $results[$key] ?? PushResult::Retry;
            $byNotif[$notifId][] = $result;
            if ($result === PushResult::InvalidToken) {
                $broken[] = $deviceId;
            }
        }
        $retry = false;
        foreach ($byNotif as $notifId => $list) {
            $status[$notifId] = match (true) {
                in_array(PushResult::Sent, $list, true) => 'terkirim',
                in_array(PushResult::Retry, $list, true) => null,
                in_array(PushResult::Failed, $list, true) => 'gagal',
                default => 'dilewati', // semua token tidak berlaku lagi
            };
            $retry = $retry || $status[$notifId] === null;
        }

        if ($broken !== []) {
            PerangkatPush::whereIn('id', array_unique($broken))->delete();
        }
        foreach (collect($status)->filter()->groupBy(fn ($s) => $s, true) as $value => $ids) {
            DB::table('notifikasi')->whereIn('id', $ids->keys()->all())->update(['push_status' => $value, 'push_at' => now()]);
        }

        return ['ids' => $rows->pluck('id')->all(), 'retry' => $retry];
    }

    /**
     * Perangkat yang masih punya sesi mobile berlaku dan akun aktif.
     *
     * @param  list<string>  $recipients
     * @return Collection<string, Collection<int, PerangkatPush>>
     */
    private static function devices(array $recipients): Collection
    {
        return PerangkatPush::query()->select('perangkat_push.*')
            ->join('akun', 'akun.id', '=', 'perangkat_push.akun_id')
            ->join('personal_access_tokens', 'personal_access_tokens.id', '=', 'perangkat_push.personal_access_token_id')
            ->whereIn('perangkat_push.akun_id', $recipients)
            ->where('akun.status', 'aktif')
            ->where(fn ($q) => $q->whereNull('personal_access_tokens.expires_at')->orWhere('personal_access_tokens.expires_at', '>', now()))
            ->get()->groupBy('akun_id');
    }

    /** @return array<string, string> ID dan jenis saja; tidak ada isi sensitif di data push. */
    private static function data(object $n): array
    {
        $data = ['notifikasi_id' => (string) $n->id, 'jenis' => (string) $n->jenis];
        foreach (self::REFS as $col) {
            if ($n->{$col} !== null) {
                $data['ref_type'] = str_replace('_id', '', $col);
                $data['ref_id'] = (string) $n->{$col};
                break;
            }
        }

        return $data;
    }
}
