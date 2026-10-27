<?php

namespace App\Domain\Community;

use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\KejadianDarurat;
use App\Models\OutboxEvent;
use App\Support\Audit;
use App\Support\Outbox;
use App\Support\Privacy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** M19: one lock order for creation, disabling and assessment; no external I/O in these transactions. */
final class Emergencies
{
    public const TERMS = '2026-10-27';

    public const KINDS = ['kebakaran', 'kemalingan', 'penyerangan', 'lainnya'];

    public static function lock(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(190027)');
    }

    public static function setting(): object
    {
        return DB::table('system_settings')->where('key', 'darurat.fitur')->firstOrFail();
    }

    public static function audienceReady(): bool
    {
        return config('panic.audience') === 'all' || config('panic.recipient_ids', []) !== [];
    }

    public static function enabled(): bool
    {
        return config('panic.enabled') && self::audienceReady() && (json_decode(self::setting()->value, true)['aktif'] ?? false);
    }

    public static function authorize(Akun $actor, bool $admin = false): Akun
    {
        $q = Akun::whereKey($actor->id);
        if (DB::transactionLevel() > 0) {
            // Compatible with the idempotency FK's KEY SHARE; serialises concurrent account status changes.
            $q->lock('for no key update');
        }
        $actor = $q->firstOrFail();
        if (! $actor->isAktif() || ($admin && ! $actor->isPengurus())) {
            throw new AuthorizationException('Akses darurat tidak diizinkan.');
        }
        if (! config('panic.enabled')) {
            throw new DomainConflict('PANIC_UNAVAILABLE', 'Tombol panik belum tersedia di server ini.', 403);
        }
        if (! Privacy::atLeast($actor->privasi_versi, Privacy::PANIC_VERSION)) {
            throw new DomainConflict('PRIVACY_CONSENT_REQUIRED', 'Baca dan setujui Kebijakan Privasi terbaru terlebih dahulu.', 403);
        }

        return $actor;
    }

    /** Penyalahgunaan yang sudah dibatalkan pengurus (koreksi_penyalahgunaan_darurat) tidak dihitung. */
    public static function misuseCount(string $actor): int
    {
        return KejadianDarurat::where('pelapor_id', $actor)->where('penyalahgunaan', true)
            ->whereNotExists(fn ($q) => $q->from('koreksi_penyalahgunaan_darurat')->whereColumn('kejadian_darurat_id', 'kejadian_darurat.id'))
            ->count();
    }

    public static function create(Akun $actor, string $kind): KejadianDarurat
    {
        return DB::transaction(function () use ($actor, $kind) {
            self::lock();
            $actor = self::authorize($actor);
            if (! in_array($kind, self::KINDS, true)) {
                throw new DomainConflict('PANIC_INVALID_KIND', 'Jenis darurat tidak valid.', 422);
            }
            if (! self::enabled()) {
                throw new DomainConflict('PANIC_DISABLED', 'Tombol panik sedang dinonaktifkan pengurus.', 403);
            }
            if (self::misuseCount($actor->id) >= 3) {
                throw new DomainConflict('PANIC_ACCOUNT_DISABLED', 'Tombol panik akun ini dinonaktifkan setelah tiga penyalahgunaan. Hubungi pengurus.', 403);
            }
            $active = KejadianDarurat::where('pelapor_id', $actor->id)->where('status', 'aktif')->first();
            if ($active) {
                if ($active->jenis !== $kind) {
                    throw new DomainConflict('PANIC_ALREADY_ACTIVE', 'Laporan darurat Anda sebelumnya masih aktif. Buka laporan itu.');
                }

                return $active;
            }
            if (KejadianDarurat::where('pelapor_id', $actor->id)->where('created_at', '>=', now()->subHour())->count() >= config('panic.account_hourly_limit')
                || KejadianDarurat::where('created_at', '>=', now()->subMinute())->count() >= config('panic.global_minute_limit')) {
                throw new DomainConflict('PANIC_RATE_LIMITED', 'Batas pengiriman darurat tercapai. Hubungi bantuan darurat langsung.', 429);
            }
            $house = $actor->rumah();
            if (! $house || $house->archived_at) {
                throw new DomainConflict('PANIC_HOUSE_REQUIRED', 'Rumah aktif belum terhubung. Hubungi pengurus.', 422);
            }
            $e = KejadianDarurat::create(['pelapor_id' => $actor->id, 'rumah_id' => $house->id, 'jenis' => $kind]);
            Audit::record($actor, 'darurat.dibuat', 'kejadian_darurat', $e->id, ['jenis' => $kind]);
            Outbox::record('darurat.dibuat', 'kejadian_darurat', $e->id);

            return $e->refresh();
        });
    }

