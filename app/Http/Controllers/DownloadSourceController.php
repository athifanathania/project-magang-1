<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Support\LogDownload;

class DownloadSourceController extends Controller
{
    public function __invoke(Request $request, string $type, int $id)
    {
        // Gate::authorize('download-source');

        $map = [
            'berkas'                => [\App\Models\Berkas::class, 'dokumen_src'],
            'lampiran'              => [\App\Models\Lampiran::class, 'file_src'],
            'imm-lampiran'          => [\App\Models\ImmLampiran::class, 'file_src'],
            'imm-lampiran-staf'     => [\App\Models\ImmLampiran::class, 'file_staf'],
            'imm-manual-mutu'       => [\App\Models\ImmManualMutu::class, 'file_src'],
            'imm-prosedur'          => [\App\Models\ImmProsedur::class, 'file_src'],
            'imm-instruksi-standar' => [\App\Models\ImmInstruksiStandar::class, 'file_src'],
            'imm-formulir'          => [\App\Models\ImmFormulir::class, 'file_src'],
        ];

        abort_unless(isset($map[$type]), 404, 'Tipe tidak dikenal.');

        [$model, $pathCol] = $map[$type];
        $record = $model::query()->findOrFail($id);

        $path = (string) ($record->{$pathCol} ?? '');

        // Fallback logic
        if ($path === '' && isset($record->file)) {
            $maybe = (string) ($record->file ?? '');
            $ext = strtolower(pathinfo($maybe, PATHINFO_EXTENSION));
            if ($maybe !== '' && $ext !== 'pdf') {
                $path = $maybe;
            }
        }

        abort_if($path === '', 404, 'File asli belum ada.');

        $disk = Storage::disk('private');
        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');
        
        $filename = basename($path);
        if (!empty($record->nama_dokumen)) {
            $filename = $record->nama_dokumen . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        }

        // Panggilan Ringkas
        LogDownload::make([
            'type'      => $type, 
            'file'      => $filename, 
            'record_id' => $record->getKey(),
            'path'      => $path,
        ]);

        return response()->download(
            $disk->path($path),
            $filename,
            ['Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache']
        );
    }

    public function version(Request $request, string $type, int $id, int $index)
    {
        Gate::authorize('download-source');

        $map = [
            'berkas'                => [\App\Models\Berkas::class, 'dokumen_src_versions'],
            'lampiran'              => [\App\Models\Lampiran::class, 'file_src_versions'],
            'imm-lampiran'          => [\App\Models\ImmLampiran::class, 'file_src_versions'],
            'imm-manual-mutu'       => [\App\Models\ImmManualMutu::class, 'file_src_versions'],
            'imm-prosedur'          => [\App\Models\ImmProsedur::class, 'file_src_versions'],
            'imm-instruksi-standar' => [\App\Models\ImmInstruksiStandar::class, 'file_src_versions'],
            'imm-formulir'          => [\App\Models\ImmFormulir::class, 'file_src_versions'],
        ];

        abort_unless(isset($map[$type]), 404, 'Tipe tidak dikenal.');
        [$model, $col] = $map[$type];

        $rec = $model::query()->findOrFail($id);
        $arr = collect($rec->{$col} ?? [])->values();
        
        abort_unless($arr->count(), 404, 'Riwayat file asli tidak tersedia.');
        abort_unless($index >= 0 && $index < $arr->count(), 404, 'Index versi tidak valid.');

        $user = $request->user();
        $isManager = $user?->hasAnyRole(['Admin','Editor']) ?? false;
        $isStaff = $user?->hasRole('Staff') ?? false;

        if ($isStaff && !$isManager) {
            $latest = $arr->count() - 1;
            abort_if($index !== $latest, 403, 'Staff hanya boleh mengunduh versi asli terbaru.');
        }

        $ver = $arr[$index];
        $path = (string)($ver['path'] ?? '');
        abort_if($path === '', 404, 'Path versi kosong.');

        $disk = \Storage::disk('private');
        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');
        $filename = (string)($ver['filename'] ?? basename($path));

        LogDownload::make([
            'type'      => $type,
            'file'      => $filename,
            'version'   => 'REV' . str_pad($index + 1, 2, '0', STR_PAD_LEFT),
            'record_id' => $rec->getKey(),
            'path'      => $path,
        ]);

        return response()->download(
            $disk->path($path),
            $filename,
            ['Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache']
        );
    }
}