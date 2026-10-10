<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Warga extends Model
{
    protected $table = 'warga';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_kepala' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'keluarga_id');
    }

    public function akun(): HasOne
    {
        return $this->hasOne(Akun::class, 'warga_id');
    }
}
