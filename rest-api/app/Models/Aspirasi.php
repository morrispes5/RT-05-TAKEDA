<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aspirasi extends Model
{
    protected $table = 'aspirasi';

    protected function casts(): array
    {
        return [
            'sembunyikan_identitas' => 'boolean',
            'versi' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'pengirim_id');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatAspirasi::class, 'aspirasi_id')->orderBy('created_at');
    }
}
