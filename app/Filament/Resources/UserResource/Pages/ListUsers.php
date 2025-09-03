<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /** Guard: hanya Admin boleh membuka halaman list */
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => auth()->check() && auth()->user()->hasRole('Admin')),
        ];
    }
}
