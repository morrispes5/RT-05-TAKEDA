<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Berkas pendukung privat. storage_key disembunyikan dari JSON. */
class DokumenUsulan extends Model
{
    protected $table = 'dokumen_usulan';

    public const UPDATED_AT = null;

    protected $hidden = ['storage_key'];

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanKegiatan::class, 'usulan_kegiatan_id');
    }
}
