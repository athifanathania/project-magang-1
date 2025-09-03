<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PublicWelcome extends Widget
{
    protected static string $view = 'filament.widgets.public-welcome';

    // BIAR NGAMBIL 1 BARIS PENUH GRID DASHBOARD
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;
}
