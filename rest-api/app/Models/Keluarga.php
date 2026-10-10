<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Keluarga extends Model
{
    protected $table = 'keluarga';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'versi' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function warga(): HasMany
    {
        return $this->hasMany(Warga::class, 'keluarga_id');
    }

    public function penghunian(): HasMany
    {
        return $this->hasMany(Penghunian::class, 'keluarga_id');
    }

    public function penghunianAktif(): HasOne
    {
        return $this->hasOne(Penghunian::class, 'keluarga_id')->whereNull('selesai_tanggal');
    }
}
