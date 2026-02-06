<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // WAJIB public
    public function getWidgets(): array
    {
        $panelId = optional(Filament::getCurrentPanel())->getId();

        return match ($panelId) {
            'public' => [
                // Panel Public cuma butuh Welcome saja
                \App\Filament\Widgets\PublicWelcome::class,
            ],
            'admin' => [
                \App\Filament\Widgets\AdminWelcome::class,
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\RegularDocumentTable::class,
                \App\Filament\Widgets\EventDocumentTable::class,
                \App\Filament\Widgets\ManualMutuDocumentTable::class,
                \App\Filament\Widgets\ProsedurDocumentTable::class,
                \App\Filament\Widgets\InstrukturStandarDocumentTable::class,
                \App\Filament\Widgets\FormulirDocumentTable::class,
                \App\Filament\Widgets\DocumentRatioChart::class,
                \App\Filament\Widgets\ActivityTypeChart::class,
                \App\Filament\Widgets\ActivityChart::class,
            ],
            default => [],
        };
    }

    public function getHeaderWidgets(): array
    {
        return [];
    }
}