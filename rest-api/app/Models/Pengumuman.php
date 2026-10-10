<?php

namespace App\Models;

class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
