<?php

namespace App\Models;

class RiwayatTransaksiKas extends Model
{
    protected $table = 'riwayat_transaksi_kas';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'sebelum' => 'array',
            'sesudah' => 'array',
        ];
    }
}
