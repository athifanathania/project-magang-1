<?php // app/Filament/Resources/ImmInstruksiStandarResource/Pages/CreateImmInstruksiStandar.php
namespace App\Filament\Resources\ImmInstruksiStandarResource\Pages;

use App\Filament\Resources\ImmInstruksiStandarResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImmInstruksiStandar extends CreateRecord
{
    protected static string $resource = ImmInstruksiStandarResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
