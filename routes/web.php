<?php

use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'page'])->name('home');
foreach (['profil', 'fasilitas', 'dokumentasi', 'artikel', 'kontak'] as $page) {
    Route::get('/'.$page, [PublicPageController::class, 'page'])->defaults('page', $page)->name($page);
}
Route::get('/artikel/{slug}', [PublicPageController::class, 'article'])->name('article');
Route::get('/pengurus/{module?}', [PublicPageController::class, 'management'])->name('pengurus');
