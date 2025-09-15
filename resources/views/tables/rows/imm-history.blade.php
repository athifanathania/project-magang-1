@php
    $rec = isset($getRecord) ? $getRecord() : ($record ?? null);
    if ($rec) $rec = $rec->refresh();

    $raw = $rec?->file_versions ?? $rec?->versions ?? [];
    $all      = collect($raw);
    $versions = $all->values();
    $tz = auth()->user()->timezone ?? config('app.timezone') ?: 'Asia/Jakarta';

    $fmtDate = function ($d) use ($tz) {
        if (blank($d)) return '-';
        try { return \Illuminate\Support\Carbon::parse($d)->setTimezone($tz)->translatedFormat('d M Y H:i'); }
        catch (\Throwable) { return (string) $d; }
    };

    $canEdit = auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
    $canDownload = $canEdit || (auth()->user()?->hasAnyRole(['Staff']) ?? false);

    $type = match (true) {
        $rec instanceof \App\Models\ImmManualMutu        => 'manual-mutu',
        $rec instanceof \App\Models\ImmProsedur          => 'prosedur',
        $rec instanceof \App\Models\ImmInstruksiStandar  => 'instruksi-standar',
        $rec instanceof \App\Models\ImmFormulir          => 'formulir',
        default => 'imm',
    };
@endphp

@if ($versions->isNotEmpty())
    <div x-data="{ toDelete: { idx: null, name: '' } }">
        <div class="mt-3 rounded-xl ring-1 ring-gray-200 overflow-hidden">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-50/80">
                    <tr class="text-gray-600">
                        <th class="px-3 py-2 border w-12">No</th>
                        <th class="px-3 py-2 border">Nama Dokumen</th>
                        <th class="px-3 py-2 border w-24">Revisi</th>
                        <th class="px-3 py-2 border">Deskripsi Revisi</th>
                        <th class="px-3 py-2 border w-40">Tgl Terbit</th>
                        <th class="px-3 py-2 border w-40">Tgl Ubah</th>
                        <th class="px-3 py-2 border w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versions as $i => $v)
                        <tr class="odd:bg-white even:bg-gray-50/50">
                            <td class="px-3 py-2 border">{{ $i + 1 }}</td>
                            <td class="px-3 py-2 border break-words">{{ $v['filename'] ?? '-' }}</td>
                            <td class="px-3 py-2 border">{{ $v['revision'] ?? '-' }}</td>
                            <td class="px-3 py-2 border break-words">{{ $v['description'] ?? '—' }}</td>
                            <td class="px-3 py-2 border">{{ $fmtDate($v['uploaded_at'] ?? null) }}</td>
                            <td class="px-3 py-2 border">{{ $fmtDate($v['replaced_at'] ?? null) }}</td>
                            <td class="px-3 py-2 border">
                                <div class="flex items-center gap-1">
                                    @if ($canDownload)
                                        <a href="{{ route('media.imm.version', [
                                                'type'  => $type,
                                                'id'    => $rec->getKey(),
                                                'index' => $i,
                                            ]) }}"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                                        title="Download">
                                            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600" />
                                        </a>
                                    @endif

                                    @if ($canEdit)
                                        <button type="button" title="Edit deskripsi"
                                            class="inline-flex w-7 h-7 items-center justify-center rounded hover:bg-gray-100"
                                            x-on:click="$dispatch('open-imm-version-edit', { id: {{ $rec->getKey() }}, index: {{ $i }} })">
                                            <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4 text-gray-600" />
                                        </button>
                                        <button type="button" title="Hapus versi"
                                            class="inline-flex w-7 h-7 items-center justify-center rounded hover:bg-gray-100"
                                            x-on:click="toDelete = { idx: {{ $i }}, name: @js($v['filename'] ?? '-') }; $dispatch('open-modal', { id: 'confirm-del-imm-ver' })">
                                            <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- konfirmasi hapus (event ke Livewire page resource kamu) --}}
        <x-filament::modal id="confirm-del-imm-ver" width="md" wire:ignore.self>
            <x-slot name="heading">Hapus versi?</x-slot>
            <x-slot name="description">
                <p class="text-sm text-gray-600">Versi <b x-text="toDelete.name"></b> akan dihapus. Tindakan tidak dapat dibatalkan.</p>
            </x-slot>
            <x-slot name="footer">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'confirm-del-imm-ver' })">Batal</x-filament::button>
                <x-filament::button color="danger"
                    x-on:click.stop.prevent="
                        $dispatch('close-modal', { id: 'confirm-del-imm-ver' });
                        window.Livewire.dispatch('delete-imm-version', { id: {{ $rec->getKey() }}, index: toDelete.idx });
                        setTimeout(() => window.location.reload(), 250);
                    ">
                    Hapus
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>
@else
    <p class="text-sm text-gray-500 mt-2">Belum ada riwayat.</p>
@endif
