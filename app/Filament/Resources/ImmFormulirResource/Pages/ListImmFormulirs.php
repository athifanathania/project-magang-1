<?php

namespace App\Filament\Resources\ImmFormulirResource\Pages;

use App\Filament\Resources\ImmFormulirResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

class ListImmFormulirs extends ListRecords
{
    protected static string $resource = ImmFormulirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Dokumen')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }

    public function handleDeleteImmLampiran(int $lampiranId, int $docId): void
    {
        $lampiran = ImmLampiran::query()
            ->whereKey($lampiranId)
            ->where('documentable_id', $docId)
            ->first();

        if (! $lampiran) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $lampiran->delete();
        $this->dispatch('$refresh');
        Notification::make()->title('Lampiran terhapus')->success()->send();
    }

    public function handleDeleteImmLampiranVersion(int $lampiranId, int $index): void
    {
        $m = ImmLampiran::find($lampiranId);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $m->deleteVersionAtIndex($index);
        $m->save();

        $this->dispatch('$refresh');
        Notification::make()->title('Versi lampiran dihapus')->success()->send();
    }
}
