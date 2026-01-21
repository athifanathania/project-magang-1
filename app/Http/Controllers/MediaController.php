<?php

namespace App\Http\Controllers;

use App\Models\Berkas;
use App\Models\Lampiran;
use Illuminate\Support\Facades\Storage;
use App\Support\LogDownload;

class MediaController extends Controller
{
    protected function streamFromDisks(string $path)
    {
        foreach (['private', 'public'] as $disk) {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path);
            }
        }
        abort(404, 'File tidak ditemukan di storage.');
    }

    public function berkas(Berkas $berkas)
    {
        $this->authorize('view', $berkas);
        
        LogDownload::make([
            'type'      => 'berkas', 
            'file'      => basename($berkas->dokumen),
            'record_id' => $berkas->id,
            'path'      => $berkas->dokumen,
        ]);
        return $this->streamFromDisks((string) $berkas->dokumen);
    }

    public function lampiran(Berkas $berkas, Lampiran $lampiran)
    {
        abort_if($lampiran->berkas_id !== $berkas->id, 404);
        $this->authorize('view', $berkas);
        
        LogDownload::make([
            'type'      => 'lampiran', 
            'file'      => basename($lampiran->file),
            'record_id' => $lampiran->id,
            'path'      => $lampiran->file,
        ]);
        return $this->streamFromDisks((string) $lampiran->file);
    }

    /** PERBAIKAN DI SINI (berkasVersion) */
    public function berkasVersion(Berkas $berkas, int $index)
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin','Editor']), 403);

        $versions = $berkas->dokumen_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v = $versions[$index];

        // FIX: Cek 'path' ATAU 'file_path' agar tidak error undefined index
        $fp = $v['path'] ?? $v['file_path'] ?? null;

        // Pastikan path ada dan file fisik ada
        abort_unless($fp && Storage::disk('private')->exists($fp), 404, 'File fisik versi ini tidak ditemukan.');

        LogDownload::make([
            'type'      => 'berkas', 
            'file'      => $v['filename'] ?? basename($fp),
            'version'   => 'REV' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
            'record_id' => $berkas->id,
            'path'      => $fp, // Gunakan $fp, jangan $v['path']
        ]);

        return Storage::disk('private')->download(
            $fp,
            $v['filename'] ?? basename($fp)
        );
    }

    /** PERBAIKAN DI SINI JUGA (lampiranVersion) agar konsisten */
    public function lampiranVersion(Lampiran $lampiran, int $index)
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin','Editor']), 403);

        $versions = $lampiran->file_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v  = $versions[$index];
        
        // FIX: Cek 'path' ATAU 'file_path'
        $fp = $v['path'] ?? $v['file_path'] ?? null;

        abort_unless($fp && Storage::disk('private')->exists($fp), 404);

        LogDownload::make([
            'type'      => 'lampiran', 
            'file'      => $v['filename'] ?? basename($fp),
            'version'   => 'REV' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
            'record_id' => $lampiran->id,
            'path'      => $fp,
        ]);

        return Storage::disk('private')->download(
            $fp,
            $v['filename'] ?? basename($fp)
        );
    }

    public function lampiranRegular(\App\Models\Regular $regular, \App\Models\Lampiran $lampiran)
    {
        abort_if($lampiran->regular_id !== $regular->id, 404);
        $this->authorize('view', $regular);

        $path = (string) $lampiran->file;
        LogDownload::make([
            'type'      => 'lampiran', 
            'file'      => basename($lampiran->file),
            'record_id' => $lampiran->id,
            'path'      => $lampiran->file,
        ]);

        return $this->streamFromDisks($path);
    }
}