<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Permintaan Pak RT (20 Okt 2026, ADR35): halaman Profil RT05 memuat cerita singkat "Tentang Taman Kedaung" yang dapat
| diedit pengurus. Profil yang sudah ada (staging/production) diberi teks awal dari seeder bila kolom `tentang` belum
| ada, di draf dan snapshot terbit. Tidak menimpa teks yang sudah diedit pengurus. Additive dan dapat dibalik.
*/
return new class extends Migration
{
    public function up(): void
    {
        $default = json_decode(file_get_contents(database_path('seeders/konten/profil.json')), true, flags: JSON_THROW_ON_ERROR)['tentang'];
        foreach (DB::table('konten')->where('jenis', 'profil')->get(['id', 'draf', 'terbit']) as $row) {
            $changes = [];
            foreach (['draf', 'terbit'] as $column) {
                $data = $row->{$column} === null ? null : json_decode($row->{$column}, true);
                if (is_array($data) && ! array_key_exists('tentang', $data)) {
                    $data['tentang'] = $default;
                    $changes[$column] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            if ($changes) {
                DB::table('konten')->where('id', $row->id)->update($changes);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('konten')->where('jenis', 'profil')->get(['id', 'draf', 'terbit']) as $row) {
            $changes = [];
            foreach (['draf', 'terbit'] as $column) {
                $data = $row->{$column} === null ? null : json_decode($row->{$column}, true);
                if (is_array($data) && array_key_exists('tentang', $data)) {
                    unset($data['tentang']);
                    $changes[$column] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            if ($changes) {
                DB::table('konten')->where('id', $row->id)->update($changes);
            }
        }
    }
};
