<?php

namespace App\Filament\Resources\RegularResource\Pages;

use App\Filament\Resources\RegularResource;
use App\Filament\Resources\BerkasResource\Pages\ListBerkas;

class ListRegular extends ListBerkas
{
    protected static string $resource = RegularResource::class;
}