    public static function resolve(Akun $actor, string $id, int $version, string $status, bool $misuse, string $reason): KejadianDarurat
    {
        return DB::transaction(function () use ($actor, $id, $version, $status, $misuse, $reason) {
            self::lock();
            $actor = self::authorize($actor, true);
            if (! in_array($status, ['selesai', 'palsu'], true) || ($misuse && $status !== 'palsu') || trim($reason) === '') {
                throw new DomainConflict('PANIC_INVALID_RESOLUTION', 'Penanganan dan alasan harus diisi dengan benar.', 422);
            }
            $e = KejadianDarurat::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($e->status !== 'aktif' || $e->versi !== $version) {
                throw new DomainConflict('VERSION_CONFLICT', 'Laporan sudah ditangani pengurus lain. Muat ulang sebelum melanjutkan.');
            }
            $e->forceFill(['status' => $status, 'penyalahgunaan' => $misuse, 'catatan_pengurus' => trim($reason),
                'ditangani_oleh_id' => $actor->id, 'selesai_at' => now(), 'versi' => $version + 1])->save();
            Audit::record($actor, 'darurat.ditangani', 'kejadian_darurat', $id, ['status' => $status, 'penyalahgunaan' => $misuse]);
            // No identity or operator notes in notifications, outbox, or audit diffs.
            Outbox::record('darurat.ditangani', 'kejadian_darurat', $id);

            return $e;
        });
    }

    /** Audit M19: batalkan penanda penyalahgunaan yang keliru. Riwayat kejadian tidak diubah; koreksi append-only. */
    public static function correctMisuse(Akun $actor, string $id, string $reason): KejadianDarurat
    {
        return DB::transaction(function () use ($actor, $id, $reason) {
            self::lock();
            $actor = self::authorize($actor, true);
            if (mb_strlen(trim($reason)) < 5 || mb_strlen(trim($reason)) > 300) {
                throw new DomainConflict('PANIC_INVALID_CORRECTION', 'Alasan pembatalan wajib 5–300 karakter.', 422);
            }
            $e = KejadianDarurat::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($e->status !== 'palsu' || ! $e->penyalahgunaan) {
                throw new DomainConflict('PANIC_NOT_MISUSE', 'Laporan ini tidak ditandai penyalahgunaan.', 422);
            }
            if (self::misuseCorrected($id)) {
                throw new DomainConflict('PANIC_MISUSE_ALREADY_CORRECTED', 'Penanda penyalahgunaan laporan ini sudah dibatalkan.');
            }
            DB::table('koreksi_penyalahgunaan_darurat')->insert(['kejadian_darurat_id' => $id, 'dicatat_oleh_id' => $actor->id, 'alasan' => trim($reason)]);
            Audit::record($actor, 'darurat.penyalahgunaan_dibatalkan', 'kejadian_darurat', $id, []);
            Outbox::record('darurat.penyalahgunaan_dibatalkan', 'kejadian_darurat', $id);

            return $e;
        });
    }

    public static function misuseCorrected(string $id): bool
    {
        return DB::table('koreksi_penyalahgunaan_darurat')->where('kejadian_darurat_id', $id)->exists();
    }

