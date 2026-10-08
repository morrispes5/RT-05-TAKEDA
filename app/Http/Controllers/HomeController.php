<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Sementara foto dibaca dari berkas data; nanti diganti tabel album_galeri/foto_galeri.
        $data = json_decode(file_get_contents(resource_path('data/dokumentasi.json')), true, flags: JSON_THROW_ON_ERROR);

        return view('home', ['foto' => $data['foto']]);
    }
}
