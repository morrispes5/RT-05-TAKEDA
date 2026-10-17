<?php

namespace App\Models;

class PerangkatPush extends Model
{
    protected $table = 'perangkat_push';

    protected $hidden = ['fcm_token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'fcm_token' => 'encrypted',
            'last_seen_at' => 'datetime',
        ];
    }
}
