<?php // app/Filament/Resources/ImmProsedurResource/Pages/CreateImmProsedur.php
namespace App\Filament\Resources\ImmProsedurResource\Pages;

use App\Filament\Resources\ImmProsedurResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImmProsedur extends CreateRecord
{
    protected static string $resource = ImmProsedurResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
