@if(!empty($record->file_versions))
  <div class="mt-4">
    <h3 class="text-sm font-semibold">Riwayat lampiran</h3>
    <div class="mt-2 overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-gray-500">
            <th class="text-left py-1">#</th>
            <th class="text-left py-1">Nama File</th>
            <th class="text-left py-1">Tanggal Upload</th>
            <th class="text-left py-1">Tanggal Ubah</th>
            <th class="text-left py-1">Ukuran</th>
            <th class="text-left py-1">Aksi</th>
          </tr>
        </thead>
        <tbody>
          @foreach($record->file_versions as $i => $v)
            <tr class="border-t">
              <td class="py-1">{{ $i+1 }}</td>
              <td class="py-1">{{ $v['filename'] ?? '-' }}</td>
              <td class="py-1">{{ $v['uploaded_at'] ?? '-' }}</td>
              <td class="py-1">{{ $v['replaced_at'] ?? '-' }}</td>
              <td class="py-1">{{ isset($v['size']) ? number_format($v['size']/1024,1).' KB' : '-' }}</td>
              <td class="py-1">
                @if(auth()->user()?->hasAnyRole(['Admin','Editor']))
                  <a href="{{ route('media.lampiran.version', [$record, 'index' => $i]) }}"
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
