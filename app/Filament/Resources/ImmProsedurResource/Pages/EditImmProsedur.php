<?php // app/Filament/Resources/ImmProsedurResource/Pages/EditImmProsedur.php
namespace App\Filament\Resources\ImmProsedurResource\Pages;

use App\Filament\Resources\ImmProsedurResource;
use Filament\Resources\Pages\EditRecord;
use App\Livewire\Concerns\HandlesImmLampiran;

class EditImmProsedur extends EditRecord
{
    use HandlesImmLampiran;
    protected static string $resource = ImmProsedurResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
