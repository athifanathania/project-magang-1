<?php

namespace App\Livewire\Concerns;

use App\Models\Lampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HandlesLampiran
{
    public function handleDeleteLampiranVersion(int $lampiranId, int $index): void
    {
        if (! $lampiranId || $index < 0) {
            Notification::make()->title('Payload hapus versi tidak valid.')->danger()->send();
            return;
        }
        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $m = Lampiran::find($lampiranId);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $ok = $m->deleteVersionAtIndex($index);

        \Filament\Notifications\Notification::make()
            ->title($ok ? 'Versi lampiran dihapus' : 'Versi tidak ditemukan')
            ->{$ok ? 'success' : 'danger'}()
            ->send();

        // Optimistic UI (untuk Alpine)
        $this->dispatch('lampiran-history-changed', lampiranId: (int) $m->getKey(), index: (int) $index, description: null);

        // >>> INI KUNCI: re-render komponen (AJAX), jangan skip
        $this->dispatch('$refresh');
    }

    public function handleUpdateLampiranVersionDesc(int $lampiranId, int $index, string $description = ''): void
    {
        if (! $lampiranId || $index < 0) {
            Notification::make()->title('Payload edit revisi tidak valid.')->danger()->send();
            return;
        }
        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $m = Lampiran::find($lampiranId);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $ok = $m->updateVersionDescription($index, $description);

        \Filament\Notifications\Notification::make()
            ->title($ok ? 'Deskripsi revisi diperbarui' : 'Versi tidak ditemukan')
            ->{$ok ? 'success' : 'danger'}()
            ->send();

        $this->dispatch('lampiran-history-changed', lampiranId: (int) $m->getKey(), index: (int) $index, description: (string) $description);

        // Boleh refresh juga supaya state server & DOM selalu sinkron
        $this->dispatch('$refresh');
    }

    // Livewire v3 events (tetap)
    #[On('lampiran-delete-version')]
    public function onDeleteLampiranVersion(int $lampiranId, int $index): void
    {
        $this->handleDeleteLampiranVersion($lampiranId, $index);
    }

    #[On('lampiran-update-version-desc')]
    public function onLampiranUpdateVersionDesc(int $lampiranId, int $index, string $description = ''): void
    {
        $this->handleUpdateLampiranVersionDesc($lampiranId, $index, $description);
    }

    // Kompat lama (boleh tetap, tidak mengganggu)
    public function onDeleteImmVersion(array $payload): void
    {
        $this->handleDeleteLampiranVersion(
            (int)($payload['lampiranId'] ?? $payload['id'] ?? 0),
            (int)($payload['index'] ?? -1),
        );
    }

    public function updateVersionDescription(array $payload): void
    {
        $this->handleUpdateLampiranVersionDesc(
            (int)($payload['lampiranId'] ?? $payload['id'] ?? 0),
            (int)($payload['index'] ?? -1),
            trim((string)($payload['description'] ?? '')),
        );
    }
}
