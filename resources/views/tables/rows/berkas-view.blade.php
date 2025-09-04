@props(['record'])

<div class="space-y-4">
  {{-- Ringkasan data --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="space-y-2">
      <div>
        <div class="text-xs text-gray-500">Customer</div>
        <div class="font-medium">{{ $record->customer_name ?? '—' }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500">Model</div>
        <div class="font-medium">{{ $record->model ?? '—' }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500">Part No</div>
        <div class="font-medium">{{ $record->kode_berkas }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500">Part Name</div>
        <div class="font-medium break-words">{{ $record->nama }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500">Detail</div>
        <div class="break-words">{{ $record->detail ?? '—' }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500">File aktif</div>
        <div>
          @if($record->dokumen)
            <a href="{{ route('media.berkas', $record) }}" target="_blank" rel="noopener"
               class="text-sm text-blue-600 hover:underline">Buka dokumen</a>
          @else
            <span class="text-sm text-gray-400">Tidak ada file</span>
          @endif
        </div>
      </div>
    </div>

    {{-- Thumbnail --}}
    <div class="flex items-start justify-center">
      @php
        $thumbPath = (string) ($record->thumbnail ?? '');
        $thumbUrl  = $thumbPath !== '' ? \Storage::disk('public')->url($thumbPath) : asset('images/placeholder.png');
      @endphp
      <img src="{{ $thumbUrl }}" alt="Thumbnail" class="max-h-40 object-contain rounded" />
    </div>
  </div>

  {{-- Keywords (chip sederhana) --}}
  <div>
    <div class="text-xs text-gray-500">Kata kunci</div>
    @php $tags = (array) ($record->keywords ?? []); @endphp
    @if(!empty($tags))
      <div class="mt-1 flex flex-wrap gap-1">
        @foreach($tags as $tag)
          @php $t = trim((string) $tag); @endphp
          @if($t !== '')
            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200">
              {{ $t }}
            </span>
          @endif
        @endforeach
      </div>
    @else
      <div class="text-sm text-gray-400">—</div>
    @endif
  </div>

  {{-- Tabel riwayat versi --}}
  @include('tables.rows.berkas-history', ['record' => $record])
</div>
