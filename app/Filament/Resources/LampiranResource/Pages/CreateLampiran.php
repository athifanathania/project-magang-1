<?php

namespace App\Filament\Resources\LampiranResource\Pages;

use App\Filament\Resources\LampiranResource;
use App\Filament\Resources\BerkasResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateLampiran extends CreateRecord
{
    protected static string $resource = LampiranResource::class;

    protected function getRedirectUrl(): string
    {
        // id berkas asal (dari query atau dari record yang baru dibuat)
        $berkasId = request('berkas_id') ?? data_get($this->record, 'berkas_id');

        // balik ke list Dokumen + minta auto-buka modal Lampiran untuk berkas tsb
        if ($berkasId) {
            return BerkasResource::getUrl('index', [
                'openLampiran' => 1,
                'berkas_id'    => $berkasId,
            ]);
        }

        // fallback: balik ke list Dokumen saja
        return BerkasResource::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Lampiran tersimpan')
            ->success();
    }
}
