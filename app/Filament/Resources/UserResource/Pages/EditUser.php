<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** Guard: hanya Admin boleh edit */
    public function mount(int|string $record): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);
        parent::mount($record);
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
