<?php

namespace App\Support;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Str;

// Import semua Model
use App\Models\Berkas;
use App\Models\Regular;
use App\Models\Lampiran;
use App\Models\ImmManualMutu;
use App\Models\ImmProsedur;
use App\Models\ImmInstruksiStandar;
use App\Models\ImmFormulir;
use App\Models\ImmAuditInternal;
use App\Models\ImmLampiran;

class LogDownload
{
    public static function make(array $data): void
    {
        $id = $data['record_id'] ?? null;
        $type = $data['type'] ?? '';
        $rawFile = $data['file'] ?? '-';

        // -----------------------------------------------------------
        // 1. LOGIKA LABEL OTOMATIS
        // -----------------------------------------------------------
        
        // Tentukan Kategori Utama
        $kategori = 'Dokumen Lainnya';
        if (str_starts_with($type, 'imm-')) {
            $kategori = 'Dokumen Internal';
        } elseif ($type === 'regular') {
            $kategori = 'Dokumen Eksternal';
        } elseif (in_array($type, ['berkas', 'lampiran'])) {
            $kategori = 'Dokumen Pendukung';
        }

        // Tentukan Nama Halaman yang Cantik
        $halaman = Str::of($type)
            ->replace('imm-', '')
            ->replace('-', ' ')
            ->title()
            ->value(); // Pastikan jadi string murni

        // Gabungkan jadi Label Objek
        // Contoh: "Dokumen Internal # Manual Mutu"
        $customLabel = "{$kategori} # {$halaman}";

        // -----------------------------------------------------------
        // 2. MAPPING MODEL
        // -----------------------------------------------------------
        $subject = match ($type) {
            'imm-manual-mutu'       => ImmManualMutu::find($id),
            'imm-prosedur'          => ImmProsedur::find($id),
            'imm-instruksi-standar' => ImmInstruksiStandar::find($id),
            'imm-formulir'          => ImmFormulir::find($id),
            'imm-audit-internal'    => ImmAuditInternal::find($id),
            'imm-lampiran'          => ImmLampiran::find($id),
            
            'berkas'                => Berkas::find($id),
            'lampiran'              => Lampiran::find($id),
            'regular'               => Regular::find($id),

            default => null,
        };

        // -----------------------------------------------------------
        // 3. SIMPAN LOG
        // -----------------------------------------------------------
        $activity = activity('web')
            ->causedBy(auth()->user())
            ->withProperties([
                'object_label' => $customLabel, // Ini yang ditampilkan di tabel
                
                // Data cadangan
                'category'  => $kategori,
                'page'      => $halaman,
                'type'      => $type,
                'file'      => $rawFile,
                'version'   => $data['version'] ?? null,
                'path'      => $data['path'] ?? null,
                'ip'        => request()->ip(),
                'user_agent'=> request()->userAgent(),
            ]);

        if ($subject) {
            $activity->performedOn($subject);
        }

        $activity->event('download')->log("Download: {$rawFile}");
    }
}