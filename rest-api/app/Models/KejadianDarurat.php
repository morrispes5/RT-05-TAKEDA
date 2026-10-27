<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KejadianDarurat extends Model
{
    protected $table = 'kejadian_darurat';

    public const UPDATED_AT = null;

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'pelapor_id');
    }

    public function rumah(): BelongsTo
    {
        return $this->belongsTo(Rumah::class, 'rumah_id');
    }

    protected function casts(): array
    {
        return ['penyalahgunaan' => 'boolean', 'versi' => 'integer', 'selesai_at' => 'datetime'];
    }
}
