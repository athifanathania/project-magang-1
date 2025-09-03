<?php

namespace App\Http\Controllers;

use App\Models\Berkas;
use App\Models\Lampiran;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    // helper untuk stream file dengan fallback disk
    protected function streamFromDisks(string $path)
    {
        foreach (['private', 'public'] as $disk) {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                // respons stream aman dari storage
                return Storage::disk($disk)->response($path);
            }
        }

        abort(404, 'File tidak ditemukan di storage.');
    }

    public function berkas(Berkas $berkas)
    {
        $this->authorize('view', $berkas);
        return $this->streamFromDisks((string) $berkas->dokumen);
    }

    public function lampiran(Berkas $berkas, Lampiran $lampiran)
    {
        abort_if($lampiran->berkas_id !== $berkas->id, 404);
        $this->authorize('view', $berkas);
        return $this->streamFromDisks((string) $lampiran->file);
    }
}
