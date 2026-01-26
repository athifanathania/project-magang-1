<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Regular;
use App\Models\Berkas; 

class DocumentRatioChart extends ChartWidget
{
    protected static ?string $heading = 'Perbandingan Jumlah Dokumen Event dan Regular';
    protected static ?int $sort = 3; 
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $totalRegular = Regular::count();
        $totalEvent = Berkas::count(); 

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Dokumen',
                    'data' => [$totalRegular, $totalEvent],
                    'backgroundColor' => [
                        '#3b82f6', // Biru (Regular)
                        '#f59e0b', // Kuning/Orange (Event)
                    ],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => ['Regular', 'Event'],
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