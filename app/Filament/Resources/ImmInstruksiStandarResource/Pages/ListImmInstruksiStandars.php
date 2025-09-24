<?php

namespace App\Filament\Resources\ImmInstruksiStandarResource\Pages;

use App\Filament\Resources\ImmInstruksiStandarResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use App\Livewire\Concerns\HandlesImmLampiran;
use App\Livewire\Concerns\HandlesImmDocVersions;

class ListImmInstruksiStandars extends ListRecords
{
    use HandlesImmLampiran;
    use HandlesImmDocVersions;

    protected static string $resource = ImmInstruksiStandarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Instruksi & Standar Kerja')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }

}
