<?php
// app/Filament/Resources/ImmLampiranResource/Pages/EditImmLampiran.php

namespace App\Filament\Resources\ImmLampiranResource\Pages;

use App\Filament\Resources\ImmLampiranResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditImmLampiran extends EditRecord
{
    protected static string $resource = ImmLampiranResource::class;

    /** Map model -> resource IMM */
    private function mapModelToResource(?string $model): ?string
    {
        return match ($model) {
            \App\Models\ImmManualMutu::class       => \App\Filament\Resources\ImmManualMutuResource::class,
            \App\Models\ImmProsedur::class         => \App\Filament\Resources\ImmProsedurResource::class,
            \App\Models\ImmInstruksiStandar::class => \App\Filament\Resources\ImmInstruksiStandarResource::class,
            \App\Models\ImmFormulir::class         => \App\Filament\Resources\ImmFormulirResource::class,
            default => null,
        };
    }

    public function mount($record): void
    {
        parent::mount($record);

        // tampilkan hint kalau datang dari "Tambahkan file"
        if (request()->boolean('missingFile')) {
            Notification::make()
                ->title('Silakan tambahkan file lampiran')
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        $rec = $this->record;

        if ($rec && ($res = $this->mapModelToResource($rec->documentable_type))) {
            return $res::getUrl(); // balik ke list IMM induk
        }

        // fallback aman
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Perubahan tersimpan')->success();
    }
}
