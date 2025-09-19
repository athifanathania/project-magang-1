<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\EditRecord;
use App\Livewire\Concerns\HandlesImmLampiran;

class EditImmManualMutu extends EditRecord
{
    use HandlesImmLampiran;
    protected static string $resource = ImmManualMutuResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
