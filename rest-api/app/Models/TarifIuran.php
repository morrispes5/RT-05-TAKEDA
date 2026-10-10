<?php

namespace App\Models;

class TarifIuran extends Model
{
    protected $table = 'tarif_iuran';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'mulai_periode' => 'date',
        ];
    }
}
