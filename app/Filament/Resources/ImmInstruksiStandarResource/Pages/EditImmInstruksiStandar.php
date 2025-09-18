<?php // app/Filament/Resources/ImmInstruksiStandarResource/Pages/EditImmInstruksiStandar.php
namespace App\Filament\Resources\ImmInstruksiStandarResource\Pages;

use App\Filament\Resources\ImmInstruksiStandarResource;
use Filament\Resources\Pages\EditRecord;

class EditImmInstruksiStandar extends EditRecord
{
    protected static string $resource = ImmInstruksiStandarResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
