@props(['lampiran','level'=>0,'filterTerms'=>[],'filterAll'=>false])

@php
use Filament\Facades\Filament;
use App\Models\ImmLampiran;
use Illuminate\Support\Facades\Gate;

// children
$childrenAll = $lampiran->relationLoaded('childrenRecursive')
    ? $lampiran->childrenRecursive
    : $lampiran->children()->with('childrenRecursive')->get();

$children    = $childrenAll;
$hasChildren = $childrenAll->isNotEmpty();

// ==========================================
// 1. DEFINISI USER & PERMISSION
// ==========================================
$user = auth()->user();
$isAdmin = $user?->hasRole('Admin') ?? false;
$isAdminOrEditor = $user?->hasAnyRole(['Admin','Editor']) ?? false;
$isStaff = $user?->hasRole('Staff') ?? false;

// ==========================================
// 2. LOGIKA FILE
// ==========================================
$fileStaf   = trim((string) ($lampiran->file_staf ?? '')); // File Staf
$fileRecord = trim((string) ($lampiran->file ?? ''));      // File Record (Utama)
$fileSrc    = trim((string) ($lampiran->file_src ?? ''));  // File Asli (Source)

// Variabel $filePath tetap ada untuk kompatibilitas logika ekstensi file di bawah
$filePath = $fileRecord; 

// Node dianggap punya file jika salah satu ada (untuk indikator warna judul merah/hitam)
$hasFile  = ($fileStaf !== '') || ($fileRecord !== '');

// URL untuk "Buka" (KHUSUS FILE STAF)
// Revisi: Tidak ada fallback ke fileRecord. Hanya isi URL jika fileStaf ada.
$openUrl = null;
if ($fileStaf !== '') {
    $openUrl = route('media.imm.lampiran', ['lampiran' => $lampiran->id, 'type' => 'staf']);
}

$editUrl  = \App\Filament\Resources\ImmLampiranResource::getUrl('edit', ['record' => $lampiran]);

// ==========================================
// 3. LOGIKA DOWNLOAD SUMBER (FILE ASLI)
// ==========================================
// Revisi: HANYA ADMIN yang boleh download source, dan file source harus ada.
$canDownloadSource = $isAdmin && ($fileSrc !== '');
$downloadSrcUrl = route('download.source', ['type' => 'imm-lampiran', 'id' => $lampiran->id]);

// ==========================================
// 4. FILTER & SEARCH (TIDAK DIUBAH)
// ==========================================
$hasFileSelf = $hasFile;
$hasFileInDesc = function (ImmLampiran $n) use (&$hasFileInDesc): bool {
    $kids = $n->relationLoaded('childrenRecursive')
        ? $n->childrenRecursive
        : $n->children()->with('childrenRecursive')->get();

    foreach ($kids as $c) {
        $cStaf   = trim((string) ($c->file_staf ?? ''));
        $cRecord = trim((string) ($c->file ?? ''));
        if (($cStaf !== '' || $cRecord !== '') || $hasFileInDesc($c)) return true;
    }
    return false;
};

$branchHasFile       = $hasFileSelf || $hasFileInDesc($lampiran);
$isCompletelyMissing = ! $branchHasFile;

