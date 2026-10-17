<?php

namespace App\Domain\Community\Push;

/**
 * Isi push aman (BUSINESS_RULES §notifikasi): judul/isi sama dengan inbox yang sudah disaring,
 * data hanya ID dan jenis. Aplikasi memuat ulang detail dari API sehingga policy server berlaku.
 */
final readonly class PushMessage
{
    /** @param array<string, string> $data */
    public function __construct(
        public string $token,
        public string $title,
        public string $body,
        public array $data,
        // Notifikasi Android dengan tag sama menggantikan yang lama: retry tidak menumpuk duplikat.
        public string $tag,
    ) {}
}
