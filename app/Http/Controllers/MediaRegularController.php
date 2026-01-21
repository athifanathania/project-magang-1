<?php

namespace App\Http\Controllers;

use App\Models\Regular;
use Illuminate\Support\Facades\Storage;
use App\Support\LogDownload;

class MediaRegularController extends Controller
{
    protected function streamFromDisks(string $path)
    {
        foreach (['private','public'] as $disk) {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path);
            }
        }
        abort(404, 'File tidak ditemukan di storage.');
    }

    public function regular(Regular $regular)
    {
        $this->authorize('view', $regular); 
        
        LogDownload::make([
            'type'      => 'regular', 
            'file'      => basename($regular->dokumen),
            'record_id' => $regular->id,
            'path'      => $regular->dokumen,
        ]);

        return $this->streamFromDisks((string) $regular->dokumen);
    }

    public function regularVersion(Regular $regular, int $index)
    {
        $this->authorize('view', $regular);

        $versions = $regular->dokumen_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v  = $versions[$index];
        $fp = $v['path'] ?? $v['file_path'] ?? null;
        abort_unless($fp && Storage::disk('private')->exists($fp), 404);

        LogDownload::make([
            'type'      => 'regular', 
            'file'      => $v['filename'] ?? basename($fp),
            'version'   => 'REV' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
            'record_id' => $regular->id,
            'path'      => $fp,
        ]);

        return Storage::disk('private')->download(
            $fp,
            $v['filename'] ?? basename($fp)
        );
    }
}