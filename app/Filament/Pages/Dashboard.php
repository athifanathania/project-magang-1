<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // WAJIB public (kalau protected akan error)
    public function getWidgets(): array
    {
        $panelId = optional(Filament::getCurrentPanel())->getId();

        return match ($panelId) {
            'public' => [
                \App\Filament\Widgets\PublicWelcome::class,
            ],
            'admin' => [
                \App\Filament\Widgets\AdminWelcome::class,
            ],
            default => [],
        };
    }

    public function getHeaderWidgets(): array
    {
        return [];
    }
}
