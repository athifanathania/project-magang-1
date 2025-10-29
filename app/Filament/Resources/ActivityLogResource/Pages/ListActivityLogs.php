<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;    

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->check() && auth()->user()->hasRole('Admin');
    }

}
