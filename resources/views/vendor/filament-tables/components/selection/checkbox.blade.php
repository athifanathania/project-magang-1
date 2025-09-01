<div class="flex items-center justify-center gap-2">
    @if ($attributes->has('value'))
        <button
            x-data="{ open: false }"
            x-on:click.stop="
                open = !open;
                $el.closest('tr').nextElementSibling?.classList.toggle('hidden')
            "
            x-bind:aria-expanded="open.toString()"
            class="flex items-center justify-center w-8 h-8 md:w-9 md:h-9
                   text-2xl md:text-[28px] leading-none select-none
                   text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded"
            type="button"
            title="Tampilkan / sembunyikan lampiran"
        >
            <span x-show="!open" aria-hidden="true">▸</span>
            <span x-show="open"  aria-hidden="true">▾</span>
        </button>
    @endif

    <label class="flex">
        <x-filament::input.checkbox
            :attributes="\Filament\Support\prepare_inherited_attributes($attributes)
                ->merge([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => implode(',', \Filament\Tables\Table::LOADING_TARGETS),
                ], escape: false)"
        />
        @if (filled($label))
            <span class="sr-only">{{ $label }}</span>
        @endif
    </label>
</div>
