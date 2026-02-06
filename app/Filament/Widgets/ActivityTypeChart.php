<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ActivityTypeChart extends ChartWidget
{
    protected static ?string $heading = 'Komposisi Event Sistem';
    protected static ?int $sort = 15;
    protected static ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return auth()->user()->hasRole('Admin');
    }

    protected function getData(): array
    {
        $data = Activity::select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Event',
                    'data' => $data->pluck('total'),
                    'backgroundColor' => [
                        '#22c55e', // Hijau
                        '#eab308', // Kuning
                        '#ef4444', // Merah
                        '#3b82f6', // Biru
                        '#64748b', // Abu
                    ],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => $data->pluck('event')->map(fn($ev) => strtoupper($ev)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'display' => false,
                ],
                'y' => [
                    'display' => false, 
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
    // ---------------------------------------------------

    protected function getType(): string
    {
        return 'doughnut';
    }
}