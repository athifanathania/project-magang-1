<?php

namespace App\Filament\Resources\ImmLampiranResource\Pages;

use App\Filament\Resources\ImmLampiranResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditImmLampiran extends EditRecord
{
    protected static string $resource = ImmLampiranResource::class;

    private function normalizeDocType(?string $type): ?string
    {
        if (! $type) return null;
        $type = ltrim($type, '\\');

        if (class_exists($type)) return $type;

        $fqcn = 'App\\Models\\' . $type;
        return class_exists($fqcn) ? $fqcn : null;
    }

    private function mapModelToResource(?string $model): ?string
    {
        $m = $this->normalizeDocType($model);

        return match ($m) {
            \App\Models\ImmManualMutu::class       => \App\Filament\Resources\ImmManualMutuResource::class,
            \App\Models\ImmProsedur::class         => \App\Filament\Resources\ImmProsedurResource::class,
            \App\Models\ImmInstruksiStandar::class => \App\Filament\Resources\ImmInstruksiStandarResource::class,
            \App\Models\ImmFormulir::class         => \App\Filament\Resources\ImmFormulirResource::class,
            \App\Models\ImmAuditInternal::class    => \App\Filament\Resources\ImmAuditInternalResource::class,
            default => null,
        };
    }

    public function mount($record): void
    {
        parent::mount($record);

        if (request()->boolean('missingFile')) {
            Notification::make()
                ->title('Silakan tambahkan file')
                ->warning()
                ->send();
        }
    }

    private function backUrl(): string
    {
        $type = $this->record?->documentable_type
            ?? request('documentable_type')
            ?? request('doc_type');

        if ($res = $this->mapModelToResource($type)) {
            // bisa juga kirim parameter jika mau auto-buka sesuatu
            return $res::getUrl('index');
        }

        return url()->previous() ?: route('filament.admin.pages.dashboard');
    }

    protected function getRedirectUrl(): string
    {
        return $this->backUrl();
    }

    protected function afterSave(): void
    {
        $this->redirect($this->backUrl(), navigate: true);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Perubahan tersimpan')->success();
    }
    
    public function getTitle(): string
    {
        $type = ltrim((string)($this->record->documentable_type ?? ''), '\\');

        return str_contains($type, 'ImmAuditInternal')
            ? 'Edit Temuan Audit'
            : 'Edit Imm Lampiran';
    }

    // optional: biar H1 & breadcrumb ikut berubah juga
    public function getHeading(): string
    {
        return $this->getTitle();
    }

    public function getBreadcrumb(): string
    {
        return $this->getTitle();
    }
}
