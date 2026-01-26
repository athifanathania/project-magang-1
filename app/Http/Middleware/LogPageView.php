<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

// --- IMPORT MODEL ---
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

        // 1. Filter: Abaikan asset, debugbar, livewire internal, dll
        if ($request->is(['_debugbar/*','telescope*','horizon*','storage/*','livewire/*','log-viewer*','filament/*'])) {
            return $response;
        }

        // 2. Filter: Hanya method GET (view), bukan AJAX, dan User BELUM Login (Guest)
        if (!Auth::check() && $request->method() === 'GET' && !$request->ajax()) {

            // Siapkan Log Dasar
            $activity = activity()
                ->event('view')
                ->withProperties([
                    'ip'         => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'url'        => $request->fullUrl(),
                ]);

            // --- 3. LOGIKA DETEKSI OBJEK (SMART DETECTION) ---
            $subject = null;
            $routeParams = $request->route() ? $request->route()->parameters() : [];

            $id = $routeParams['record'] ?? $routeParams['id'] ?? null;
            
            $type = $request->segment(2); 

            if ($id && $type) {
                $subject = match ($type) {
                    'imm-manual-mutu'       => ImmManualMutu::find($id),
                    'imm-prosedur'          => ImmProsedur::find($id),
                    'imm-instruksi-standar' => ImmInstruksiStandar::find($id),
                    'imm-formulir'          => ImmFormulir::find($id),
                    'imm-audit-internal'    => ImmAuditInternal::find($id),
                    'imm-lampiran'          => ImmLampiran::find($id),
                    
                    'regular'               => Regular::find($id),
                    'berkas'                => Berkas::find($id),
                    'lampiran'              => Lampiran::find($id),

                    default => null,
                };
            }

            if ($subject) {
                $activity->performedOn($subject);
                $activity->log('Melihat Detail Dokumen (Public)');
            } else {
                $activity->log('Melihat Halaman (Public): ' . $request->path());
            }
        }

        return $response;
    }
}