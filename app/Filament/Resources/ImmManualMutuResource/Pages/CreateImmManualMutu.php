<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\CreateRecord;
use App\Livewire\Concerns\HandlesImmLampiran;

class CreateImmManualMutu extends CreateRecord
{
    use HandlesImmLampiran;
    protected static string $resource = ImmManualMutuResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
