<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotoPengaduan extends Model
{
    protected $table = 'foto_pengaduan';

    public const UPDATED_AT = null;

    protected $hidden = ['storage_key'];

    public function pengaduan(): BelongsTo
    {
        return $this->belongsTo(Pengaduan::class, 'pengaduan_id');
    }
}
