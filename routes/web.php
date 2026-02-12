<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaImmController;
use App\Http\Controllers\DownloadSourceController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\MediaRegularController;

/* ==============================================
   1. ROUTE HALAMAN DEPAN
============================================== */

Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/admin');
    }
    return view('welcome');
})->name('welcome');
    
/* ==============================================
   2. MEDIA & DOWNLOAD (WAJIB LOGIN)
============================================== */
Route::middleware('auth')->group(function () {
    
    // --- A. MEDIA BERKAS ---
    Route::get('/media/berkas/{berkas}', [MediaController::class, 'berkas'])
        ->whereNumber('berkas')->name('media.berkas');

    Route::get('/media/berkas/{berkas}/lampiran/{lampiran}', [MediaController::class, 'lampiran'])
        ->whereNumber(['berkas','lampiran'])->name('media.berkas.lampiran');

    Route::get('/media/berkas/{berkas}/version/{index}', [MediaController::class, 'berkasVersion'])
        ->whereNumber(['berkas','index'])->name('media.berkas.version');

    Route::get('/media/lampiran/{lampiran}/version/{index}', [MediaController::class, 'lampiranVersion'])
        ->whereNumber(['lampiran','index'])->name('media.lampiran.version');

    // --- B. MEDIA IMM ---
    Route::get('/media/imm/{type}/{id}', [MediaImmController::class, 'file'])
        ->whereIn('type', ['manual-mutu','prosedur','instruksi-standar','formulir'])
        ->whereNumber('id')->name('media.imm.file');

    Route::get('/media/imm/version/{id}/{index}', function (int $id, int $index) {
        $rec = \App\Models\ImmLampiran::findOrFail($id);
        $v   = collect($rec->file_versions ?? [])->get($index);
        abort_unless($v && !empty($v['file_path']), 404);

        $disk = 'private';
        abort_unless(Storage::disk($disk)->exists($v['file_path']), 404);

        $full = Storage::disk($disk)->path($v['file_path']);
        return response()->file($full); // Simplified response for readability
    })->name('media.imm.version');

    Route::get('/media/imm/lampiran/{lampiran}', [MediaImmController::class, 'lampiran'])
        ->whereNumber('lampiran')
        ->name('media.imm.lampiran');

    // --- C. MEDIA EVENT CUSTOMER (Baru) ---
    // Ini harus di dalam middleware auth supaya aman
    Route::get('/media/event-customer/{record}', function ($recordId) {
        $record = \App\Models\EventCustomer::findOrFail($recordId);
        
        if (empty($record->dokumen)) {
            abort(404, 'File not found in database.');
        }

        $path = $record->dokumen;
        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'File not found on server.');
        }

        $fullPath = Storage::disk('private')->path($path);
        return response()->file($fullPath, [
            'Content-Type' => File::mimeType($fullPath) ?: 'application/pdf',
        ]);
    })->name('media.event_customer');

    // --- D. DOWNLOAD SOURCE ---
    // Digabung jadi satu definisi saja biar rapi
    Route::get('/download/source/{type}/{id}', DownloadSourceController::class)
        ->whereIn('type', [
            'event_customer', // <--- Sudah ditambahkan
            'berkas','regular','lampiran','imm-lampiran',
            'imm-manual-mutu','imm-prosedur','imm-instruksi-standar','imm-formulir'
        ])
        ->whereNumber('id')->name('download.source');

    Route::get('/download/source/{type}/{id}/v/{index}', [DownloadSourceController::class, 'version'])
        ->whereIn('type', [
            'event_customer', 
            'berkas','regular','lampiran','imm-lampiran',
            'imm-manual-mutu','imm-prosedur','imm-instruksi-standar','imm-formulir'
        ])
        ->whereNumber(['id','index'])->name('download.source.version');
});

/* ==============================================
   3. REGULAR (WAJIB LOGIN)
============================================== */
Route::middleware('auth')->group(function () {
    Route::get('/media/regular/{regular}', [MediaRegularController::class, 'regular'])
        ->whereNumber('regular')->name('media.regular');

    Route::get('/media/regular/{regular}/lampiran/{lampiran}', [MediaController::class, 'lampiranRegular'])
        ->whereNumber(['regular','lampiran'])->name('media.regular.lampiran');

    Route::get('/media/regular/{regular}/version/{index}', [MediaRegularController::class, 'regularVersion'])
        ->whereNumber(['regular','index'])->name('media.regular.version');
});