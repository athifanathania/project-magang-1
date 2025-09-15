<?php // app/Filament/Resources/ImmFormulirResource/Pages/CreateImmFormulir.php
namespace App\Filament\Resources\ImmFormulirResource\Pages;

use App\Filament\Resources\ImmFormulirResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImmFormulir extends CreateRecord
{
    protected static string $resource = ImmFormulirResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
