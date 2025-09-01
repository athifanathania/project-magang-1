@props([
    'alpineHidden' => null,
    'alpineSelected' => null,
    'recordAction' => null,
    'recordUrl' => null,
    'striped' => false,

    // dukung dua kemungkinan nama prop dari induk
    'record' => null,
    'recordKey' => null,
])

@php
    $hasAlpineHiddenClasses = filled($alpineHidden);
    $hasAlpineSelectedClasses = filled($alpineSelected);
    $stripedClasses = 'bg-gray-50 dark:bg-white/5';

    /** @var null|\Illuminate\Database\Eloquent\Model $record */
    // pakai record langsung jika dikirim; kalau tidak, resolve dari key (jika tersedia)
    $record = $record ?: ($recordKey && method_exists($this, 'getTableRecord')
        ? $this->getTableRecord($recordKey)
        : $record);
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
    {{-- Caret paling kiri --}}
    <!-- <td class="w-6 align-top">
        <button
            x-data="{ open: false }"
            x-on:click.stop="
                open = !open;
                // toggle baris expandable di bawah baris ini
                $el.closest('tr').nextElementSibling.classList.toggle('hidden')
            "
            x-text="open ? '▾' : '▸'"
            class="text-gray-500 hover:text-gray-700"
            type="button"
            title="Tampilkan / sembunyikan lampiran"
        >▸</button>
    </td> -->

    {{-- Sel-sel kolom asli --}}
    {{ $slot }}
    </tr>

    {{-- Baris expandable (awalnya tersembunyi) --}}
    <tr class="hidden" x-data>
        {{-- Placeholder untuk kolom checkbox (biar align) --}}
        <td class="px-3 py-3"></td>

        {{-- colspan = jumlah kolom baris utama - 1 (karena ada kolom checkbox) --}}
        <td
            x-bind:colspan="
                Math.max(
                    1,
                    $el.closest('tr').previousElementSibling.children.length - 1
                )
            "
            class="px-3 py-3"
        >
            @php
                $lampirans = optional($record)->lampirans ?? collect();
            @endphp

            @include('tables.rows.lampirans-panel-plain', [
                'record' => $record,
                'lampirans' => $lampirans,
            ])
        </td>
    </tr>