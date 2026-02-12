@props([
    'alpineHidden' => null,
    'alpineSelected' => null,
    'recordAction' => null,
    'recordUrl' => null,
    'striped' => false,
    'record' => null,
    'recordKey' => null,
])

@php
    $hasAlpineHiddenClasses = filled($alpineHidden);
    $hasAlpineSelectedClasses = filled($alpineSelected);    
    $stripedClasses = 'bg-gray-50 dark:bg-white/5';

    /** @var null|\Illuminate\Database\Eloquent\Model $record */
    $record = $record ?: ($recordKey && method_exists($this, 'getTableRecord')
        ? $this->getTableRecord($recordKey)
        : $record);

    $isPublic = optional(\Filament\Facades\Filament::getCurrentPanel())->getId() === 'public';

    $resourceClass = method_exists($this, 'getResource') ? $this->getResource() : null;

    $supportsCaret = in_array($resourceClass, [
        \App\Filament\Resources\BerkasResource::class,
        \App\Filament\Resources\ImmManualMutuResource::class,
        \App\Filament\Resources\ImmProsedurResource::class,
        \App\Filament\Resources\ImmInstruksiStandarResource::class,
        \App\Filament\Resources\ImmFormulirResource::class,
        \App\Filament\Resources\RegularResource::class,
        \App\Filament\Resources\EventCustomerResource::class,
    ], true);

    $hasSelection = method_exists($this, 'isTableSelectionEnabled')
        ? $this->isTableSelectionEnabled()
        : false;

    // butuh kolom caret sendiri hanya kalau tidak ada selection
    $needsOwnCaretCol = $supportsCaret;

    // flag halaman IMM (punyamu sebelumnya)...
    $isImm = in_array($resourceClass, [
        \App\Filament\Resources\ImmManualMutuResource::class,
        \App\Filament\Resources\ImmProsedurResource::class,
        \App\Filament\Resources\ImmInstruksiStandarResource::class,
        \App\Filament\Resources\ImmFormulirResource::class,
    ], true);
    if (! $isImm && $record) {
        $isImm = $record instanceof \App\Models\ImmManualMutu
            || $record instanceof \App\Models\ImmProsedur
            || $record instanceof \App\Models\ImmInstruksiStandar
            || $record instanceof \App\Models\ImmFormulir;
    }
@endphp

<tr
    @if ($hasAlpineHiddenClasses || $hasAlpineSelectedClasses)
        x-bind:class="{
            {{ $hasAlpineHiddenClasses ? "'hidden': {$alpineHidden}," : null }}
            {{ $hasAlpineSelectedClasses && (! $striped) ? "'{$stripedClasses}': {$alpineSelected}," : null }}
            {{ $hasAlpineSelectedClasses ? "'[&>*:first-child]:relative [&>*:first-child]:before:absolute [&>*:first-child]:before:start-0 [&>*:first-child]:before:inset-y-0 [&>*:first-child]:before:w-0.5 [&>*:first-child]:before:bg-primary-600 [&>*:first-child]:dark:before:bg-primary-500': {$alpineSelected}," : null }}
        }"
    @endif
    {{
        $attributes->class([
            'fi-ta-row [@media(hover:hover)]:transition [@media(hover:hover)]:duration-75',
            'hover:bg-gray-50 dark:hover:bg-white/5' => $recordAction || $recordUrl,
            $stripedClasses => $striped,
        ])
    }}
>
    @if ($needsOwnCaretCol)
        <td class="fi-ta-selection-cell px-3 py-4">
        @include('tables.partials.caret-button')
    </td>
    @endif

    {{-- sel-sel kolom asli --}}
    {{ $slot }}
</tr>

{{-- Baris expandable (panel lampiran) --}}
<tr class="hidden" x-data>
    @if ($needsOwnCaretCol)
        <td class="px-3 py-3"></td>
    @endif
    <td
        x-bind:colspan="
            Math.max(
                1,
                $el.closest('tr').previousElementSibling.children.length - {{ $needsOwnCaretCol ? 1 : 0 }}
            )
        "
        class="px-3 py-3"
    >
        @php $lampirans = optional($record)->lampirans ?? collect(); @endphp
        @if ($isImm)
            @include('tables.rows.imm-lampirans-panel-plain', ['record' => $record])
        @else
            @include('tables.rows.lampirans-panel-plain', ['record' => $record, 'lampirans' => $lampirans])
        @endif
    </td>
</tr>
