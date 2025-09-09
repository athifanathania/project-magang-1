<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Models\Lampiran;
use App\Filament\Resources\LampiranResource;
use Livewire\Attributes\On;

class LampiranViewer extends Component implements HasForms
{
    use InteractsWithForms;

    public ?Lampiran $record = null;
    public ?array $data = [];

    public function mount(): void
    {
        // tidak perlu apa-apa saat mount; record boleh null
        $this->form->fill([]);
    }

    public function form(Form $form): Form
    {
        return LampiranResource::form($form)
            ->model($this->record ?? Lampiran::make())
            ->statePath('data')
            ->operation('view');
    }

    #[On('open-lampiran-view')]   // ← DENGARKAN EVENT GLOBAL
    public function loadById(int $id): void
    {
        $this->record = Lampiran::with(['berkas','parent'])->findOrFail($id);
        $this->form->fill($this->record->attributesToArray());
    }

    public function render()
    {
        return view('livewire.lampiran-viewer');
    }
}
