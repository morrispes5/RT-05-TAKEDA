<?php

namespace App\Domain\Community\Push;

/** Tanpa kredensial FCM (dev, CI, shared sebelum Firebase siap): inbox saja. */
final class NullGateway implements PushGateway
{
    public function configured(): bool
    {
        return false;
    }

    public function send(array $messages): array
    {
        return array_map(fn () => PushResult::Failed, $messages);
    }
}
