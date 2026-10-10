<?php

namespace App\Models;

class KategoriPengaduan extends Model
{
    protected $table = 'kategori_pengaduan';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}
