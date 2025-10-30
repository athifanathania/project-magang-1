<?php

namespace App\Http\Controllers;

use App\Models\Berkas;
use App\Models\Lampiran;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /** Stream file dari disk private/public */
    protected function streamFromDisks(string $path)
    {
        foreach (['private', 'public'] as $disk) {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path);
            }
        }
        abort(404, 'File tidak ditemukan di storage.');
    }

    /** Lihat file AKTIF dokumen berkas (diatur oleh Policy) */
    public function berkas(Berkas $berkas)
    {
        $this->authorize('view', $berkas);   // pastikan BerkasPolicy@view ada
        return $this->streamFromDisks((string) $berkas->dokumen);
    }

    /** Lihat file AKTIF lampiran dari berkas tertentu (Policy berkas) */
    public function lampiran(Berkas $berkas, Lampiran $lampiran)
    {
        abort_if($lampiran->berkas_id !== $berkas->id, 404);
        $this->authorize('view', $berkas);
        return $this->streamFromDisks((string) $lampiran->file);
    }

    /** Download versi lama dokumen berkas (Admin/Editor saja) */
    public function berkasVersion(Berkas $berkas, int $index)
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin','Editor']), 403);

        $versions = $berkas->dokumen_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v = $versions[$index];
        abort_unless(Storage::disk('private')->exists($v['path'] ?? ''), 404);

        return Storage::disk('private')->download(
            $v['path'],
            $v['filename'] ?? basename($v['path'])
        );
    }

    /** Download versi lama file lampiran (Admin/Editor saja) */
    public function lampiranVersion(Lampiran $lampiran, int $index)
    {
        abort_unless(auth()->user()?->hasAnyRole(['Admin','Editor']), 403);

        $versions = $lampiran->file_versions ?? [];
        abort_unless(isset($versions[$index]), 404);

        $v  = $versions[$index];
        $fp = $v['file_path'] ?? null;

        abort_unless($fp && Storage::disk('private')->exists($fp), 404);

        return Storage::disk('private')->download(
            $fp,
            $v['filename'] ?? basename($fp)
        );
    }

    public function lampiranRegular(\App\Models\Regular $regular, \App\Models\Lampiran $lampiran)
    {
        abort_if($lampiran->regular_id !== $regular->id, 404);

        $this->authorize('view', $regular); // sesuaikan policy/guard kamu

        $path = (string) $lampiran->file;
        return $this->streamFromDisks($path); // gunakan util yg sama seperti lampiran Berkas
    }

}
