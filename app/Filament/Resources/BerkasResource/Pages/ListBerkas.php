<?php

namespace App\Filament\Resources\BerkasResource\Pages;

use App\Filament\Resources\BerkasResource;
use App\Models\Berkas;
use App\Models\Lampiran;                      // + tambah
use Filament\Actions;
use Filament\Notifications\Notification;      // + tambah
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\On;              

class ListBerkas extends ListRecords
{
    protected static string $resource = BerkasResource::class;

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
        if ($m = Lampiran::find($lampiranId)) {
            if ($m->deleteVersionAtIndex($index)) {
                Notification::make()->title('Versi lampiran dihapus')->success()->send();
            } else {
                Notification::make()->title('Versi tidak ditemukan')->danger()->send();
            }
        } else {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
        }

        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [ Actions\CreateAction::make() ];
    }
}
