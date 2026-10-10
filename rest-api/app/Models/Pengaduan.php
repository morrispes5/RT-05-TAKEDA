<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengaduan extends Model
{
    protected $table = 'pengaduan';

    protected function casts(): array
    {
        return [
            'sembunyikan_identitas' => 'boolean',
            'versi' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'pelapor_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriPengaduan::class, 'kategori_id');
    }

    public function foto(): HasMany
    {
        return $this->hasMany(FotoPengaduan::class, 'pengaduan_id')->orderBy('urutan');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatPengaduan::class, 'pengaduan_id')->orderBy('created_at');
    }
}
