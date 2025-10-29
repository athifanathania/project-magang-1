<?php

namespace App\Http\Controllers;

use App\Models\Regular;
use App\Models\Lampiran;
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

    public function regular(Regular $regular)
    {
        $this->authorize('view', $regular); // buat policy jika dibutuhkan, atau cek role
        return $this->streamFromDisks((string) $regular->dokumen);
    }

    public function lampiran(Regular $regular, Lampiran $lampiran)
    {
        abort_if($lampiran->regular_id !== $regular->id, 404);
        $this->authorize('view', $regular);
        return $this->streamFromDisks((string) $lampiran->file);
    }

    public function regularVersion(Regular $regular, int $index)
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin','Editor']), 403);

        $versions = $regular->dokumen_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v = $versions[$index];
        abort_unless(Storage::disk('private')->exists($v['path'] ?? $v['file_path'] ?? ''), 404);

        $path = $v['path'] ?? $v['file_path'];
        return Storage::disk('private')->download(
            $path,
            $v['filename'] ?? basename($path)
        );
    }
}
