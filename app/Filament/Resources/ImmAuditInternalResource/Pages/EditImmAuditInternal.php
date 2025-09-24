<?php

namespace App\Filament\Resources\ImmAuditInternalResource\Pages;

use App\Filament\Resources\ImmAuditInternalResource;
use Filament\Resources\Pages\EditRecord;
use App\Livewire\Concerns\HandlesImmLampiran;

class EditImmAuditInternal extends EditRecord
{
    use HandlesImmLampiran;

    protected static string $resource = ImmAuditInternalResource::class;
}
