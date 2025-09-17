<?php

namespace App\Filament\Resources\ImmInstruksiStandarResource\Pages;

use App\Filament\Resources\ImmInstruksiStandarResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use App\Livewire\Concerns\HandlesImmLampiran;

class ListImmInstruksiStandars extends ListRecords
{
    use HandlesImmLampiran;

    protected static string $resource = ImmInstruksiStandarResource::class;

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

    #[On('imm-delete-version')]
    public function deleteImmVersionListener($lampiranId = null, $index = null): void
    {
        if (is_array($lampiranId)) {
            // kalau dipanggil dengan array tunggal
            $index      = $lampiranId['index']      ?? $index;
            $lampiranId = $lampiranId['lampiranId'] ?? null;
        }

        $id  = (int) $lampiranId;
        $idx = (int) $index;

        if (! $id || $idx < 0) {
            Notification::make()->title('Payload penghapusan tidak valid.')->danger()->send();
            return;
        }

        $m = ImmLampiran::find($id);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        if ($m->deleteVersionAtIndex($idx)) {
            Notification::make()->title('Versi lampiran dihapus')->success()->send();
        } else {
            Notification::make()->title('Versi tidak ditemukan')->danger()->send();
        }

        $this->dispatch('$refresh'); // segarkan tabel/panel
    }
}