    public static function configure(Akun $actor, int $version, bool $active): object
    {
        return DB::transaction(function () use ($actor, $version, $active) {
            self::lock();
            $actor = self::authorize($actor, true);
            $setting = self::setting();
            if ($setting->version !== $version) {
                throw new DomainConflict('VERSION_CONFLICT', 'Pengaturan berubah. Muat ulang terlebih dahulu.');
            }
            if ($active && ! self::audienceReady()) {
                throw new DomainConflict('PANIC_AUDIENCE_NOT_CONFIGURED', 'Penerima uji belum dikonfigurasi. Hubungi pengelola server.', 422);
            }
            DB::table('system_settings')->where('key', 'darurat.fitur')->update(['value' => json_encode(['aktif' => $active]),
                'version' => $version + 1, 'updated_by' => $actor->id, 'updated_at' => now()]);
            Audit::record($actor, 'darurat.fitur_diubah', 'system_settings', null, ['aktif' => $active]);

            return self::setting();
        });
    }

    public static function alarmEligible(KejadianDarurat $e): bool
    {
        return self::enabled() && $e->status === 'aktif'
            && $e->created_at->gte(now()->subMinutes(config('panic.alarm_age_minutes')));
    }

    public static function recipients(OutboxEvent $event): array
    {
        // Audit M19: minimal versi persetujuan (bukan sama persis) dan pelapor tidak menerima alarm laporannya sendiri.
        $q = Akun::where('status', 'aktif')->where('privasi_versi', '>=', Privacy::PANIC_VERSION)->where('created_at', '<=', $event->created_at);
        $reporter = KejadianDarurat::whereKey($event->aggregate_id)->value('pelapor_id');
        if ($reporter !== null) {
            $q->whereKeyNot($reporter);
        }
        if (config('panic.audience') !== 'all') {
            $q->whereIn('id', config('panic.recipient_ids', []));
        }

        return $q->pluck('id')->all();
    }

    public static function present(KejadianDarurat $e, bool $admin = false): array
    {
        $a = $e->pelapor;
        $h = $e->rumah;

        return ['id' => $e->id, 'jenis' => $e->jenis, 'status' => $e->status, 'versi' => $e->versi,
            'pelapor' => $a?->nama, 'rumah' => $h?->alamat(), 'created_at' => $e->created_at->toIso8601String(),
            'selesai_at' => $e->selesai_at?->toIso8601String(),
            // Residents receive no misuse count, operator identity, or free-form assessment notes.
            ...($admin ? ['penyalahgunaan' => $e->penyalahgunaan, 'catatan_pengurus' => $e->catatan_pengurus,
                'penyalahgunaan_dibatalkan' => $e->penyalahgunaan && self::misuseCorrected($e->id)] : [])];
    }

    /** Delivery to the provider is not proof of sound or receipt on a device. */
    public static function deliveryState(string $id): string
    {
        $event = OutboxEvent::where('type', 'darurat.dibuat')->where('aggregate_id', $id)->first();
        if (! $event) {
            return 'belum_diketahui';
        }
        if ($event->failed_at) {
            return 'gagal';
        }
        if (! $event->completed_at) {
            return 'antrean';
        }
        $n = DB::table('notifikasi')->where('event_id', $event->id)->selectRaw("count(*) AS total,
            count(*) FILTER (WHERE push_status IS NULL) AS pending,
            count(*) FILTER (WHERE push_status = 'gagal') AS failed,
            count(*) FILTER (WHERE push_status = 'terkirim') AS sent")->first();
        if ((int) $n->failed > 0) {
            return (int) $n->sent > 0 || (int) $n->pending > 0 ? 'sebagian_gagal' : 'gagal';
        }
        if ((int) $n->pending > 0) {
            return 'diproses';
        }

        return (int) $n->sent > 0 ? 'provider_menerima' : 'dilewati';
    }
}
