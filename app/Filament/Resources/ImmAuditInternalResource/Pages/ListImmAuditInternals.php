<?php
// ListImmAuditInternals.php
namespace App\Filament\Resources\ImmAuditInternalResource\Pages;

use App\Filament\Resources\ImmAuditInternalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Livewire\Concerns\HandlesImmLampiran; 

class ListImmAuditInternals extends ListRecords
{
    use HandlesImmLampiran;
    
    protected static string $resource = ImmAuditInternalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Departemen')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin']) ?? false),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
