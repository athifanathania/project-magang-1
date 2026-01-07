<?php

namespace App\Support;

use Spatie\Activitylog\Models\Activity;

// Import semua Model yang ada di folder App\Models kamu
use App\Models\Berkas;
use App\Models\Regular; // Pastikan file ini ada
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
        // 1. Ambil ID dan Type dari data yang dikirim
        $id = $data['record_id'] ?? null;
        $type = $data['type'] ?? '';

        // 2. Tentukan Model mana yang sedang di-download (Mapping Object)
        // Sesuaikan string di kiri ('imm-manual-mutu') dengan parameter 'type' dari tombol download di view kamu.
        $subject = match ($type) {
            // Mapping untuk Dokumen IMM
            'imm-manual-mutu'       => ImmManualMutu::find($id),
            'imm-prosedur'          => ImmProsedur::find($id),
            'imm-instruksi-standar' => ImmInstruksiStandar::find($id),
            'imm-formulir'          => ImmFormulir::find($id),
            'imm-audit-internal'    => ImmAuditInternal::find($id),
            'imm-lampiran'          => ImmLampiran::find($id),
            
            // Mapping untuk Dokumen Lain (sesuai file di sidebar kamu)
            'berkas'                => Berkas::find($id),
            'lampiran'              => Lampiran::find($id),
            'regular'               => Regular::find($id), 

            default => null,
        };

        // 3. Buat Activity Instance
        $activity = activity('web')
            ->causedBy(auth()->user())
            ->withProperties([
                'page'      => $data['page']      ?? null,
                'type'      => $data['type']      ?? null,
                'file'      => $data['file']      ?? null,
                'version'   => $data['version']   ?? null,
                'record_id' => $data['record_id'] ?? null,
                'path'      => $data['path']      ?? null,
            ]);

        // 4. PENTING: Jika subject ditemukan, kaitkan log ini dengan object tersebut
        if ($subject) {
            $activity->performedOn($subject);
        }

        // 5. Simpan log
        $activity->event('download')->log('download');
    }
}