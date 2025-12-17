<?php

namespace App\Http\Controllers;

use App\Models\Regular;
use Illuminate\Support\Facades\Storage;

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

    /** Buka file aktif Regular */
    public function regular(Regular $regular)
    {
        $this->authorize('view', $regular); // <- butuh RegularPolicy
        LogDownload::make([
            'page'      => 'regular',
            'type'      => 'view',  
            'file'      => basename($regular->dokumen),
            'record_id' => $regular->id,
            'path'      => $regular->dokumen,
        ]);
        return $this->streamFromDisks((string) $regular->dokumen);
    }

    /** (opsional) Download versi lama dokumen Regular */
    public function regularVersion(Regular $regular, int $index)
    {
        $this->authorize('view', $regular);

        $versions = $regular->dokumen_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v  = $versions[$index];
        $fp = $v['path'] ?? $v['file_path'] ?? null;
        abort_unless($fp && Storage::disk('private')->exists($fp), 404);

        LogDownload::make([
            'page'      => 'regular',
            'type'      => 'version',
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
