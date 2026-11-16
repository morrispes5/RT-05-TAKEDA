<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * ADR61: data inti tidak bisa dihapus permanen (trigger rt05_larang_hapus). Tes yang memang perlu membuang baris
     * (menyiapkan keadaan kosong, membersihkan data yang sudah commit) membuka izin hanya selama closure ini.
     */
    protected function hapusPermanen(Closure $hapus, string $connection = 'pgsql'): void
    {
        DB::connection($connection)->transaction(function ($db) use ($hapus) {
            $db->statement("SET LOCAL rt05.izinkan_hapus = 'ya'");
            $hapus($db);
            // Di dalam transaksi tes (savepoint) SET LOCAL bertahan sampai transaksi terluar selesai; tutup lagi di sini.
            $db->statement("SET LOCAL rt05.izinkan_hapus = ''");
        });
    }
}
