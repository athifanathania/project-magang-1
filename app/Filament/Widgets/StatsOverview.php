<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// --- DOKUMEN EKSTERNAL ---
use App\Models\Regular;
use App\Models\Berkas;     // Event
use App\Models\Lampiran;   // Lampiran Eksternal

// --- DOKUMEN INTERNAL (Sesuaikan Nama Model Anda) ---
use App\Models\ImmLampiran;       // Lampiran Internal (Single Model)
use App\Models\ImmManualMutu;
use App\Models\ImmProsedur;
use App\Models\ImmInstruksiStandar; 
use App\Models\ImmFormulir;
use App\Models\ImmAuditInternal;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 2; 

    protected function getStats(): array
    {
        // ==========================================
        // 1. DATA DOKUMEN EKSTERNAL
        // ==========================================
        $totalRegular = Regular::count();
        $totalEvent   = Berkas::count();
        
        $lampiranRegular = Lampiran::whereNotNull('regular_id')->count();
        $lampiranEvent   = Lampiran::whereNotNull('berkas_id')->count();

        // ==========================================
        // 2. DATA DOKUMEN INTERNAL (Polymorphic)
        // ==========================================
        
        // A. Manual Mutu
        $totalMM = ImmManualMutu::count();
        $lampiranMM = ImmLampiran::where('documentable_type', ImmManualMutu::class)->count();

        // B. Prosedur
        $totalProsedur = ImmProsedur::count();
        $lampiranProsedur = ImmLampiran::where('documentable_type', ImmProsedur::class)->count();

        // C. Instruksi Kerja & Standar
        // Pastikan nama model sesuai file anda
        $totalIK = ImmInstruksiStandar::count(); 
        $lampiranIK = ImmLampiran::where('documentable_type', ImmInstruksiStandar::class)->count();

        // D. Formulir
        $totalForm = ImmFormulir::count();
        $lampiranForm = ImmLampiran::where('documentable_type', ImmFormulir::class)->count();

        // E. Audit Internal
        $totalAudit = ImmAuditInternal::count();
        $lampiranAudit = ImmLampiran::where('documentable_type', ImmAuditInternal::class)->count();


        // ==========================================
        // 3. BUILD CARDS
        // ==========================================
        
        $cards = [
            // --- BARIS 1: EKSTERNAL ---
            Stat::make('Dokumen Regular', $totalRegular)
                ->description("Total {$lampiranRegular} lampiran")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Dokumen Event', $totalEvent)
                ->description("Total {$lampiranEvent} lampiran")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning')
                ->chart([3, 5, 3, 6, 3, 5, 3]),
        ];

        // --- BARIS 2: INTERNAL ---
        
        $cards[] = Stat::make('Manual Mutu', $totalMM)
            ->description("{$lampiranMM} Lampiran")
            ->descriptionIcon('heroicon-m-book-open')
            ->color('success') // Hijau
            ->chart([2, 4, 6, 8, 5, 2]);

        $cards[] = Stat::make('Prosedur', $totalProsedur)
            ->description("{$lampiranProsedur} Lampiran")
            ->descriptionIcon('heroicon-m-clipboard-document-list')
            ->color('primary') // Biru
            ->chart([5, 2, 5, 2, 5, 2]);

        $cards[] = Stat::make('Instruksi Kerja & Standar', $totalIK)
            ->description("{$lampiranIK} Lampiran")
            ->descriptionIcon('heroicon-m-wrench-screwdriver')
            ->color('danger') // Merah
            ->chart([2, 2, 8, 2, 2, 8]);

        $cards[] = Stat::make('Formulir', $totalForm)
            ->description("{$lampiranForm} Lampiran")
            ->descriptionIcon('heroicon-m-table-cells')
            ->color('gray')
            ->chart([1, 1, 1, 10, 1, 1]);

        $cards[] = Stat::make('Audit Internal', $totalAudit)
            ->description("{$lampiranAudit} Lampiran")
            ->descriptionIcon('heroicon-m-scale')
            ->color('warning') // Kuning/Oranye
            ->chart([9, 5, 7, 4, 6, 2]);


        // --- BARIS 3: ADMIN ONLY ---
        if (auth()->check() && auth()->user()->hasRole('Admin')) {
            $cards[] = Stat::make('Total User', User::count())
                ->description('User aktif')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary');

            $cards[] = Stat::make('Aktivitas Hari Ini', Activity::whereDate('created_at', today())->count())
                ->description('Interaksi sistem')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color('gray');
        }

        return $cards;
    }
}