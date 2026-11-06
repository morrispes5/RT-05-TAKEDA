<?php

namespace App\Support\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Konektor pgsql yang menyalakan jembatan Neon (ADR55) tepat sebelum koneksi pertama bila socket belum ada.
 * Koneksi yang bukan ke folder jembatan (VPS, CI, migrasi dari PC) diteruskan tanpa perubahan.
 */
class BridgedPostgresConnector extends PostgresConnector
{
    public function __construct(private readonly NeonBridge $bridge) {}

    public function connect(array $config)
    {
        if ($this->bridge->serves($config)) {
            // Gagal menyalakan tidak dilempar di sini: PDO tetap mencoba dan error koneksi biasa yang muncul.
            $this->bridge->ensureRunning();
        }

        return parent::connect($config);
    }
}
