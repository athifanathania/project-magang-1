<?php

namespace App\Livewire\Concerns;

use App\Models\Lampiran;
use Filament\Notifications\Notification;

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

        Notification::make()
            ->title($ok ? 'Versi lampiran dihapus' : 'Versi tidak ditemukan')
            ->{$ok ? 'success' : 'danger'}()
            ->send();

        // buka kembali modal lampiran pada record yg sama setelah refresh (opsional)
        $this->openLampiranForId = $m->berkas_id ?? null;

        $this->dispatch('$refresh');
    }

    /** === DIPANGGIL DARI JS (SAMAKAN DGN IMM) === */
    public function onDeleteImmVersion(array $payload): void
    {
        $id  = (int)($payload['lampiranId'] ?? $payload['id'] ?? 0);
        $idx = (int)($payload['index'] ?? -1);

        if (! $id || $idx < 0) {
            Notification::make()->title('Payload hapus versi tidak valid.')->danger()->send();
            return;
        }

        $this->handleDeleteLampiranVersion($id, $idx);
    }

    /** === DIPANGGIL DARI JS (SAMAKAN DGN IMM) === */
    public function updateVersionDescription(array $payload): void
    {
        $id   = (int)($payload['lampiranId'] ?? $payload['id'] ?? 0);
        $idx  = (int)($payload['index'] ?? -1);
        $desc = trim((string)($payload['description'] ?? ''));

        if (! $id || $idx < 0) {
            Notification::make()->title('Payload edit revisi tidak valid.')->danger()->send();
            return;
        }
        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $m = Lampiran::find($id);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        // Model Lampiran sudah punya helper
        $ok = $m->updateVersionDescription($idx, $desc);

        Notification::make()
            ->title($ok ? 'Deskripsi revisi diperbarui' : 'Versi tidak ditemukan')
            ->{$ok ? 'success' : 'danger'}()
            ->send();

        // supaya modal "Lampiran" kebuka lagi untuk record yang sama setelah refresh
        $this->openLampiranForId = $m->berkas_id ?? null;

        $this->dispatch('$refresh');
    }

    /** === KOMPAT: bila masih ada blade lama memanggil nama ini === */
    public function onDeleteLampiranVersion(array $payload): void
    {
        $this->onDeleteImmVersion($payload);
    }

    /** === KOMPAT: bila masih ada blade lama memanggil nama ini === */
    public function onLampiranUpdateVersionDesc(array $payload): void
    {
        $this->updateVersionDescription($payload);
    }
}
