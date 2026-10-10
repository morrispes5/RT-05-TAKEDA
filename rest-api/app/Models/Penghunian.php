<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penghunian extends Model
{
    protected $table = 'penghunian';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'mulai_tanggal' => 'date',
            'selesai_tanggal' => 'date',
        ];
    }

    public function rumah(): BelongsTo
    {
        return $this->belongsTo(Rumah::class, 'rumah_id');
    }

    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'keluarga_id');
    }
}
