<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PembayaranIuran extends Model
{
    protected $table = 'pembayaran_iuran';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'total_bayar' => 'integer',
            'tanggal_bayar' => 'date',
            'dibatalkan_at' => 'datetime',
        ];
    }

    public function rumah(): BelongsTo
    {
        return $this->belongsTo(Rumah::class, 'rumah_id');
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiPembayaran::class, 'pembayaran_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'diterima_oleh_id');
    }
}
