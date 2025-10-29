<?php

namespace App\Filament\Resources\ImmInstruksiStandarResource\Pages;

use App\Filament\Resources\ImmInstruksiStandarResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImmInstruksiStandar extends CreateRecord
{
    protected static string $resource = ImmInstruksiStandarResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\ImmInstruksiStandar $rec */
        $rec = $this->record;

        $tmp = (string) ($rec->file ?? '');
        if ($tmp === '') return;

        $base = rtrim($rec::storageBaseDir(), '/');

        if (str_starts_with($tmp, $base.'/tmp/')) {
            $disk   = \Storage::disk('private');
            if (! $disk->exists($tmp)) return;

            $dir    = $base.'/'.$rec->getKey();
            $name   = basename($tmp);
            $target = $dir.'/'.$name;

            $disk->makeDirectory($dir);
            $disk->move($tmp, $target);

            $rec->addVersionFromPath($target, basename($target), null, 'REV00');
        }
    }
}
