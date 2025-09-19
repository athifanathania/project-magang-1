<?php

namespace App\Filament\Resources\ImmProsedurResource\Pages;

use App\Filament\Resources\ImmProsedurResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use App\Livewire\Concerns\HandlesImmLampiran;
use App\Livewire\Concerns\HandlesImmDocVersions;

class ListImmProsedurs extends ListRecords
{
    use HandlesImmLampiran;
    use HandlesImmDocVersions;

    protected static string $resource = ImmProsedurResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Dokumen')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }

}
