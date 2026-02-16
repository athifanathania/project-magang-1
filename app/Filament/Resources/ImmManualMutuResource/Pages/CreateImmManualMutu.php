<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\CreateRecord;
use App\Livewire\Concerns\HandlesImmLampiran;

class CreateImmManualMutu extends CreateRecord
{
    use HandlesImmLampiran;
    protected static string $resource = ImmManualMutuResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\ImmManualMutu $rec */
        $rec = $this->record;

        $tmp = (string)($rec->file ?? '');
        if ($tmp === '') return;

        // base dir model IMM, mis: 'imm/manual-mutu'
        $base = rtrim($rec::storageBaseDir(), '/');

        // hanya bila masih di folder tmp
        if (str_starts_with($tmp, $base.'/tmp/')) {
            $disk   = \Storage::disk('private');
            if (! $disk->exists($tmp)) return;

            $dir    = $base.'/'.$rec->getKey();
            $name   = basename($tmp);
            $target = $dir.'/'.$name;

            $disk->makeDirectory($dir);
            $disk->move($tmp, $target);

            // Catat sebagai REV00
            $rec->addVersionFromPath($target, basename($target), null, 'REV00');
        }
    }

}
