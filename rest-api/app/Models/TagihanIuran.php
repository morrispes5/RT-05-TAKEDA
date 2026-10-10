<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagihanIuran extends Model
{
    protected $table = 'tagihan_iuran';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'nominal_tagihan' => 'integer',
            'periode' => 'date',
            'jatuh_tempo' => 'date',
        ];
    }

    public function rumah(): BelongsTo
    {
        return $this->belongsTo(Rumah::class, 'rumah_id');
    }

    public function alokasi(): HasMany
    {
        return $this->hasMany(AlokasiPembayaran::class, 'tagihan_id');
    }
}
