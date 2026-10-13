<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status: diproses (berkas mentah privat, menunggu worker-media) → siap | gagal. */
class FotoPengaduan extends Model
{
    protected $table = 'foto_pengaduan';

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['percobaan' => 'integer'];
    }

    public function pengaduan(): BelongsTo
    {
        return $this->belongsTo(Pengaduan::class, 'pengaduan_id');
    }
}
