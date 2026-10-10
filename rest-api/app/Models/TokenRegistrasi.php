<?php

namespace App\Models;

class TokenRegistrasi extends Model
{
    protected $table = 'token_registrasi';

    public const UPDATED_AT = null;

    protected $hidden = ['kode_terenkripsi', 'kode_digest'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'berlaku_sampai' => 'datetime',
            'dicabut_at' => 'datetime',
        ];
    }
}
