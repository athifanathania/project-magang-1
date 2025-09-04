<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;

// JANGAN redirect / ke /berkas supaya panel publik Filament bisa jalan
// Route::redirect('/', '/berkas');

//
// Akses file AKTIF (stream). Boleh publik, tapi tetap lewat Policy (`BerkasPolicy@view`).
//
Route::get('/media/berkas/{berkas}', [MediaController::class, 'berkas'])
    ->whereNumber('berkas')
    ->name('media.berkas');

Route::get('/media/berkas/{berkas}/lampiran/{lampiran}', [MediaController::class, 'lampiran'])
    ->whereNumber(['berkas', 'lampiran'])
    ->name('media.berkas.lampiran');

//
// Download VERSI LAMA (history). Hanya untuk user login dengan role Admin/Editor.
//
Route::middleware('auth')->group(function () {
    Route::get('/media/berkas/{berkas}/version/{index}', [MediaController::class, 'berkasVersion'])
        ->whereNumber(['berkas', 'index'])
        ->name('media.berkas.version');

    Route::get('/media/lampiran/{lampiran}/version/{index}', [MediaController::class, 'lampiranVersion'])
        ->whereNumber(['lampiran', 'index'])
        ->name('media.lampiran.version');
});
