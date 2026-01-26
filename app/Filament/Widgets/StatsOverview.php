<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Regular;
use App\Models\Berkas;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;
use Filament\Facades\Filament; // Tambahan untuk auth check yang aman

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 2; 

    protected function getStats(): array
    {
        // 1. Buat dulu variabel array berisi data yang BISA DILIHAT SEMUA (Regular & Event)
        $stats = [
            Stat::make('Dokumen Regular', Regular::count())
                ->description('Total arsip regular')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Dokumen Event', Berkas::count())
                ->description('Total arsip event')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning')
                ->chart([3, 5, 3, 6, 3, 5, 3]),
        ];

        // 2. Cek: Apakah user yang login punya role 'Admin'?
        if (auth()->user()->hasRole('Admin')) {
            
            // Jika ADMIN, tambahkan (push) statistik User ke dalam array
            $stats[] = Stat::make('Total User', User::count())
                ->description('User terdaftar aktif')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]);

            // Jika ADMIN, tambahkan juga statistik Aktivitas
            $stats[] = Stat::make('Aktivitas Hari Ini', Activity::whereDate('created_at', today())->count())
                ->description('Interaksi sistem')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color('gray');
        }

        // 3. Kembalikan array finalnya
        return $stats;
    }
}