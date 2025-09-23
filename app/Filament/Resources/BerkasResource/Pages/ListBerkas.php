<?php

namespace App\Filament\Resources\BerkasResource\Pages;

use App\Filament\Resources\BerkasResource;
use App\Models\Berkas;
use App\Models\Lampiran;                      
use Filament\Actions;
use Filament\Notifications\Notification;      
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;              
use App\Livewire\Concerns\HandlesImmDocVersions;

class ListBerkas extends ListRecords
{
    protected static string $resource = BerkasResource::class;
    use HandlesImmDocVersions;

    /** dipakai untuk auto-buka modal setelah create lampiran */
    protected ?int $openLampiranForId = null;

    public function mount(): void
    {
        parent::mount();

        if (request()->boolean('openLampiran') && ($id = (int) request('berkas_id'))) {
            // simpan dulu; table belum siap di sini
            $this->openLampiranForId = $id;
        }
    }

    /** WAJIB public, serta panggil parent agar $table terinisialisasi */
    public function bootedInteractsWithTable(): void
    {
        parent::bootedInteractsWithTable();   // penting!

        if (! $this->openLampiranForId) {
            return;
        }

        if ($record = Berkas::find($this->openLampiranForId)) {
            // nama action harus sama dengan yang di table(): Action::make('lampiran')
            $this->mountTableAction('lampiran', $record);
        }

        $this->openLampiranForId = null; // sekali pakai
    }

    /** Dipanggil dari blade via $wire.handleDeleteLampiran(...) */
    public function handleDeleteLampiran(int $lampiranId, int $berkasId, string $source = 'panel'): void
    {
        // (opsional) otorisasi: pastikan user boleh hapus
        // $this->authorize('lampiran.delete');

        $lampiran = Lampiran::query()
            ->whereKey($lampiranId)
            ->where('berkas_id', $berkasId)
            ->first();

        if (! $lampiran) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        // Model Lampiran kamu sudah punya hook deleting() yang menghapus anak-anak.
        $lampiran->delete();

        // refresh UI panel/tabel
        $this->dispatch('$refresh');

        Notification::make()
            ->title('Lampiran terhapus')
            ->body('Lampiran beserta semua subnya telah dihapus.')
            ->success()
            ->send();
    }
    
    /** Hapus satu versi file riwayat (dipanggil dari blade history via $wire.handleDeleteLampiranVersion) */
    #[On('delete-lampiran-version')]
    public function handleDeleteLampiranVersion(int $lampiranId, int $index): void
    {
        if ($m = \App\Models\Lampiran::find($lampiranId)) {
            if ($m->deleteVersionAtIndex($index)) {
                \Filament\Notifications\Notification::make()
                    ->title('Versi lampiran dihapus')->success()->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Versi tidak ditemukan')->danger()->send();
            }
        } else {
            \Filament\Notifications\Notification::make()
                ->title('Lampiran tidak ditemukan')->danger()->send();
        }

        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [ Actions\CreateAction::make() ];
    }

    /** Dipanggil dari Blade: window.Livewire.find(pageId).call('onDocDeleteVersion', payload) */
    public function onDocDeleteVersion(array $payload): void
    {
        $type  = (string)($payload['type'] ?? 'berkas');
        $id    = (int)   ($payload['id']   ?? 0);
        $index = (int)   ($payload['index']?? -1);
        $path  = (string)($payload['path'] ?? '');

        try {
            if ($type !== 'berkas') {
                throw new \RuntimeException('Tipe tidak dikenali.');
            }

            $berkas = Berkas::findOrFail($id);

            // Prioritas: tembak index berdasarkan path dari Blade (dukung legacy `path`)
            if ($path !== '') {
                $idxByPath = $berkas->versionsList()->search(
                    fn ($v) => (string)($v['file_path'] ?? $v['path'] ?? '') === (string)$path
                );
                if ($idxByPath !== false) {
                    $index = (int) $idxByPath;
                }
            }

            $ok = $berkas->deleteVersionAtIndex($index);

            if ($ok) {
                Notification::make()->title('Versi dihapus')->success()->send();
            } else {
                Notification::make()->title('Gagal menghapus versi')->danger()->send();
            }

            // refresh UI
            $this->dispatch('$refresh');

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal menghapus versi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    #[On('lampiran-update-version-desc')]
    public function onLampiranUpdateVersionDesc(array $payload): void
    {
        $id   = (int)($payload['id'] ?? $payload['lampiranId'] ?? 0);
        $idx  = (int)($payload['index'] ?? -1);
        $desc = (string)($payload['description'] ?? '');

        try {
            if ($id <= 0 || $idx < 0) {
                \Filament\Notifications\Notification::make()->title('Payload tidak valid')->danger()->send();
                return;
            }

            $m = \App\Models\Lampiran::find($id);
            if (! $m) {
                \Filament\Notifications\Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
                return;
            }

            $ok = $m->updateVersionDescription($idx, $desc);

            \Filament\Notifications\Notification::make()
                ->title($ok ? 'Deskripsi revisi diperbarui' : 'Versi tidak ditemukan')
                ->{$ok ? 'success' : 'danger'}()
                ->send();

            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('Gagal menyimpan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
