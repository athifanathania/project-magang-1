<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** Stream file AKTIF (path di kolom `file`, disk `private`) */
    public function file(Request $req, string $type, int $id): StreamedResponse
    {
        $modelClass = $this->mapTypeToModel($type);
        abort_unless($modelClass, 404);

        /** @var \Illuminate\Database\Eloquent\Model $m */
        $m = $modelClass::findOrFail($id);

        // TODO: kalau mau, pakai Gate/Policy di sini
        // Gate::authorize('view', $m);

        $path = (string) $m->file;
        abort_if(blank($path), 404, 'File belum tersedia.');

        return Storage::disk('private')->response($path);
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
