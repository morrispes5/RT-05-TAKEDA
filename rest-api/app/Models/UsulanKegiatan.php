<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pengajuan kegiatan warga. Jadwal resmi ada di agenda, bukan di baris ini. */
class UsulanKegiatan extends Model
{
    protected $table = 'usulan_kegiatan';

    protected function casts(): array
    {
        return [
            'mulai_at' => 'datetime',
            'selesai_at' => 'datetime',
            'versi' => 'integer',
        ];
    }

    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'pengusul_id');
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(Agenda::class, 'agenda_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(DokumenUsulan::class, 'usulan_kegiatan_id')->orderBy('urutan');
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(RiwayatUsulanKegiatan::class, 'usulan_kegiatan_id')->orderBy('created_at');
    }
}
