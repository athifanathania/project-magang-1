<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaImmController;
use App\Models\ImmLampiran;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

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

// --- BUKA FILE LAMPIRAN IMM (letakkan DI ATAS route generik) ---
Route::get('/media/imm/lampiran/{lampiran}', function (ImmLampiran $lampiran) {
    abort_if(blank($lampiran->file), 404);

    $disk = 'private';
    abort_unless(Storage::disk($disk)->exists($lampiran->file), 404);

    $full = Storage::disk($disk)->path($lampiran->file);
    $mime = File::mimeType($full) ?? 'application/octet-stream';
    $name = basename($lampiran->file);

    return Response::file($full, [
        'Content-Type'        => $mime,
        'Content-Disposition' => 'inline; filename="'.$name.'"',
    ]);
})->whereNumber('lampiran')->name('media.imm.lampiran');

Route::get('/media/imm/{type}/{id}', [MediaImmController::class, 'file'])
    ->whereIn('type', ['manual-mutu','prosedur','instruksi-standar','formulir'])
    ->whereNumber('id')
    ->name('media.imm.file');


Route::middleware('auth')->group(function () {
    Route::get('/media/imm/version/{id}/{index}', function (int $id, int $index) {
        $rec = \App\Models\ImmLampiran::findOrFail($id);

        $versions = collect($rec->file_versions ?? []);
        $v = $versions->get($index);
        abort_unless($v && !empty($v['path']), 404);

        $disk = 'private'; // <-- FIX
        abort_unless(Storage::disk($disk)->exists($v['path']), 404);

        $full = Storage::disk($disk)->path($v['path']);
        $mime = File::mimeType($full) ?? 'application/octet-stream';
        $name = $v['filename'] ?? basename($v['path']);

        return Response::file($full, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    })->name('media.imm.version');
});