<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Models\ImmLampiran;
use App\Filament\Resources\ImmLampiranResource;
use Livewire\Attributes\On;

class ImmLampiranViewer extends Component implements HasForms
{
    use InteractsWithForms;
    use \App\Livewire\Concerns\HandlesImmLampiran;

    public ?ImmLampiran $record = null;
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function form(Form $form): Form
    {
        return ImmLampiranResource::form($form)
            ->model($this->record ?? ImmLampiran::make())
            ->statePath('data')
            ->operation('view'); // view-mode -> semua field readonly
    }

    #[On('open-imm-lampiran-view')]
    public function loadById(int $id): void
    {
        $this->record = ImmLampiran::with(['parent','children'])->findOrFail($id);
        $this->form->fill($this->record->attributesToArray());
    }

    public function render()
    {
        // PENTING: view ini harus ada (lihat langkah 1)
        return view('livewire.imm-lampiran-viewer');
    }
}
