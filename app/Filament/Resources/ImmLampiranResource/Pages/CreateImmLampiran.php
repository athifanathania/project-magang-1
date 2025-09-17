<?php

namespace App\Filament\Resources\ImmLampiranResource\Pages;

use App\Filament\Resources\ImmLampiranResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateImmLampiran extends CreateRecord
{
    protected static string $resource = ImmLampiranResource::class;

    /** Normalisasi: terima FQCN atau short name, kembalikan FQCN yang valid */
    private function normalizeDocType(?string $type): ?string
    {
        if (! $type) return null;
        $type = ltrim($type, '\\');

        if (class_exists($type)) return $type;

        $fqcn = 'App\\Models\\' . $type;
        return class_exists($fqcn) ? $fqcn : null;
    }

    /** Map model -> resource IMM (pakai FQCN) */
    private function mapModelToResource(?string $model): ?string
    {
        $m = $this->normalizeDocType($model);

        return match ($m) {
            \App\Models\ImmManualMutu::class       => \App\Filament\Resources\ImmManualMutuResource::class,
            \App\Models\ImmProsedur::class         => \App\Filament\Resources\ImmProsedurResource::class,
            \App\Models\ImmInstruksiStandar::class => \App\Filament\Resources\ImmInstruksiStandarResource::class,
            \App\Models\ImmFormulir::class         => \App\Filament\Resources\ImmFormulirResource::class,
            default => null,
        };
    }

    private function backUrl(): string
    {
        $type = $this->record?->documentable_type
            ?? request('documentable_type')
            ?? request('doc_type');

        if ($res = $this->mapModelToResource($type)) {
            // kalau mau, bisa kirim parameter agar auto-buka panel, contoh:
            // return $res::getUrl('index', ['openLampiran' => 1, 'doc_id' => ($this->record->documentable_id ?? request('doc_id'))]);
            return $res::getUrl('index');
        }

        // fallback yang lebih aman dari dashboard:
        return url()->previous() ?: route('filament.admin.pages.dashboard');
    }

    protected function getRedirectUrl(): string
    {
        return $this->backUrl();
    }

    protected function afterCreate(): void
    {
        $this->redirect($this->backUrl(), navigate: true);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Lampiran tersimpan')->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($t = request('doc_type')) {
            $data['documentable_type'] = $this->normalizeDocType($t) ?? $t;
        }
        if ($id = request('doc_id')) {
            $data['documentable_id'] = (int) $id;
        }

        $parent = $data['parent_id'] ?? request('parent_id');
        $data['parent_id'] = ($parent && (int) $parent > 0) ? (int) $parent : null;

        return $data;
    }
}
