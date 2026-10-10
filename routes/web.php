<?php

use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'page'])->name('home');
foreach (['profil', 'fasilitas', 'dokumentasi', 'artikel', 'kontak'] as $page) {
    Route::get('/'.$page, [PublicPageController::class, 'page'])->defaults('page', $page)->name($page);
}
Route::get('/artikel/{slug}', [PublicPageController::class, 'article'])->name('article');
Route::get('/dokumentasi/{slug}', [PublicPageController::class, 'album'])->name('album');
Route::get('/pengurus', [PublicPageController::class, 'management'])->name('pengurus');
foreach (['masuk', 'artikel', 'artikel/editor', 'dokumentasi', 'dokumentasi/editor', 'pratinjau'] as $screen) {
    Route::get('/pengurus/'.$screen, [PublicPageController::class, 'management'])->defaults('screen', $screen);
}
Route::redirect('/pengurus/konten', '/pengurus');
