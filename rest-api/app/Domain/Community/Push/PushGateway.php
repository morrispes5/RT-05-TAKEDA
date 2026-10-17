<?php

namespace App\Domain\Community\Push;

/**
 * Transport push (M10). Implementasi tidak pernah melempar karena kegagalan provider: setiap pesan
 * mendapat hasil, sehingga inbox dan event outbox tidak pernah bergantung pada push (FR20, ASYNC_JOBS).
 */
interface PushGateway
{
    /** false → tidak ada kredensial; notifikasi tetap di inbox, push ditandai dilewati. */
    public function configured(): bool;

    /**
     * @param  array<string, PushMessage>  $messages  key bebas (dipertahankan pada hasil)
     * @return array<string, PushResult>
     */
    public function send(array $messages): array;
}
