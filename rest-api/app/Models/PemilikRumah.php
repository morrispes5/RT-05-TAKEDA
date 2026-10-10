<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PemilikRumah extends Model
{
    protected $table = 'pemilik_rumah';

    public const UPDATED_AT = null;

    public function rumah(): HasMany
    {
        return $this->hasMany(Rumah::class, 'pemilik_id');
    }
}
