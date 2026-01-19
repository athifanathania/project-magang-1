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
    
    // Cek properti custom label dulu
    if ($customLabel = $record->getExtraProperty('object_label')) {
        $objectLabel = $customLabel;
    } 
    // Jika Subject adalah User
    elseif ($record->subject_type === \App\Models\User::class && $record->subject) {
        $objectLabel = $record->subject->name . ' #' . ($record->subject->department ?? '-');
        $isUserObject = true;
    } 
    // Default
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

    {{-- PERUBAHAN DATA (DIFF) --}}
    @if ($diff = data_get($p, 'attributes'))
        <div class="mt-2">
            <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-1">Data Changes</span>
            <div class="rounded-md border border-gray-200 dark:border-gray-700 overflow-hidden">
                <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-3 overflow-x-auto font-mono text-gray-600 dark:text-gray-300">{{ json_encode([
    'new' => $p['attributes'] ?? [],
    'old' => $p['old'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
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