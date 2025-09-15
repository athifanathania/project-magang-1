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
    
    $showCaret = in_array($resourceClass, [
        \App\Filament\Resources\BerkasResource::class,
        \App\Filament\Resources\ImmManualMutuResource::class,
        \App\Filament\Resources\ImmProsedurResource::class,
        \App\Filament\Resources\ImmInstruksiStandarResource::class,
        \App\Filament\Resources\ImmFormulirResource::class,
    ], true);

    // ✅ Tambahkan ini: penanda apakah ini halaman IMM
    $isImm = in_array($resourceClass, [
        \App\Filament\Resources\ImmManualMutuResource::class,
        \App\Filament\Resources\ImmProsedurResource::class,
        \App\Filament\Resources\ImmInstruksiStandarResource::class,
        \App\Filament\Resources\ImmFormulirResource::class,
    ], true);

    // fallback kalau dipanggil di konteks lain: cek tipe model record
    if (! $isImm && $record) {
        $isImm =
            $record instanceof \App\Models\ImmManualMutu ||
            $record instanceof \App\Models\ImmProsedur ||
            $record instanceof \App\Models\ImmInstruksiStandar ||
            $record instanceof \App\Models\ImmFormulir;
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
    {{-- ⬇️ Caret kiri untuk VIEWER – markup & kelas SAMA persis seperti di selection/checkbox.blade.php --}}
    @if ($showCaret)
        <td class="fi-ta-selection-cell px-3 py-4">
            <div class="flex items-center justify-center gap-2">
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
                    <span x-show="open" x-cloak aria-hidden="true">▾</span>
                </button>
            </div>
        </td>
    @endif

    {{-- sel-sel kolom asli --}}
    {{ $slot }}
</tr>

{{-- Baris expandable (panel lampiran) --}}
<tr class="hidden" x-data>
    {{-- placeholder untuk kolom caret/checkbox supaya align --}}
    <td class="px-3 py-3"></td>

    {{-- colspan = jumlah kolom baris utama - 1 --}}
    <td
        x-bind:colspan="
            Math.max(
                1,
                $el.closest('tr').previousElementSibling.children.length - 1
            )
        "
        class="px-3 py-3"
    >
        @php $lampirans = optional($record)->lampirans ?? collect(); @endphp

        @if ($isImm)
            @include('tables.rows.imm-lampirans-panel-plain', ['record' => $record])
        @else
            @include('tables.rows.lampirans-panel-plain', [
                'record' => $record,
                'lampirans' => $lampirans,
            ])
        @endif
    </td>
</tr>
