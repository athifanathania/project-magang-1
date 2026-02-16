<?php

namespace App\Filament\Resources\EventCustomerResource\Pages;

use App\Filament\Resources\EventCustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventCustomer extends CreateRecord
{
    protected static string $resource = EventCustomerResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}