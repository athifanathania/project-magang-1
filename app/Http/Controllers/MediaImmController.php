<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Support\LogDownload; // Pastikan file LogDownload ada di folder App/Support

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
            'Accept-Ranges'          => 'bytes',
        ]);

        if (is_file($full)) {
            $response->setEtag(md5_file($full));
            $response->setLastModified(\Illuminate\Support\Carbon::createFromTimestamp(filemtime($full)));
        }

        // Log untuk file dokumen utama
        LogDownload::make([
            'type'      => 'imm-' . $type, 
            'file'      => basename($path),
            'record_id' => $m->id,
            'path'      => $path,
        ]);

        return $response;
    }

    public function version(Request $req, string $type, int $id, int $index)
    {
        $modelClass = $this->mapTypeToModel($type);
        abort_unless($modelClass, 404);

        /** @var \Illuminate\Database\Eloquent\Model|\App\Models\Concerns\HasImmVersions $m */
        $m = $modelClass::findOrFail($id);

        $user = $req->user();
        abort_unless($user && $user->hasAnyRole(['Admin','Editor','Staff']), 403);

        $versions = $m->versionsList();
        abort_if($index < 0 || $index >= $versions->count(), 404, 'Versi tidak ditemukan.');

        $v = $versions[$index];
        $path = (string) ($v['file_path'] ?? '');
        abort_if(blank($path), 404, 'Path versi kosong.');

        $downloadName = (string) ($v['filename'] ?? basename($path));

        LogDownload::make([
            'type'      => 'imm-' . $type,
            'file'      => $downloadName,
            'version'   => 'REV' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
            'record_id' => $m->id,
            'path'      => $path,
        ]);

        return Storage::disk('private')->download($path, $downloadName);
    }

    /**
     * Handle download lampiran dengan LOG + Support Staf File
     */
    public function lampiran(Request $req, \App\Models\ImmLampiran $lampiran)
    {
        $user = $req->user();
        
        // 1. Cek User Login
        abort_unless($user && $user->hasAnyRole(['Admin','Editor','Staff']), 403);

        // 2. Tentukan File Mana yang Diambil (Staf vs Asli)
        $type = $req->query('type'); // Deteksi parameter ?type=staf dari Filament
        $path = null;
        $categoryLog = 'Lampiran Asli';

        if ($type === 'staf') {
            // Jika request file staf
            $path = $lampiran->file_staf;
            $categoryLog = 'Lampiran (Versi Staf)';
            if (blank($path)) abort(404, 'File staf belum diupload.');
        } else {
            // Jika request file asli (Default)
            $path = $lampiran->file;
            if (blank($path)) abort(404, 'File lampiran tidak tersedia.');
        }

        // 3. Validasi Fisik File
        $disk = 'private';
        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'File fisik tidak ditemukan.');
        }

        // 4. Siapkan Header & Nama File (LOGIKA LAMA KAMU)
        $full = Storage::disk($disk)->path($path);
        $size = filesize($full);
        $ext  = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : (File::mimeType($full) ?: 'application/octet-stream');

        $baseName   = basename($path);
        // Trik encoding nama file agar karakter aneh tidak error
        $asciiName  = preg_replace('/[^\x20-\x7E]/', '_', $baseName);
        $utf8Name   = rawurlencode($baseName);
        $disposition = ($ext === 'pdf' ? 'inline' : 'attachment')
            . '; filename="'.$asciiName.'"'
            . "; filename*=UTF-8''".$utf8Name;

        // 5. CATAT LOG DI SINI
        LogDownload::make([
            'type'      => 'imm-lampiran',
            'record_id' => $lampiran->id,
            'file'      => $baseName,   
            'path'      => $path,
            'category'  => $categoryLog 
        ]);

        // 6. Return Response (LOGIKA LAMA KAMU)
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
    }
}