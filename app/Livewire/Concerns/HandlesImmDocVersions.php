<?php

namespace App\Livewire\Concerns;

use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HandlesImmDocVersions
{
    protected function mapTypeToModel(string $type): ?string
    {
        return match ($type) {
            'manual-mutu'       => \App\Models\ImmManualMutu::class,
            'prosedur'          => \App\Models\ImmProsedur::class,
            'instruksi-standar' => \App\Models\ImmInstruksiStandar::class,
            'formulir'          => \App\Models\ImmFormulir::class,
            default             => null,
        };
    }

    #[On('imm-doc-delete-version')]
    public function onDocDeleteVersion(array $payload): void
    {
        $class = $this->mapTypeToModel((string)($payload['type'] ?? ''));
        $id    = (int)($payload['id'] ?? 0);
        $idx   = (int)($payload['index'] ?? -1);

        if (! $class || ! $id || $idx < 0) {
            Notification::make()->title('Payload hapus versi tidak valid.')->danger()->send();
            return;
        }

        $m = $class::find($id);
        if (! $m) { Notification::make()->title('Dokumen tidak ditemukan')->danger()->send(); return; }

        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send(); return;
        }

        $ok = $m->deleteVersionAtIndex($idx);
        Notification::make()->title($ok ? 'Versi dihapus' : 'Versi tidak ditemukan')->{$ok?'success':'danger'}()->send();
        $this->dispatch('$refresh');
    }

    #[On('imm-doc-update-version-desc')]
    public function onDocUpdateVersionDesc(array $payload): void
    {
        $class = $this->mapTypeToModel((string)($payload['type'] ?? ''));
        $id    = (int)($payload['id'] ?? 0);
        $idx   = (int)($payload['index'] ?? -1);
        $desc  = trim((string)($payload['description'] ?? ''));

        if (! $class || ! $id || $idx < 0) {
            Notification::make()->title('Payload edit revisi tidak valid.')->danger()->send();
            return;
        }

        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send(); return;
        }

        $m = $class::find($id);
        if (! $m) { Notification::make()->title('Dokumen tidak ditemukan')->danger()->send(); return; }

        $ok = method_exists($m,'updateVersionDescription')
            ? $m->updateVersionDescription($idx, $desc)
            : (function() use ($m,$idx,$desc) {
                // fallback jika helper tidak ada (mestinya ada di trait)
                $versions = $m->file_versions ?? [];
                if (!array_key_exists($idx, $versions)) return false;
                $versions[$idx]['description'] = $desc;
                $m->file_versions = array_values($versions);
                return $m->save();
            })();

        $this->dispatch('$refresh');
        Notification::make()->title('Deskripsi revisi diperbarui')->success()->send();
    }
}
