<?php

namespace App\Models;

class Agenda extends Model
{
    protected $table = 'agenda';

    protected function casts(): array
    {
        return [
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
            'pengingat_terkirim_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
