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
    public function onDeleteImmVersionEvent(array $payload): void
    {
        $this->onDeleteImmVersion($payload);
    }

    #[On('imm-update-version-desc')]
    public function updateVersionDescription($payloadOrId = null, $index = null, $description = null): void
    {
        // SUPPORT 2 BENTUK PAYLOAD:
        // a) dispatch('imm-update-version-desc', { lampiranId: 1, index: 2, description: '...' })
        // b) call('updateVersionDescription', 1, 2, '...')

        if (is_array($payloadOrId)) {
            $description = $payloadOrId['description'] ?? $description;
            $index       = $payloadOrId['index']       ?? $index;
            $payloadOrId = $payloadOrId['lampiranId']  ?? null;
        }

        $id   = (int) $payloadOrId;
        $idx  = (int) $index;
        $desc = trim((string) ($description ?? ''));

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

        // Update hanya kolom deskripsi
        $versions[$idx]['description'] = $desc;
        $m->file_versions = array_values($versions);
        $m->save();

        // segarkan halaman list (panel lampiran ikut rerender karena di dalam page)
        $this->dispatch('$refresh');
        Notification::make()->title('Deskripsi revisi diperbarui')->success()->send();

        // Opsional: broadcast event untuk komponen viewer bila kamu ingin dia refresh sendiri
        // $this->dispatch('imm-version-updated', id: $m->id)->self();
    }
}
