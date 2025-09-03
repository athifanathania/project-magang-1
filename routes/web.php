<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;

// ❌ HAPUS / KOMENTARI baris ini agar "/" dipakai Dashboard panel publik
// Route::redirect('/', '/berkas')->name('home');

// Gateway file publik (tetap aman via Policy)
Route::get('/media/berkas/{berkas}', [MediaController::class, 'berkas'])
    ->whereNumber('berkas')
    ->name('media.berkas');

Route::get('/media/berkas/{berkas}/lampiran/{lampiran}', [MediaController::class, 'lampiran'])
    ->whereNumber(['berkas', 'lampiran'])
    ->name('media.berkas.lampiran');

// Panel admin tetap di /admin (dari AdminPanelProvider)