$toArray = function ($v) {
    if (blank($v)) return [];
    if ($v instanceof \Illuminate\Support\Collection) return $v->all();
    if (is_array($v)) return $v;
    if (is_string($v)) {
        $j = json_decode($v, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : [$v];
    }
    return [(string) $v];
};

$kw = collect($toArray($lampiran->keywords))
    ->flatMap(fn($x) => is_array($x) ? $x : preg_split('/\s*,\s*/u', (string) $x, -1, PREG_SPLIT_NO_EMPTY))
    ->map(fn($x) => trim((string) $x, " \t\n\r\0\x0B\"'"))
    ->filter()->unique()->values();

$indent = 'pl-' . min(($level * 4), 16);

$filtersState = method_exists($this, 'getTableFiltersForm') ? $this->getTableFiltersForm()->getRawState() : [];
$terms = collect(data_get($filtersState, 'q.terms', []))->filter(fn($t)=>is_string($t) && trim($t)!=='')->map(fn($t)=>mb_strtolower(trim($t)))->values();
$modeAll = (bool) data_get($filtersState, 'q.all', false);

$norm = function($v){
    if ($v instanceof \Illuminate\Support\Collection) return $v->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
    if (is_array($v)) return collect($v)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
    if (is_string($v)) {
        $j = json_decode($v,true);
        if (json_last_error()===JSON_ERROR_NONE && is_array($j)) return collect($j)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
        return collect([mb_strtolower(trim($v))])->filter();
    }
    if (is_object($v)) return collect((array)$v)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
    if (is_numeric($v) || is_bool($v)) return collect([mb_strtolower((string)$v)]);
    return collect();
};

$nodeMatches = function(ImmLampiran $n) use($terms,$modeAll,$norm): bool {
    if ($terms->isEmpty()) return true;
    $hay = collect()->merge($norm($n->nama ?? ''))->merge($norm($n->file ?? ''))->merge($norm($n->keywords ?? ''));
    if ($hay->isEmpty()) return false;
    return $modeAll ? $terms->every(fn ($t) => $hay->contains(fn ($s) => str_contains($s, $t))) : $terms->some(fn ($t) => $hay->contains(fn ($s) => str_contains($s, $t)));
};

$branchMatches = function(ImmLampiran $n) use(&$branchMatches,$nodeMatches): bool {
    if ($nodeMatches($n)) return true;
    $kids = $n->relationLoaded('childrenRecursive') ? $n->childrenRecursive : $n->children()->with('childrenRecursive')->get();
    foreach ($kids as $c) if ($branchMatches($c)) return true;
    return false;
};

$shouldRender = $nodeMatches($lampiran);
if ($hasChildren) {
    $children = $children->filter(fn($c)=>$branchMatches($c))->values();
    $hasChildren = $children->isNotEmpty();
}

$createUrl = \App\Filament\Resources\ImmLampiranResource::getUrl('create', [
    'parent_id' => $lampiran->id,
    'doc_type'  => class_basename($lampiran->documentable_type),
    'doc_id'    => $lampiran->documentable_id,
]);

$isPublic = optional(Filament::getCurrentPanel())->getId() === 'public';
$canManageImm = ! $isPublic && (
    ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin','Editor']))
    || $user?->can('lampiran.create')
    || $user?->can('create', \App\Models\ImmLampiran::class)
);

// LOGIKA TOMBOL "BUKA" (User Permission)
$canOpen = ($isAdminOrEditor || $isStaff);

$canDeleteStrict = $user?->hasRole('Admin') ?? false;

@endphp

@once
<style>[x-cloak]{display:none!important}</style>
@endonce

@if ($shouldRender)
<div class="rounded-lg border p-3 mb-2 w-full max-w-full overflow-hidden {{ $indent }} {{ $hasFile ? 'cursor-pointer hover:bg-blue-50/50' : '' }}"
     x-data="{ open: false }">

  <div class="flex items-start gap-2">
    @if ($hasChildren)
      <button type="button"
              class="mt-1 w-5 h-5 flex items-center justify-center border rounded hover:bg-gray-50 shrink-0"
              @click.stop="open = !open"
              :aria-expanded="open.toString()">
        <span x-show="!open">▸</span>
        <span x-show="open" x-cloak>▾</span>
      </button>
    @else
      <span class="mt-1 w-5 h-5 inline-block shrink-0"></span>
    @endif

    <div class="min-w-0 w-full">
      <div class="flex items-center gap-2 flex-wrap">
        {{-- JUDUL --}}
        <span class="font-semibold break-words {{ $isCompletelyMissing ? 'text-red-600' : 'text-gray-900' }}"
          style="{{ $isCompletelyMissing ? 'color:#dc2626' : '' }}">
          {{ $lampiran->nama ?? 'Tanpa judul' }}
        </span>

        @if ($canManageImm)
        <a href="{{ $createUrl }}" @click.stop
            class="text-xs px-2 py-0.5 rounded border border-gray-200 hover:bg-gray-50">
            + Sub
        </a>
        @endif

        <div class="ml-auto flex items-center gap-2">
        {{-- 1. TOMBOL BUKA (MENGARAH KE FILE STAF SAJA) --}}
        @if (!empty($openUrl) && $canOpen)
          <a href="{{ $openUrl }}" target="_blank" rel="noopener"
            class="text-sm font-medium hover:underline" style="color:#2563eb" @click.stop>
            Buka
          </a>
        @elseif (!empty($openUrl))
          <span class="text-sm text-gray-400 cursor-not-allowed" title="Akses terbatas">Buka</span>
        @elseif ($isAdminOrEditor)
          {{-- Jika file staf KOSONG, tawarkan Admin/Editor upload --}}
          <a href="{{ $editUrl }}?missingFile=1"
            class="text-sm font-medium hover:underline text-amber-700" @click.stop>
            Tambahkan file
          </a>
        @else
          {{-- User biasa, file staf kosong = File belum tersedia --}}
          <span class="text-sm text-gray-500">File belum tersedia</span>
        @endif

        {{-- 2. TOMBOL DOWNLOAD SUMBER (HANYA ADMIN & JIKA ADA FILE SOURCE) --}}
        @if ($canDownloadSource)
            <a href="{{ $downloadSrcUrl }}" target="_blank" rel="noopener"
              class="text-gray-600 hover:text-gray-900" title="Unduh File Asli (Admin Saja)"
              @click.stop>
              <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4" />
            </a>
        @endif
      </div>

        @if (auth()->user()?->can('update', $lampiran) || $canManageImm)
        <a href="{{ $editUrl }}" class="ml-2 text-gray-400 hover:text-blue-600 shrink-0" title="Edit lampiran" @click.stop>
            <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4" />
        </a>
        @endif

        {{-- TOMBOL VIEW MODAL --}}
        <a href="#"
            class="text-gray-600 hover:text-gray-900"
            title="Lihat Detail"
            @click.stop.prevent="
                $wire.dispatch('open-imm-lampiran-view', { id: {{ $lampiran->id }} });
                $dispatch('open-modal', { id: 'view-imm-lampiran-panel-{{ $lampiran->documentable_id }}' });
            ">
            <x-filament::icon icon="heroicon-m-eye" class="w-4 h-4" />
        </a>

        @if ($canDeleteStrict)
        <button type="button"
            class="ml-2 text-gray-400 hover:text-red-600"
            title="Hapus lampiran"
            @click.stop="$dispatch('set-imm-lampiran-to-delete', { id: {{ $lampiran->id }}, docId: {{ $lampiran->documentable_id }} })">
            <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4" />
        </button>
        @endif
      </div>

      {{-- 3. NAMA FILE RECORD (Di bawah judul) --}}
      {{-- REVISI: Hanya Admin & Editor yang boleh lihat nama file record di tampilan list ini --}}
      @if(!empty($fileRecord) && $isAdminOrEditor)
        <div class="text-xs text-gray-500 break-words">{{ $fileRecord }}</div>
      @endif

      @if($kw->isNotEmpty())
        <div class="mt-2 kw-grid">@foreach ($kw as $tag)<span class="kw-pill">{{ $tag }}</span>@endforeach</div>
      @endif

      @if ($hasChildren)
        @php $canDrag = auth()->user()?->hasAnyRole(['Admin']) ?? false; @endphp

        <div class="mt-2 imm-sortable" x-show="open" x-cloak
            data-parent="{{ $lampiran->id }}">
          @foreach ($children as $child)
            <div class="imm-sortable-item" data-id="{{ $child->id }}">
              @if($canDrag)
                <span class="cursor-grab drag-handle mr-2" title="Geser untuk mengurutkan">⋮⋮</span>
              @endif

              @include('tables.rows.partials.imm-lampiran-card-node', [
                'lampiran' => $child,
                'level'    => $level+1
              ])
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>
@else
  @foreach ($children as $child)
    @include('tables.rows.partials.imm-lampiran-card-node', ['lampiran'=>$child, 'level'=>$level])
  @endforeach
@endif