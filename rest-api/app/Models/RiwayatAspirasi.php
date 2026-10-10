<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatAspirasi extends Model
{
    protected $table = 'riwayat_aspirasi';

    public const UPDATED_AT = null;

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'aktor_id');
    }
}
