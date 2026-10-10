<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPengaduan extends Model
{
    protected $table = 'riwayat_pengaduan';

    public const UPDATED_AT = null;

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'aktor_id');
    }
}
