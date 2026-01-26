<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Spatie\Activitylog\Models\Activity;

class ActivityChart extends ChartWidget
{
    protected static ?string $heading = 'Tren Aktivitas Pengguna (7 Hari Terakhir)';
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full'; 

    // --- BAGIAN PENGAMAN (Hanya Admin) ---
    public static function canView(): bool
    {
        // Hanya return true jika user adalah Admin
        return auth()->user()->hasRole('Admin');
    }
    // -------------------------------------

    protected function getData(): array
    {
        $data = Trend::model(Activity::class)
            ->between(
                start: now()->subDays(6),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Aktivitas',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#3b82f6', 
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}