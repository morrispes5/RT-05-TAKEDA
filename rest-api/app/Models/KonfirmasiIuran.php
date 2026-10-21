<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto bukti transfer iuran dari warga (ADR35). Status: diajukan → selesai | ditolak (oleh pengurus). */
class KonfirmasiIuran extends Model
{
    protected $table = 'konfirmasi_iuran';

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return [
            'periode' => 'array',
            'nominal' => 'integer',
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
}
