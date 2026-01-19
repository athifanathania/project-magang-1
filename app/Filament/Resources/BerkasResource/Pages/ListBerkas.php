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

        activity()
            ->causedBy(auth()->user())
            ->event('view')
            ->withProperties([
                // Kunci ini HARUS sama dengan yang di ActivityLogResource ('object_label')
                'object_label' => 'Dokumen Eksternal # Halaman Event', 
                'url' => request()->fullUrl(),       // URL Halaman
                'ip' => request()->ip(),             // Alamat IP User
                'user_agent' => request()->userAgent()
            ])
            ->log('Melihat Halaman Daftar Event');

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

    public function handleDeleteLampiran(
        int $lampiranId,
        int $ownerId,
        string $source = 'panel'
    ): void {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        // pastikan lampiran ditemukan & memang milik owner (berkas atau regular)
        $lampiran = \App\Models\Lampiran::query()
            ->whereKey($lampiranId)
            ->where(function ($q) use ($ownerId) {
                $q->where('berkas_id', $ownerId)
                ->orWhere('regular_id', $ownerId);
            })
            ->first();

        if (! $lampiran) {
            \Filament\Notifications\Notification::make()
                ->title('Lampiran tidak ditemukan atau bukan milik dokumen ini')
                ->danger()->send();
            return;
        }

        $lampiran->delete();

        \Filament\Notifications\Notification::make()
            ->title('Lampiran dihapus')->success()->send();

        $this->dispatch('refreshLampiranPanel'); // (opsional) kalau ada listener
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
