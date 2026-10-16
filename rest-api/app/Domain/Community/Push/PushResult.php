<?php

namespace App\Domain\Community\Push;

enum PushResult: string
{
    case Sent = 'sent';
    // Token tidak berlaku lagi (aplikasi dihapus/token diganti): baris perangkat dihapus.
    case InvalidToken = 'invalid_token';
    // Sementara (jaringan, 429, 5xx, token akses kedaluwarsa): dicoba ulang dengan backoff.
    case Retry = 'retry';
    // Permanen untuk pesan ini (payload ditolak, proyek salah): tidak diulang.
    case Failed = 'failed';
}
