    <?php

    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\MediaController;
    use App\Http\Controllers\MediaImmController;
    use App\Models\ImmLampiran;
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Facades\Response;
    use Illuminate\Support\Facades\File;
    use App\Http\Controllers\DownloadSourceController;

    Route::get('/media/berkas/{berkas}', [MediaController::class, 'berkas'])
        ->whereNumber('berkas')
        ->name('media.berkas');

    Route::get('/media/berkas/{berkas}/lampiran/{lampiran}', [MediaController::class, 'lampiran'])
        ->whereNumber(['berkas', 'lampiran'])
        ->name('media.berkas.lampiran');


    Route::middleware('auth')->group(function () {
        Route::get('/media/berkas/{berkas}/version/{index}', [MediaController::class, 'berkasVersion'])
            ->whereNumber(['berkas', 'index'])
            ->name('media.berkas.version');

        Route::get('/media/lampiran/{lampiran}/version/{index}', [MediaController::class, 'lampiranVersion'])
            ->whereNumber(['lampiran', 'index'])
            ->name('media.lampiran.version');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/download/source/{type}/{id}', DownloadSourceController::class)
            ->whereIn('type', ['berkas','lampiran','imm-lampiran','imm-manual-mutu','imm-prosedur','imm-instruksi-standar','imm-formulir'])
            ->whereNumber('id')
            ->name('download.source');

        Route::get('/download/source/{type}/{id}/v/{index}', [DownloadSourceController::class, 'version'])
            ->whereIn('type', ['berkas','lampiran','imm-lampiran','imm-manual-mutu','imm-prosedur','imm-instruksi-standar','imm-formulir'])
            ->whereNumber(['id','index'])
            ->name('download.source.version');
    });


    // --- BUKA FILE LAMPIRAN IMM (letakkan DI ATAS route generik) ---
    Route::get('/media/imm/lampiran/{lampiran}', function (\App\Models\ImmLampiran $lampiran) {
        abort_if(blank($lampiran->file), 404);

        $disk = 'private';
        abort_unless(Storage::disk($disk)->exists($lampiran->file), 404);

        $full = Storage::disk($disk)->path($lampiran->file);
        $size = filesize($full);
        $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : (File::mimeType($full) ?: 'application/octet-stream');

        // Disposition + nama aman (Edge kadang sensi ke spasi/UTF-8)
        $baseName   = basename($lampiran->file);
        $asciiName  = preg_replace('/[^\x20-\x7E]/', '_', $baseName); // fallback ASCII
        $utf8Name   = rawurlencode($baseName);
        $disposition = ($ext === 'pdf' ? 'inline' : 'attachment')
            . '; filename="' . $asciiName . '"'
            . "; filename*=UTF-8''" . $utf8Name;

        $response = response()->file($full, [
            'Content-Type'        => $mime,
            'Content-Disposition' => $disposition,
            'Content-Length'      => $size,          // penting untuk Edge
            'Accept-Ranges'       => 'bytes',
            // JANGAN set X-Content-Type-Options di endpoint media (Edge+Acrobat bisa salah deteksi)
            'Cache-Control'       => 'private, max-age=0, must-revalidate',
        ]);

        if (is_file($full)) {
            $response->setEtag(md5_file($full));
            $response->setLastModified(\Illuminate\Support\Carbon::createFromTimestamp(filemtime($full)));
        }

        return $response;
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
            abort_unless($v && !empty($v['file_path']), 404);

            $disk = 'private';
            abort_unless(Storage::disk($disk)->exists($v['file_path']), 404);

            $full = Storage::disk($disk)->path($v['file_path']);
            $size = filesize($full);
            $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            $mime = $ext === 'pdf' ? 'application/pdf' : (File::mimeType($full) ?: 'application/octet-stream');

            $baseName   = $v['filename'] ?? basename($v['file_path']);
            $asciiName  = preg_replace('/[^\x20-\x7E]/', '_', $baseName);
            $utf8Name   = rawurlencode($baseName);
            $disposition = ($ext === 'pdf' ? 'inline' : 'attachment')
                . '; filename="' . $asciiName . '"'
                . "; filename*=UTF-8''" . $utf8Name;

            $response = response()->file($full, [
                'Content-Type'        => $mime,
                'Content-Disposition' => $disposition,
                'Content-Length'      => $size,
                'Accept-Ranges'       => 'bytes',
                'Cache-Control'       => 'private, max-age=0, must-revalidate',
            ]);

            if (is_file($full)) {
                $response->setEtag(md5_file($full));
                $response->setLastModified(\Illuminate\Support\Carbon::createFromTimestamp(filemtime($full)));
            }

            return $response;
        })->name('media.imm.version');
    });
