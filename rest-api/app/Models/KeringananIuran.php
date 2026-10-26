<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeringananIuran extends Model
{
    protected $table = 'keringanan_iuran';

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(TagihanIuran::class, 'tagihan_id');
    }
}
