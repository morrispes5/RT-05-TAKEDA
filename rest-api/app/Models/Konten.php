<?php

namespace App\Models;

class Konten extends Model
{
    protected $table = 'konten';

    protected function casts(): array
    {
        return [
            'draf' => 'array',
            'terbit' => 'array',
            'versi' => 'integer',
            'versi_terbit' => 'integer',
            'urutan' => 'integer',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
