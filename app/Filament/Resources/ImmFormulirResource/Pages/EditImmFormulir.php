<?php // app/Filament/Resources/ImmFormulirResource/Pages/EditImmFormulir.php
namespace App\Filament\Resources\ImmFormulirResource\Pages;

use App\Filament\Resources\ImmFormulirResource;
use Filament\Resources\Pages\EditRecord;

class EditImmFormulir extends EditRecord
{
    protected static string $resource = ImmFormulirResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
