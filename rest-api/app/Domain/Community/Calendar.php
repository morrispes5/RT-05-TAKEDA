<?php

namespace App\Domain\Community;

use App\Models\Agenda;

/** Tambah ke kalender lewat tautan/ICS (BR19); tidak memerlukan akses penuh Google Calendar. */
final class Calendar
{
    public static function googleUrl(Agenda $a): string
    {
        $fmt = fn ($t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $end = $a->selesai_at ?? $a->mulai_at->copy()->addHours(2);

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => $a->nama,
            'dates' => $fmt($a->mulai_at).'/'.$fmt($end),
            'details' => $a->deskripsi,
            'location' => $a->lokasi,
            'ctz' => 'Asia/Jakarta',
        ]);
    }

    public static function ics(Agenda $a): string
    {
        $fmt = fn ($t) => $t->copy()->utc()->format('Ymd\THis\Z');
        $esc = fn (string $v) => str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $v);
        $end = $a->selesai_at ?? $a->mulai_at->copy()->addHours(2);

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//RT05 TAKEDA//Agenda//ID', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:'.$a->id.'@takeda.morriz.tech',
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($a->mulai_at),
            'DTEND:'.$fmt($end),
            'SUMMARY:'.$esc($a->nama),
            'LOCATION:'.$esc($a->lokasi),
            'DESCRIPTION:'.$esc($a->deskripsi),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);
    }
}
