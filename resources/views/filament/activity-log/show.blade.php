@php
    $p = $record->properties ?? collect();
    
    // Logic Warna Badge Aksi
    $eventColor = match($record->event) {
        'login', 'create', 'restore', 'download' => 'text-success-700 bg-success-50 ring-success-600/20',
        'update', 'version_desc_update' => 'text-warning-700 bg-warning-50 ring-warning-600/20',
        'delete', 'logout', 'version_delete' => 'text-danger-700 bg-danger-50 ring-danger-600/20',
        default => 'text-primary-700 bg-primary-50 ring-primary-600/20',
    };

    // Logic Label Objek
    $objectLabel = '-';
    $isUserObject = false;
    
    // 1. Cek properti custom label dulu (biasanya titipan controller)
    if ($customLabel = $record->getExtraProperty('object_label')) {
        $objectLabel = $customLabel;
    } 
    // 2. Jika Subject adalah User
    elseif ($record->subject_type === \App\Models\User::class && $record->subject) {
        $objectLabel = $record->subject->name . ' #' . ($record->subject->department ?? '-');
        $isUserObject = true;
    } 
    // 3. (BARU) Cek Function Model (Ini yang memanggil Part Name dari Model Regular)
    elseif ($record->subject && method_exists($record->subject, 'getActivityDisplayName')) {
        $objectLabel = $record->subject->getActivityDisplayName();
    }
    // 4. Default Fallback
    elseif ($record->subject_type) {
        $objectLabel = class_basename($record->subject_type) . ' #' . $record->subject_id;
    }
@endphp 

