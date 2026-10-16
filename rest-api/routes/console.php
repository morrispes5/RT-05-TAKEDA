<?php

use App\Domain\Community\Notifications;
use App\Domain\Finance\Dues;
use App\Domain\Identity\RegistrationToken;
use App\Domain\Operations\OutboxDispatcher;
use App\Domain\Services\Photo;
use App\Domain\Services\Reports;
use App\Jobs\ProcessComplaintPhoto;
use App\Jobs\SendPush;
use App\Models\Agenda;
use App\Models\FotoPengaduan;
use App\Models\TagihanIuran;
use App\Support\Heartbeat;
use App\Support\Outbox;
use App\Support\SharedHosting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| Command operasional RT05 TAKEDA dan jadwal Asia/Jakarta (docs/ASYNC_JOBS.md).
| Semua job idempotent: aman dijalankan ulang setelah downtime atau restart.
*/

Artisan::command('rt05:outbox-dispatch {--limit=100}', function () {
    $r = OutboxDispatcher::dispatch((int) $this->option('limit'));
    $this->info("Event diklaim: {$r['claimed']}, dikirim ke antrean: {$r['enqueued']}, gagal enqueue: {$r['failed']}");

    // Antrean tidak tersedia: event tetap pending di Postgres; exit non-zero agar terlihat di log scheduler.
    return $r['failed'] > 0 ? 1 : 0;
})->purpose('Klaim outbox_events lalu kirim ID event ke antrean notifications');

Artisan::command('rt05:tagihan-generate {periode? : YYYY-MM; kosong = catch-up sampai bulan berjalan}', function () {
    $p = $this->argument('periode');
    $n = $p ? Dues::generate(Dues::period($p)) : Dues::catchUp();
    $this->info("Tagihan baru: $n");
})->purpose('Buat tagihan iuran per rumah/periode (idempotent)');

Artisan::command('rt05:pengingat-agenda', function () {
    // Satu kali H-1: saat agenda masuk jendela 24 jam sebelum mulai. Catch-up aman: setelah
    // downtime, agenda yang belum dimulai tetap diingatkan; yang sudah dimulai tidak. Agenda yang
    // dibuat/diubah di dalam jendela tidak diingatkan lagi (notifikasi "Agenda baru/diubah" cukup).
    // Waktu berubah → update agenda mereset penanda, pengingat lama menjadi basi (Notifications).
    $sent = $covered = 0;
    $ids = Agenda::whereNull('archived_at')->whereNull('pengingat_terkirim_at')
        ->where('mulai_at', '>', now())->where('mulai_at', '<=', now()->addDay())->pluck('id');
    foreach ($ids as $id) {
        DB::transaction(function () use ($id, &$sent, &$covered) {
            $a = Agenda::whereKey($id)->whereNull('archived_at')->whereNull('pengingat_terkirim_at')->lockForUpdate()->first();
            if (! $a) {
                return; // diproses run lain (dedup)
            }
            // Warga sudah diberi tahu di dalam jendela H-1 (agenda dibuat atau waktunya diubah).
            $told = DB::table('outbox_events')->where('aggregate_id', $a->id)
                ->whereIn('type', ['agenda.dibuat', 'agenda.diubah'])
                ->where('created_at', '>=', $a->mulai_at->copy()->subDay())->exists();
            if ($told) {
                $covered++;
            } else {
                Outbox::record('agenda.pengingat', 'agenda', $a->id, ['mulai_at' => $a->mulai_at->toIso8601String()]);
                $sent++;
            }
            $a->forceFill(['pengingat_terkirim_at' => now()])->save();
        });
    }
    $this->info("Pengingat agenda: $sent, sudah diberitahukan saat dibuat/diubah: $covered");
})->purpose('Kirim pengingat agenda H-1 (catch-up dan dedup)');

