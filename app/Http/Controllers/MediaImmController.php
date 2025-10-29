<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class MediaImmController extends Controller
{
    protected function mapTypeToModel(string $type): ?string
    {
        return [
            'manual-mutu'        => \App\Models\ImmManualMutu::class,
            'prosedur'           => \App\Models\ImmProsedur::class,
            'instruksi-standar'  => \App\Models\ImmInstruksiStandar::class,
            'formulir'           => \App\Models\ImmFormulir::class,
        ][$type] ?? null;
    }

    public function file(Request $req, string $type, int $id)
    {
        $modelClass = $this->mapTypeToModel($type);
        abort_unless($modelClass, 404);

        $user = $req->user();
        abort_unless($user && $user->hasAnyRole(['Admin','Editor','Staff']), 403);

        $m = $modelClass::findOrFail($id);
        $path = (string) $m->file;
        abort_if(blank($path), 404, 'File belum tersedia.');

        $disk = 'private';
        $full = \Storage::disk($disk)->path($path);

        $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : (\Illuminate\Support\Facades\File::mimeType($full) ?: 'application/octet-stream');
        $name = basename($path);

        $response = response()->file($full, [
            'Content-Type'           => $mime,
            'Content-Disposition'    => ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="'.$name.'"',
            'Accept-Ranges'          => 'bytes', // bantu viewer Edge
            // 'X-Content-Type-Options' => 'nosniff', // jika Edge masih error, coba sementara NONAKTIFKAN baris ini
        ]);

        // Tambahkan ETag & Last-Modified (membantu caching & range)
        if (is_file($full)) {
            $response->setEtag(md5_file($full));
            $response->setLastModified(\Illuminate\Support\Carbon::createFromTimestamp(filemtime($full)));
        }

        return $response;
    }

    /** Download salah satu versi (index 0-based sesuai urutan tersimpan di kolom `versions`) */
    public function version(Request $req, string $type, int $id, int $index)
    {
        $modelClass = $this->mapTypeToModel($type);
        abort_unless($modelClass, 404);

        /** @var \Illuminate\Database\Eloquent\Model|\App\Models\Concerns\HasImmVersions $m */
        $m = $modelClass::findOrFail($id);

        // Role minimal: Admin/Editor/Staff (viewer tidak boleh)
        $user = $req->user();
        abort_unless($user && $user->hasAnyRole(['Admin','Editor','Staff']), 403);

        $versions = $m->versionsList();
        abort_if($index < 0 || $index >= $versions->count(), 404, 'Versi tidak ditemukan.');

        $v = $versions[$index];
        $path = (string) ($v['file_path'] ?? '');
        abort_if(blank($path), 404, 'Path versi kosong.');

        $downloadName = (string) ($v['filename'] ?? basename($path));
        return Storage::disk('private')->download($path, $downloadName);
    }
}
