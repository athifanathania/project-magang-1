<?php

namespace App\Filament\Resources\ImmFormulirResource\Pages;

use App\Filament\Resources\ImmFormulirResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImmFormulir extends CreateRecord
{
    protected static string $resource = ImmFormulirResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\ImmFormulir $rec */
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
