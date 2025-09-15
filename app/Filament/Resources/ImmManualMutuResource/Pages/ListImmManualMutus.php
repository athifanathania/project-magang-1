<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;

class ListImmManualMutus extends ListRecords
{
    protected static string $resource = ImmManualMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Dokumen')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }

    // Dipanggil dari blade panel IMM: window.Livewire.find(pageId).call('handleDeleteImmLampiran', ...)
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

    // Dipanggil dari blade panel IMM: window.Livewire.find(pageId).call('handleDeleteImmLampiranVersion', ...)
    public function handleDeleteImmLampiranVersion(int $lampiranId, int $index): void
    {
        $m = ImmLampiran::find($lampiranId);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        // Model menghapus file & merapikan array
        $m->deleteVersionAtIndex($index);
        $m->save();

        $this->dispatch('$refresh');
        Notification::make()->title('Versi lampiran dihapus')->success()->send();
    }
}
