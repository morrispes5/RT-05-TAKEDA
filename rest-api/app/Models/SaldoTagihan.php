<?php

namespace App\Models;

/** Read-only PostgreSQL aggregate view; eager-loaded so a recap needs one balance query. */
class SaldoTagihan extends Model
{
    protected $table = 'rt05_tagihan_saldo';
}
