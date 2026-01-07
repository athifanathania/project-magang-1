<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;

// --- IMPORT MODEL (Sama seperti di LogDownload) ---
use App\Models\Berkas;
use App\Models\Regular;
use App\Models\Lampiran;
use App\Models\ImmManualMutu;
use App\Models\ImmProsedur;
use App\Models\ImmInstruksiStandar;
use App\Models\ImmFormulir;
use App\Models\ImmAuditInternal;
use App\Models\ImmLampiran;

class LogPageView
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // 1. Filter: Abaikan asset, debugbar, dll
        if ($request->is(['_debugbar/*','telescope*','horizon*','storage/*','livewire/*','log-viewer*'])) {
            return $response;
        }

        // 2. Filter: Hanya method GET (view) dan bukan AJAX
        if ($request->method() === 'GET' && !$request->ajax()) {

            // Siapkan Log
            $activity = activity()
                ->causedBy($request->user())
                ->event('view')
                ->withProperties([
                    'route'      => optional($request->route())->getName(),
                    'url'        => $request->fullUrl(),
                    'method'     => $request->method(),
                    'ip'         => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ]);

            // --- BAGIAN DETEKSI OBJEK (DIPERBAIKI) ---
            $subject = null;
            $routeParams = $request->route() ? $request->route()->parameters() : [];

            // CARA A: Cek apakah Controller sudah mengirim Model asli (Model Binding)
            foreach ($routeParams as $param) {
                if ($param instanceof Model) {
                    $subject = $param;
                    break;
                }
            }

            // CARA B: Jika CARA A gagal, kita cari manual berdasarkan ID dan Type (seperti LogDownload)
            if (! $subject) {
                // Coba cari parameter 'id' atau 'record_id'
                $id = $routeParams['id'] ?? $routeParams['record_id'] ?? null;
                
                // Coba cari parameter 'type'. Jika tidak ada di parameter, coba cek segmen URL.
                $type = $routeParams['type'] ?? $request->segment(2) ?? ''; 
                // Logika $request->segment(2) berasumsi URL-nya seperti: /admin/imm-manual-mutu/10

                if ($id) {
                    $subject = match ($type) {
                        // Mapping Dokumen IMM
                        'imm-manual-mutu'       => ImmManualMutu::find($id),
                        'imm-prosedur'          => ImmProsedur::find($id),
                        'imm-instruksi-standar' => ImmInstruksiStandar::find($id),
                        'imm-formulir'          => ImmFormulir::find($id),
                        'imm-audit-internal'    => ImmAuditInternal::find($id),
                        'imm-lampiran'          => ImmLampiran::find($id),
                        
                        // Mapping Dokumen Lain
                        'berkas'                => Berkas::find($id),
                        'lampiran'              => Lampiran::find($id),
                        'regular'               => Regular::find($id),

                        default => null,
                    };
                }
            }

            // Jika ketemu (baik dari Cara A atau Cara B), simpan ke Log!
            if ($subject) {
                $activity->performedOn($subject);
            }
            // --------------------------------------------

            $activity->log('View page');
        }

        return $response;
    }
}