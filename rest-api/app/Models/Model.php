<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Basis model RT05 TAKEDA: UUID (v7), timestamptz UTC, nama tabel ERD v1.1.
 * Controller tidak pernah mengoper request->all(); atribut diisi eksplisit oleh Action.
 */
abstract class Model extends Eloquent
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:sP';
}
