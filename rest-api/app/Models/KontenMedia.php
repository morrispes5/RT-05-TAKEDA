<?php

namespace App\Models;

class KontenMedia extends Model
{
    protected $table = 'konten_media';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['ukuran_statis' => 'array', 'bytes' => 'integer', 'lebar' => 'integer', 'tinggi' => 'integer'];
    }
}
