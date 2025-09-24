<?php

namespace App\Filament\Resources\ImmFormulirResource\Pages;

use App\Filament\Resources\ImmFormulirResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use App\Livewire\Concerns\HandlesImmLampiran;
use App\Livewire\Concerns\HandlesImmDocVersions;

class ListImmFormulirs extends ListRecords
{
    use HandlesImmLampiran;
    use HandlesImmDocVersions;

    protected static string $resource = ImmFormulirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Formulir')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }

}
