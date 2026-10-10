<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermohonanAkun extends Model
{
    protected $table = 'permohonan_akun';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'diproses_at' => 'datetime',
        ];
    }

    public function akun(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'akun_id');
    }

    public function rumah(): BelongsTo
    {
        return $this->belongsTo(Rumah::class, 'rumah_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'diproses_oleh_id');
    }
}
