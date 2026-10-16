<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatUsulanKegiatan extends Model
{
    protected $table = 'riwayat_usulan_kegiatan';

    public const UPDATED_AT = null;

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'aktor_id');
    }
}
