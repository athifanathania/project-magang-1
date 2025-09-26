<x-filament::page>
    <form wire:submit.prevent="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-2">
            <x-filament::button type="submit">
                Simpan Perubahan
            </x-filament::button>

            {{-- Tombol Batal: kembali ke halaman sebelumnya --}}
            <x-filament::button
                tag="a"
                color="gray"
                href="{{ $this->backUrl ?: route('filament.admin.pages.dashboard') }}"
                wire:navigate
            >
                Batal
            </x-filament::button>
        </div>
    </form>
</x-filament::page>
