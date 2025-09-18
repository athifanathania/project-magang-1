<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\EditRecord;

class EditImmManualMutu extends EditRecord
{
    protected static string $resource = ImmManualMutuResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
