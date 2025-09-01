<?php

namespace App\Filament\Resources\LampiranResource\Pages;

use App\Filament\Resources\LampiranResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListLampirans extends ListRecords
{
    protected static string $resource = LampiranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    public function getSubheading(): string|Htmlable
    {
        return 'Daftar lampiran lengkap beserta sub lampiran.';
    }

}
