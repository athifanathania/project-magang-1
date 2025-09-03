@php
    // Ambil record dari $getRecord() (Filament v3) atau dari $record (kalau kamu kirim manual)
    $rec = isset($getRecord) && is_callable($getRecord) ? $getRecord() : ($record ?? null);
@endphp

<div class="flex items-center gap-1">
  @if ($rec && filled($rec->dokumen))
    <a href="{{ route('media.berkas', $rec) }}"
       target="_blank" rel="noopener"
       title="Lihat File"
       class="text-[11px] text-blue-600 hover:underline flex items-center gap-1">
      <x-filament::icon icon="heroicon-m-folder" class="w-4 h-4" />
      <span>Lihat File</span>
    </a>
  @else
    <span class="text-[11px] text-gray-400">–</span>
  @endif
</div>
