<x-filament::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">
                Simpan Perubahan
            </x-filament::button>
        </div>
    </form>
</x-filament::page>
