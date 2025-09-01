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

    /** Dipanggil oleh tombol 🗑️ di panel/modal (event dari Blade: $dispatch('delete-lampiran', …)) */
    #[On('delete-lampiran')]
    public function handleDeleteLampiran(int $id, int $berkasId, string $source = 'panel'): void
    {
        if ($m = Lampiran::find($id)) {
            $m->delete();
        }

        Notification::make()->title('Lampiran dihapus')->success()->send();

        if ($source === 'modal') {
            if ($record = Berkas::find($berkasId)) {
                $this->mountTableAction('lampiran', $record);
            }
        } else {
            $this->dispatch('$refresh'); // v3 (v2: $this->emitSelf('$refresh'))
        }

        $this->js(<<<'JS'
            document.documentElement.classList.remove('overflow-y-hidden','fi-modal-open');
            document.body.classList.remove('overflow-y-hidden');
            document.documentElement.style.removeProperty('overflow');
            document.body.style.removeProperty('overflow');
        JS);

        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [ Actions\CreateAction::make() ];
    }

    

}