Artisan::command('rt05:push-recover {--stale-minutes=10}', function () {
    // Job push hilang (enqueue gagal, worker mati): kirim ulang per event. Notifikasi yang sudah
    // terlalu tua ditandai dilewati oleh PushDelivery; inbox tidak berubah.
    $events = DB::table('notifikasi')->whereNull('push_status')
        ->where('created_at', '<', now()->subMinutes((int) $this->option('stale-minutes')))
        ->distinct()->limit(50)->pluck('event_id');
    foreach ($events as $eventId) {
        SendPush::queueFor($eventId);
    }
    $this->info('Event push dikirim ulang: '.$events->count());
})->purpose('Pulihkan push notifikasi yang belum terproses');

Artisan::command('rt05:siaran-sistem {kategori : kebijakan|pembaruan_aplikasi} {--judul=} {--isi=}', function () {
    $kategori = (string) $this->argument('kategori');
    $judul = trim((string) $this->option('judul'));
    $isi = trim((string) $this->option('isi'));
    if (! in_array($kategori, ['kebijakan', 'pembaruan_aplikasi'], true) || mb_strlen($judul) < 5 || mb_strlen($judul) > 150
        || mb_strlen($isi) < 10 || mb_strlen($isi) > 500) {
        $this->error('Kategori kebijakan|pembaruan_aplikasi, judul 5–150 karakter, isi 10–500 karakter.');

        return 1;
    }
    DB::transaction(fn () => Outbox::record('sistem.siaran', 'sistem', null, ['kategori' => $kategori, 'judul' => $judul, 'isi' => $isi]));
    $this->info('Siaran dicatat; inbox dan push dikirim worker.');
})->purpose('Siarkan informasi kebijakan atau pembaruan aplikasi ke semua akun aktif');

Artisan::command('rt05:pengingat-iuran', function () {
    $period = CarbonImmutable::now('Asia/Jakarta')->startOfMonth()->toDateString();
    $slot = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
    $count = 0;
    TagihanIuran::where('periode', $period)->where('status', 'belum_bayar')->each(function (TagihanIuran $t) use ($slot, &$count) {
        $penerima = Notifications::primaryAccountsOf($t->rumah_id)->all();
        if ($penerima === []) {
            return;
        }
        // Dedup per tagihan + slot tanggal: event yang sama tidak dibuat dua kali.
        $exists = DB::table('outbox_events')->where('type', 'iuran.pengingat')->where('aggregate_id', $t->id)
            ->whereRaw("safe_payload->>'slot' = ?", [$slot])->exists();
        if (! $exists) {
            Outbox::record('iuran.pengingat', 'tagihan_iuran', $t->id, ['tagihan_id' => $t->id, 'penerima' => $penerima, 'slot' => $slot]);
            $count++;
        }
    });
    $this->info("Pengingat iuran: $count");
})->purpose('Pengingat iuran bulan berjalan ke akun utama rumah');

Artisan::command('rt05:token-rotate {--if-due : Rotasi hanya bila tidak ada token aktif atau token berumur 7 hari}', function () {
    if ($this->option('if-due')) {
        $this->info(RegistrationToken::rotateIfDue() ? 'Token registrasi dirotasi (jatuh tempo).' : 'Token registrasi belum jatuh tempo.');

        return;
    }
    RegistrationToken::rotate(null, 'operator');
    $this->info('Token registrasi dirotasi.');
})->purpose('Rotasi token registrasi RT (nilai token tidak pernah dicetak)');

Artisan::command('rt05:media-recover {--stale-minutes=10}', function () {
    // Foto yang tertahan "diproses" (job hilang dari Redis/enqueue gagal): kirim ulang sampai
    // 5 percobaan, lalu tandai gagal. Retry aman karena job melewati foto yang sudah selesai.
    $requeued = $failed = 0;
    FotoPengaduan::where('status', 'diproses')
        ->whereRaw('coalesce(updated_at, created_at) < ?', [now()->subMinutes((int) $this->option('stale-minutes'))])
        ->each(function (FotoPengaduan $f) use (&$requeued, &$failed) {
            if ($f->percobaan >= 5) {
                ProcessComplaintPhoto::markFailed($f->id, 'PROCESSING_STALLED');
                $failed++;

                return;
            }
            FotoPengaduan::whereKey($f->id)->update(['updated_at' => now()]);
            Reports::enqueuePhoto($f->id);
            $requeued++;
        });

    // Berkas mentah >24 jam yang tidak dirujuk foto "diproses" (transaksi gagal/crash).
    $live = FotoPengaduan::where('status', 'diproses')->pluck('storage_key')->flip();
    $orphans = 0;
    $disk = Storage::disk(Photo::DISK);
    foreach ($disk->files(Photo::STAGING) as $key) {
        if (! isset($live[$key]) && $disk->lastModified($key) < now()->subDay()->getTimestamp()) {
            $disk->delete($key);
            $orphans++;
        }
    }
    $this->info("Foto dikirim ulang: $requeued, ditandai gagal: $failed, berkas yatim dihapus: $orphans");
})->purpose('Pulihkan antrean foto pengaduan yang tertahan dan hapus berkas mentah yatim');

