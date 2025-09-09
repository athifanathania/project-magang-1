@php
    /** @var \App\Models\Lampiran $record */
    $rec = isset($getRecord) ? $getRecord() : ($record ?? null);

    $all      = collect($rec?->file_versions ?? []);
    $versions = $all->reverse()->values();

    $fmtDate = fn ($d) => blank($d)
        ? '-'
        : optional(\Illuminate\Support\Carbon::parse($d))->translatedFormat('d M Y H:i');

    $fmtSize = function ($bytes) {
        if (! is_numeric($bytes)) return '-';
        $u = ['B','KB','MB','GB','TB']; $i=0; $s=(float)$bytes;
        while ($s>=1024 && $i<count($u)-1) { $s/=1024; $i++; }
        return number_format($s, $i?1:0).' '.$u[$i];
    };

    $extColor = function ($ext) {
        return match (strtolower((string) $ext)) {
            'pdf'                 => 'bg-red-100 text-red-700 ring-red-200',
            'xlsx','xls','csv'    => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'doc','docx'          => 'bg-blue-100 text-blue-700 ring-blue-200',
            'ppt','pptx'          => 'bg-orange-100 text-orange-700 ring-orange-200',
            'jpg','jpeg','png','webp','svg'
                                   => 'bg-purple-100 text-purple-700 ring-purple-200',
            default               => 'bg-gray-100 text-gray-700 ring-gray-200',
        };
    };
@endphp

@if ($versions->isNotEmpty())
    <div class="mt-4">
        <h3 class="text-sm font-semibold">Riwayat lampiran</h3>

        <div class="mt-2 rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
            <table class="w-full text-sm border-collapse table-fixed">
                <colgroup>
                    <col class="w-12">
                    <col>
                    <col class="w-44">
                    <col class="w-44">
                    <col class="w-24">
                    <col class="w-28">
                </colgroup>

                <thead class="bg-gray-50/80">
                    <tr class="text-gray-600">
                        <th class="px-3 py-2 text-left border border-gray-200">#</th>
                        <th class="px-3 py-2 text-left border border-gray-200">Nama File</th>
                        <th class="px-3 py-2 text-left border border-gray-200">Tanggal Upload</th>
                        <th class="px-3 py-2 text-left border border-gray-200">Tanggal Ubah</th>
                        <th class="px-3 py-2 text-left border border-gray-200">Ukuran</th>
                        @if (auth()->user()?->hasAnyRole(['Admin','Editor']))
                        <th class="px-3 py-2 text-left border border-gray-200">Aksi</th>
                        @endif
                    </tr>
                </thead>

                <tbody>
                    @foreach ($versions as $i => $v)
                        @php
                            $originalIndex = $all->count() - 1 - $i;
                            $ext = $v['ext']
                                ?? \Illuminate\Support\Str::of($v['filename'] ?? '')->afterLast('.')->lower();
                        @endphp

                        <tr class="odd:bg-white even:bg-gray-50/30 hover:bg-gray-50/70">
                            <td class="px-3 py-2 text-gray-500 border border-gray-200">{{ $i + 1 }}</td>

                            <td class="px-3 py-2 border border-gray-200">
                                <div class="flex items-start gap-2">
                                    <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                                    <div class="whitespace-normal break-words break-all">
                                        <div class="font-medium text-gray-900">{{ $v['filename'] ?? '-' }}</div>
                                        <span
                                            class="inline-flex items-center rounded ring-1 ring-inset {{ $extColor($ext) }} px-1"
                                            style="font-size:9px; line-height:10px; padding-top:1px; padding-bottom:1px;"
                                            >
                                            {{ strtoupper($ext ?: 'FILE') }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-3 py-2 border border-gray-200 whitespace-normal">{{ $fmtDate($v['uploaded_at'] ?? null) }}</td>
                            <td class="px-3 py-2 border border-gray-200 whitespace-normal">{{ $fmtDate($v['replaced_at'] ?? null) }}</td>
                            <td class="px-3 py-2 border border-gray-200 whitespace-nowrap">{{ $fmtSize($v['size'] ?? null) }}</td>
                            @if (auth()->user()?->hasAnyRole(['Admin','Editor']))
                            <td class="px-3 py-2 border border-gray-200">
                                    <div class="flex items-center gap-1">
                                        {{-- Download --}}
                                        <a href="{{ route('media.lampiran.version', ['lampiran' => $rec->getKey(), 'index' => $originalIndex]) }}"
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                                        title="Download">
                                            <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600" />
                                        </a>

                                        {{-- Hapus versi (buka modal konfirmasi) --}}
                                        <button type="button"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                                            title="Hapus versi"
                                            x-data
                                            @click="$dispatch('ask-delete-version', {
                                                lampiranId: {{ $rec->getKey() }},
                                                index: {{ $originalIndex }},
                                                name: @json($v['filename'] ?? '-')
                                            })">
                                            <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600" />
                                        </button>
                                    </div>
                                @endif
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <p class="text-sm text-gray-500 mt-2">Belum ada riwayat.</p>
@endif
