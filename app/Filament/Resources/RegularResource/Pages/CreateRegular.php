<?php

namespace App\Filament\Resources\RegularResource\Pages;

use App\Filament\Resources\RegularResource;
use App\Filament\Resources\BerkasResource\Pages\CreateBerkas;
use Illuminate\Support\Facades\Storage;

class CreateRegular extends CreateBerkas
{
    protected static string $resource = RegularResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $rec = $this->record;
        $tmp = (string) ($rec->dokumen ?? '');

        if ($tmp !== '' && str_starts_with($tmp, 'regular/tmp/')) {
            $disk   = Storage::disk('private');
            if (! $disk->exists($tmp)) return;

            $dir    = 'regular/'.$rec->getKey();
            $name   = basename($tmp);
            $target = $dir.'/'.$name;

            $disk->makeDirectory($dir);
            $disk->move($tmp, $target);

            // seed REV00 (pola sama dengan Berkas)
            $rec->addVersionFromPath($target, basename($target), null, 'REV00');
        }

        // $this->redirect($this->getResource()::getUrl('index'), navigate: true);
    }
}