if (SharedHosting::enabled()) {
    // Dipanggil shared-tick saja (cron ≤15 menit, bisa terlambat). Ekspresi tetap (hourly, dailyAt)
    // terlewat bila tick tidak tepat di menit itu, jadi tiap task dicek setiap tick dan jalan bila
    // jatuh tempo (catch-up). Pengecekan hanya membaca cache file; outbox/media/push lewat tick.
    $wib = fn () => CarbonImmutable::now('Asia/Jakarta');
    Schedule::command('rt05:tagihan-generate')->everyMinute()->withoutOverlapping()
        ->when(fn () => SharedHosting::dueSlot('tagihan-generate', $wib()->toDateString()))
        ->onSuccess(fn () => SharedHosting::markRan('tagihan-generate', $wib()->toDateString()));
    Schedule::command('rt05:pengingat-agenda')->everyMinute()->withoutOverlapping()
        ->when(fn () => SharedHosting::dueEvery('pengingat-agenda', 3600))
        ->onSuccess(fn () => SharedHosting::markRan('pengingat-agenda'));
    Schedule::command('rt05:token-rotate --if-due')->everyMinute()->withoutOverlapping()
        ->when(fn () => SharedHosting::dueEvery('token-rotate', 3600))
        ->onSuccess(fn () => SharedHosting::markRan('token-rotate'));
    // Tanggal 5 dan 20 mulai 08:00 WIB: tick pertama setelahnya mengirim; dedup slot per tanggal.
    Schedule::command('rt05:pengingat-iuran')->everyMinute()->withoutOverlapping()
        ->when(fn () => in_array($wib()->day, [5, 20], true) && $wib()->hour >= 8
            && SharedHosting::dueSlot('pengingat-iuran', $wib()->toDateString()))
        ->onSuccess(fn () => SharedHosting::markRan('pengingat-iuran', $wib()->toDateString()));
} else {
    // Heartbeat scheduler M03: dibaca rt05:heartbeat-check, tidak lewat endpoint publik.
    Schedule::call(fn () => Heartbeat::beat('scheduler'))->everyMinute()->name('rt05:heartbeat-scheduler');
    Schedule::command('rt05:outbox-dispatch')->everyMinute()->withoutOverlapping()->onOneServer();
    Schedule::command('rt05:tagihan-generate')->dailyAt('00:10')->timezone('Asia/Jakarta')->withoutOverlapping()->onOneServer();
    Schedule::command('rt05:media-recover')->everyTenMinutes()->withoutOverlapping()->onOneServer();
    Schedule::command('rt05:push-recover')->everyTenMinutes()->withoutOverlapping()->onOneServer();
    Schedule::command('rt05:pengingat-agenda')->hourly()->withoutOverlapping()->onOneServer();
    Schedule::command('rt05:pengingat-iuran')->monthlyOn(5, '08:00')->timezone('Asia/Jakarta')->onOneServer();
    Schedule::command('rt05:pengingat-iuran')->monthlyOn(20, '08:00')->timezone('Asia/Jakarta')->onOneServer();
    // Rotasi mingguan tahan scheduler terlewat: dicek tiap jam, rotasi saat token berumur 7 hari.
    Schedule::command('rt05:token-rotate --if-due')->hourlyAt(5)->withoutOverlapping()->onOneServer();
}