<div class="space-y-6">

    {{-- HEADER: Waktu & Aksi --}}
    <div class="flex items-center justify-between border-b pb-4 border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-2">
            @php
                $icon = match($record->event) {
                    'login', 'logout' => 'heroicon-m-arrow-right-on-rectangle',
                    'delete' => 'heroicon-m-trash',
                    'update' => 'heroicon-m-pencil-square',
                    'create' => 'heroicon-m-plus-circle',
                    default => 'heroicon-m-information-circle',
                };
            @endphp
            <x-filament::icon 
                :icon="$icon" 
                class="h-5 w-5 text-gray-400" 
            />
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ $record->created_at->timezone(auth()->user()->timezone ?? config('app.timezone','Asia/Jakarta'))->format('d F Y, H:i') }}
            </span>
        </div>
        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $eventColor }}">
            {{ strtoupper($record->event) }}
        </span>
    </div>

    {{-- GRID INFO UTAMA --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        {{-- 1. Pelaku (Causer) - UPDATED --}}
        <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Pelaku (User)</span>
            <div class="mt-1 flex items-center gap-2">
                <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600">
                    {{ substr(optional($record->causer)->name ?? 'S', 0, 1) }}
                </div>
                <div class="text-sm">
                    <p class="font-medium text-gray-900 dark:text-white">
                        {{ optional($record->causer)->name ?? 'System / Tidak Diketahui' }}
                    </p>
                    {{-- Ganti ID menjadi Departemen --}}
                    <p class="text-xs text-gray-500">
                        Dept: {{ optional($record->causer)->department ?? '-' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- 2. Objek yang dimanipulasi --}}
        <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded-lg border border-gray-100 dark:border-gray-800">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Objek</span>
            <div class="mt-1">
                <p class="text-sm font-medium {{ $isUserObject ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-white' }}">
                    {{ $objectLabel }}
                </p>
                <p class="text-xs text-gray-500 truncate" title="{{ $record->subject_type }}">
                    Type: {{ class_basename($record->subject_type) }}
                </p>
            </div>
        </div>
    </div>

    {{-- DESKRIPSI --}}
    <div>
        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-1">Deskripsi</span>
        <div class="text-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md p-3">
            {{ $record->description }}
        </div>
    </div>

    {{-- PERUBAHAN DATA (DIFF) - TAMPILAN TABEL GRID TEGAS --}}
    @php
        $new = $p['attributes'] ?? $p['new'] ?? [];
        $old = $p['old'] ?? [];

        // Daftar field yang disembunyikan
        $hiddenFields = [
            'id', 'created_at', 'updated_at', 'deleted_at', 
            'uuid', 'user_id', 'team_id', 'model_type', 'model_id',
            'dokumen_src', 'thumbnail', 'is_public', 
            'dokumen_src_uploaded_at', 'kode_berkas_ci',
        ];
    @endphp

    @if (!empty($new) || !empty($old))
        <div class="mt-4">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-2">
                Rincian Perubahan Data
            </span>
            
            <div class="overflow-x-auto rounded border border-gray-300">
                <table class="w-full text-sm text-left border-collapse">
                    {{-- HEADER --}}
                    <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-xs">
                            <th class="px-4 py-3 font-bold border-b border-r border-gray-300 w-1/3">Field</th>
                            @if(!empty($old))
                                <th class="px-4 py-3 font-bold border-b border-r border-gray-300 w-1/3">Sebelum</th>
                                <th class="px-4 py-3 font-bold border-b border-gray-300 w-1/3">Sesudah</th>
                            @else
                                <th class="px-4 py-3 font-bold border-b border-gray-300">Isi Data</th>
                            @endif
                        </tr>
                    </thead>

                    {{-- BODY --}}
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($new as $key => $value)
                            @continue(in_array($key, $hiddenFields))

                            <tr class="hover:bg-gray-50">
                                {{-- Kolom Nama Field --}}
                                <td class="px-4 py-2 font-medium text-gray-700 border-r border-gray-200 bg-gray-50/50 capitalize">
                                    {{ str_replace(['_', '-'], ' ', $key) }}
                                </td>

                                {{-- Kolom Nilai Lama (Jika Update) --}}
                                @if(!empty($old))
                                    <td class="px-4 py-2 text-red-600 border-r border-gray-200 bg-red-50/20 text-xs font-mono break-all">
                                        {{ $old[$key] ?? '-' }}
                                    </td>
                                @endif

                                {{-- Kolom Nilai Baru --}}
                                <td class="px-4 py-2 text-gray-800 break-all">
                                    @if(is_bool($value))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $value ? 'Yes' : 'No' }}
                                        </span>

                                    {{-- PERBAIKAN: Handle jika datanya Array (seperti keywords) --}}
                                    @elseif(is_array($value))
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($value as $item)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                                    {{ is_string($item) ? $item : json_encode($item) }}
                                                </span>
                                            @empty
                                                <span class="text-gray-400 italic text-xs">Kosong</span>
                                            @endforelse
                                        </div>

                                    {{-- Handle File / Path Panjang --}}
                                    @elseif(is_string($value) && (str_contains($key, 'dokumen') || str_contains($value, 'tmp/') || str_contains($value, '/')))
                                        <div class="flex flex-col gap-1">
                                            <span class="text-blue-600 font-medium flex items-center gap-1">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                                {{ basename($value) }}
                                            </span>
                                            <span class="text-[10px] text-gray-400 font-mono truncate max-w-xs" title="{{ $value }}">
                                                {{ $value }}
                                            </span>
                                        </div>

                                    {{-- Default String/Number --}}
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- FOOTER: INFORMASI TEKNIS (Collapsible / Kecil) --}}
    <div class="border-t pt-4 border-gray-200 dark:border-gray-700 text-xs text-gray-500 space-y-1">
        <div class="flex gap-2">
            <span class="font-semibold w-12 shrink-0">IP:</span>
            <span class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">{{ data_get($p,'ip') ?? '-' }}</span>
        </div>
        
        @if ($route = data_get($p,'route'))
        <div class="flex gap-2">
            <span class="font-semibold w-12 shrink-0">Route:</span>
            <span class="truncate font-mono text-gray-400">{{ $route }}</span>
        </div>
        @endif

        @if ($url = data_get($p,'url'))
        <div class="flex gap-2">
            <span class="font-semibold w-12 shrink-0">URL:</span>
            <a href="{{ $url }}" target="_blank" class="truncate text-primary-500 hover:underline">{{ $url }}</a>
        </div>
        @endif

        <div class="flex gap-2 mt-2">
            <span class="font-semibold w-12 shrink-0">Agent:</span>
            <span class="text-gray-400 italic">{{ data_get($p,'user_agent') }}</span>
        </div>
    </div>

</div>