<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On; 
use App\Livewire\Concerns\HandlesImmLampiran;
use App\Livewire\Concerns\HandlesImmDocVersions;

class ListImmManualMutus extends ListRecords
{
    use HandlesImmLampiran;
    use HandlesImmDocVersions;
    
    protected static string $resource = ImmManualMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Dokumen')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }


}
