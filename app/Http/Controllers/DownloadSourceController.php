<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DownloadSourceController extends Controller
{
    public function __invoke(Request $request, string $type, int $id)
    {
        Gate::authorize('download-source');

        // Pemetaan slug → [model, kolom path]
        $map = [
            'berkas'               => [\App\Models\Berkas::class, 'dokumen_src'],
            'lampiran'             => [\App\Models\Lampiran::class, 'file_src'],
            'imm-lampiran'         => [\App\Models\ImmLampiran::class, 'file_src'],
            'imm-manual-mutu'      => [\App\Models\ImmManualMutu::class, 'file_src'],
            'imm-prosedur'         => [\App\Models\ImmProsedur::class, 'file_src'],
            'imm-instruksi-standar'=> [\App\Models\ImmInstruksiStandar::class, 'file_src'],
            'imm-formulir'         => [\App\Models\ImmFormulir::class, 'file_src'],
        ];

        abort_unless(isset($map[$type]), 404, 'Tipe tidak dikenal.');

        [$model, $pathCol] = $map[$type];
        $record = $model::query()->findOrFail($id);

        $path = (string) ($record->{$pathCol} ?? '');
        abort_if($path === '', 404, 'File asli belum ada.');

        // Sesuaikan disk jika beda; asumsi: disimpan di disk "private"
        $disk = Storage::disk('private');

        abort_unless($disk->exists($path), 404, 'File tidak ditemukan.');
        $filename = basename($path);

        // Unduh sebagai attachment
        return response()->download(
            $disk->path($path),
            $filename,
            [
                'Cache-Control' => 'private, max-age=0, no-store, no-cache, must-revalidate',
                'Pragma'        => 'no-cache',
            ]
        );
    }
}
