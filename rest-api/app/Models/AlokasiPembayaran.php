<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlokasiPembayaran extends Model
{
    protected $table = 'alokasi_pembayaran';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'nominal_alokasi' => 'integer',
            'dibatalkan_at' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(TagihanIuran::class, 'tagihan_id');
    }

    public function pembayaran(): BelongsTo
    {
        return $this->belongsTo(PembayaranIuran::class, 'pembayaran_id');
    }
}
