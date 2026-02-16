<?php
// CreateImmAuditInternal.php
namespace App\Filament\Resources\ImmAuditInternalResource\Pages;

use App\Filament\Resources\ImmAuditInternalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImmAuditInternal extends CreateRecord
{
    protected static string $resource = ImmAuditInternalResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
