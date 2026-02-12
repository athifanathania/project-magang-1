<?php

namespace App\Filament\Resources\EventCustomerResource\Pages;

use App\Filament\Resources\EventCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventCustomer extends EditRecord
{
    protected static string $resource = EventCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}