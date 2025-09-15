<?php

namespace App\Filament\Resources\ImmLampiranResource\Pages;

use App\Filament\Resources\ImmLampiranResource;
use Filament\Resources\Pages\ListRecords;

class ListImmLampiran extends ListRecords
{
    protected static string $resource = ImmLampiranResource::class;

    public function mount(): void
    {
        $back = url()->previous() ?: route('filament.admin.pages.dashboard');
        $this->redirect($back);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
