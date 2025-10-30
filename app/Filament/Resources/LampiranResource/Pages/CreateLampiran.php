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
        $berkasId  = request('berkas_id')  ?? data_get($this->record, 'berkas_id');
        $regularId = request('regular_id') ?? data_get($this->record, 'regular_id');

        if ($berkasId) {
            return \App\Filament\Resources\BerkasResource::getUrl('index', [
                'openLampiran' => 1,
                'berkas_id'    => $berkasId,
            ]);
        }

        if ($regularId) {
            return \App\Filament\Resources\RegularResource::getUrl('index');
        }

        return \App\Filament\Resources\BerkasResource::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Lampiran tersimpan')
            ->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return \App\Filament\Resources\LampiranResource::normalizeOwner($data);
    }
}
