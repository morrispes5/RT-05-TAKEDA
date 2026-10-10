<?php

namespace App\Models;

class PreferensiNotifikasi extends Model
{
    protected $table = 'preferensi_notifikasi';

    protected $primaryKey = 'akun_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'pengumuman' => 'boolean',
            'agenda' => 'boolean',
            'pengaduan' => 'boolean',
            'aspirasi' => 'boolean',
            'iuran' => 'boolean',
        ];
    }
}
