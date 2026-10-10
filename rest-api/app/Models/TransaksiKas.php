<?php

namespace App\Models;

class TransaksiKas extends Model
{
    protected $table = 'transaksi_kas';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'tanggal' => 'date',
        ];
    }
}
