<?php
// app/Filament/Resources/ImmLampiranResource/Pages/CreateImmLampiran.php

namespace App\Filament\Resources\ImmLampiranResource\Pages;

use App\Filament\Resources\ImmLampiranResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateImmLampiran extends CreateRecord
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

    protected function getRedirectUrl(): string
    {
        // Pakai record yg baru dibuat (Livewire request tidak bawa query string).
        $rec = $this->record;

        if ($rec && ($res = $this->mapModelToResource($rec->documentable_type))) {
            return $res::getUrl(); // balik ke list IMM induk
        }

        // fallback aman
        return static::getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Lampiran tersimpan')->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // isi doc_type/doc_id dari query string
        if ($t = request('doc_type')) {
            $data['documentable_type'] = str_starts_with($t, 'App\\Models\\') ? $t : ('App\\Models\\' . $t);
        }
        if ($id = request('doc_id')) {
            $data['documentable_id'] = (int) $id;
        }

        // root harus NULL, bukan 0
        $parent = $data['parent_id'] ?? request('parent_id');
        $data['parent_id'] = ($parent && (int) $parent > 0) ? (int) $parent : null;

        return $data;
    }
}
