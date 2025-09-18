<?php

namespace App\Livewire\Concerns;

use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HandlesImmLampiran
{
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

        if ($m->deleteVersionAtIndex($index)) {
            Notification::make()->title('Versi lampiran dihapus')->success()->send();
        } else {
            Notification::make()->title('Versi tidak ditemukan')->danger()->send();
        }

        $this->dispatch('$refresh');
    }

    public function onDeleteImmVersion(array $payload): void
    {
        $id    = (int) ($payload['lampiranId'] ?? 0);
        $index = (int) ($payload['index'] ?? -1);
        $this->handleDeleteImmLampiranVersion($id, $index);
    }

    #[On('imm-delete-version')]
    public function onDeleteImmVersionEvent($payload = null): void
    {
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?? [];
        }
        if (!is_array($payload)) $payload = [];

        $this->onDeleteImmVersion($payload); // <- method yang sudah ada
    }

    #[On('imm-update-version-desc')]
    public function updateVersionDescription(...$args): void
    {
        // Normalisasi argumen:
        // - Kalau dikirim {lampiranId, index, description} -> $args[0] adalah array
        // - Kalau dikirim 3 argumen terpisah -> $args = [id, index, description]

        $id = null; $idx = null; $desc = null;

        if (count($args) === 1 && is_array($args[0])) {
            $payload = $args[0];
            $id   = (int) ($payload['lampiranId'] ?? 0);
            $idx  = (int) ($payload['index'] ?? -1);
            $desc = trim((string) ($payload['description'] ?? ''));
        } elseif (count($args) >= 3) {
            $id   = (int) $args[0];
            $idx  = (int) $args[1];
            $desc = trim((string) ($args[2] ?? ''));
        }

        if (! $id || $idx < 0) {
            Notification::make()->title('Payload edit revisi tidak valid.')->danger()->send();
            return;
        }

        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $m = ImmLampiran::find($id);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $versions = $m->file_versions ?? [];
        if (! array_key_exists($idx, $versions)) {
            Notification::make()->title('Versi tidak ditemukan')->danger()->send();
            return;
        }

        // Update hanya deskripsi
        $versions[$idx]['description'] = $desc;
        $m->file_versions = array_values($versions);
        $m->save();

        $this->dispatch('$refresh');
        Notification::make()->title('Deskripsi revisi diperbarui')->success()->send();
    }

}
