<?php

namespace App\Filament\Resources\BerkasResource\Pages;

use App\Filament\Resources\BerkasResource;
use App\Models\Berkas;
use App\Models\Lampiran;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Livewire\Concerns\HandlesLampiran;

class ListBerkas extends ListRecords
{
    protected static string $resource = BerkasResource::class;

    use HandlesLampiran;

    /** dipakai untuk auto-buka modal setelah aksi versi */
    public ?int $openLampiranForId = null;

    public function mount(): void
    {
        parent::mount();

        if (request()->boolean('openLampiran') && ($id = (int) request('berkas_id'))) {
            $this->openLampiranForId = $id;
        }
    }

    /** WAJIB public + panggil parent supaya $table siap */
    public function bootedInteractsWithTable(): void
    {
        parent::bootedInteractsWithTable();

        if (! $this->openLampiranForId) return;

        if ($record = Berkas::find($this->openLampiranForId)) {
            // nama action harus sama dg Table Action: Action::make('lampiran')
            $this->mountTableAction('lampiran', $record);
        }

        $this->openLampiranForId = null; // sekali pakai
    }

    protected function getHeaderActions(): array
    {
        return [ Actions\CreateAction::make() ];
    }

    /** Dipanggil dari blade via $wire.handleDeleteLampiran(...) */
    public function handleDeleteLampiran(int $lampiranId, int $berkasId, string $source = 'panel'): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $lampiran = Lampiran::query()
            ->whereKey($lampiranId)
            ->where('berkas_id', $berkasId)
            ->first();

        if (! $lampiran) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $lampiran->delete();

        Notification::make()
            ->title('Lampiran terhapus')
            ->body('Lampiran beserta semua subnya telah dihapus.')
            ->success()
            ->send();
    }

    /** (tetap ada utk versi dokumen berkas) */
    #[\Livewire\Attributes\On('doc-delete-version')]
    public function onDocDeleteVersion(
        string $type = 'berkas',
        int $id = 0,
        int $index = -1,
        string $path = ''
    ): void {
        try {
            if ($type !== 'berkas') {
                throw new \RuntimeException('Tipe tidak dikenali.');
            }

            $berkas = Berkas::findOrFail($id);

            if ($path !== '') {
                $idxByPath = $berkas->versionsList()->search(
                    fn ($v) => (string)($v['file_path'] ?? $v['path'] ?? '') === (string)$path
                );
                if ($idxByPath !== false) {
                    $index = (int) $idxByPath;
                }
            }

            $ok = $berkas->deleteVersionAtIndex($index);

            Notification::make()
                ->title($ok ? 'Versi dihapus' : 'Gagal menghapus versi')
                ->{$ok ? 'success' : 'danger'}()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal menghapus versi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
