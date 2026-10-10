<?php

namespace App\Models;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'dibaca_at' => 'datetime',
        ];
    }
}
