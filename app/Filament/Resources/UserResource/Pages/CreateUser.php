<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** Guard: hanya Admin boleh create */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);
        parent::mount();
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
