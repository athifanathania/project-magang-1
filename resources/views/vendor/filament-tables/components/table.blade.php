@props([
    'footer' => null,
    'header' => null,
    'headerGroups' => null,
    'reorderable' => false,
    'reorderAnimationDuration' => 300,
])

<table
    {{ $attributes->class(['fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5']) }}
>
    @if ($header)
        <thead class="divide-y divide-gray-200 dark:divide-white/5">
            @if ($headerGroups)
                <tr class="bg-gray-100 dark:bg-transparent">
                    {{ $headerGroups }}
                </tr>
            @endif

            @php
                $resourceClass = method_exists($this, 'getResource') ? $this->getResource() : null;

                $supportsCaret = in_array($resourceClass, [
                    \App\Filament\Resources\BerkasResource::class,
                    \App\Filament\Resources\ImmManualMutuResource::class,
                    \App\Filament\Resources\ImmProsedurResource::class,
                    \App\Filament\Resources\ImmInstruksiStandarResource::class,
                    \App\Filament\Resources\ImmFormulirResource::class,
                    \App\Filament\Resources\RegularResource::class,
                ], true);

                $hasSelection = method_exists($this, 'isTableSelectionEnabled')
                    ? $this->isTableSelectionEnabled()
                    : false;

                // header caret hanya kalau BUTUH kolom sendiri (tidak ada selection)
                $showCaretHeaderCol = $supportsCaret && ! $hasSelection;
            @endphp

            <tr class="bg-gray-50 dark:bg-white/5">
                @if ($showCaretHeaderCol)
                    <th class="fi-ta-selection-cell w-1">
                        <div class="px-3 py-3"></div>
                    </th>
                @endif
                {{ $header }}
            </tr>

        </thead>
    @endif

    <tbody
        @if ($reorderable)
            x-on:end.stop="$wire.reorderTable($event.target.sortable.toArray())"
            x-sortable
            data-sortable-animation-duration="{{ $reorderAnimationDuration }}"
        @endif
        class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5"
    >
        {{ $slot }}
    </tbody>

    @if ($footer)
        <tfoot class="bg-gray-50 dark:bg-white/5">
            <tr>
                {{ $footer }}
            </tr>
        </tfoot>
    @endif
</table>
