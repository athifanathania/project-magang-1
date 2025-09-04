@props(['record'])

@php
  $versions = $record->dokumen_versions ?? [];
@endphp

@if(!empty($versions))
  <div class="mt-4">
    <h3 class="text-sm font-semibold">Riwayat dokumen</h3>
    <div class="mt-2 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-500">
            <th class="text-left py-1 px-2">#</th>
            <th class="text-left py-1 px-2">Nama File</th>
            <th class="text-left py-1 px-2">Tanggal Upload</th>
            <th class="text-left py-1 px-2">Tanggal Ubah</th>
            <th class="text-left py-1 px-2">Ukuran</th>
            <th class="text-left py-1 px-2">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($versions as $i => $v)
            <tr class="border-t">
              <td class="py-1 px-2">{{ $i + 1 }}</td>
              <td class="py-1 px-2 break-words">{{ $v['filename'] ?? basename($v['path'] ?? '') }}</td>
              <td class="py-1 px-2">{{ $v['uploaded_at'] ?? '—' }}</td>
              <td class="py-1 px-2">{{ $v['replaced_at'] ?? '—' }}</td>
              <td class="py-1 px-2">
                {{ isset($v['size']) ? number_format(($v['size'] ?? 0)/1024, 1).' KB' : '—' }}
              </td>
              <td class="py-1 px-2">
                @if(auth()->user()?->hasAnyRole(['Admin','Editor']))
                  <a href="{{ route('media.berkas.version', ['berkas' => $record->id, 'index' => $i]) }}"
                     class="text-xs text-blue-600 hover:underline">Download</a>
                @else
                  <span class="text-xs text-gray-400">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif
