<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AdminWelcome extends Widget
{
    protected static string $view = 'filament.widgets.admin-welcome';
    protected int|string|array $columnSpan = 'full';
    protected static bool $isLazy = false;
    protected static ?int $sort = 1;
}
