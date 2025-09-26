@props(['lampiran','level'=>0,'filterTerms'=>[],'filterAll'=>false])

@php
use Filament\Facades\Filament;
use App\Models\ImmLampiran;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;

// children
$childrenAll = $lampiran->relationLoaded('childrenRecursive')
    ? $lampiran->childrenRecursive
    : $lampiran->children()->with('childrenRecursive')->get();

$children    = $childrenAll;
$hasChildren = $childrenAll->isNotEmpty();

// file status
$filePath = trim((string) ($lampiran->file ?? ''));
$hasFile  = $filePath !== '';

$type = class_basename($lampiran->documentable_type ?? '');

$openUrl = $hasFile
    ? route('media.imm.lampiran', ['lampiran' => $lampiran->id])
    : null;

$editUrl  = \App\Filament\Resources\ImmLampiranResource::getUrl('edit', ['record' => $lampiran]);

// ===== deadline badge =====
$tz       = auth()->user()->timezone ?? config('app.timezone') ?: 'Asia/Jakarta';
$deadline = $lampiran->deadline_at ? Carbon::parse($lampiran->deadline_at, $tz) : null;
$today    = Carbon::now($tz)->startOfDay();
$overdue  = $deadline ? $deadline->endOfDay()->lt($today) : false;
$soon     = $deadline ? (!$overdue && $deadline->diffInDays($today) <= 7) : false;

// ===== branch has file? =====
$hasFileSelf = ($filePath !== '');
$hasFileInDesc = function (ImmLampiran $n) use (&$hasFileInDesc): bool {
    $kids = $n->relationLoaded('childrenRecursive')
        ? $n->childrenRecursive
        : $n->children()->with('childrenRecursive')->get();
    foreach ($kids as $c) {
        if (trim((string) ($c->file ?? '')) !== '' || $hasFileInDesc($c)) return true;
    }
    return false;
};
$branchHasFile       = $hasFileSelf || $hasFileInDesc($lampiran);
$isCompletelyMissing = ! $branchHasFile;

// keywords
$toArray = function ($v) {
    if (blank($v)) return [];
    if ($v instanceof \Illuminate\Support\Collection) return $v->all();
    if (is_array($v)) return $v;
    if (is_string($v)) { $j = json_decode($v, true); return (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : [$v]; }
    return [(string) $v];
};
$kw = collect($toArray($lampiran->keywords))
    ->flatMap(fn($x) => is_array($x) ? $x : preg_split('/\s*,\s*/u', (string) $x, -1, PREG_SPLIT_NO_EMPTY))
    ->map(fn($x) => trim((string) $x, " \t\n\r\0\x0B\"'"))
    ->filter()->unique()->values();

$indent = 'pl-' . min(($level * 4), 16);

// filter (copas IMM)
$filtersState = method_exists($this, 'getTableFiltersForm') ? $this->getTableFiltersForm()->getRawState() : [];
$terms = collect(data_get($filtersState, 'q.terms', []))
    ->filter(fn($t)=>is_string($t) && trim($t)!=='')
    ->map(fn($t)=>mb_strtolower(trim((string)$t)))
    ->values();
$modeAll = (bool) data_get($filtersState, 'q.all', false);
$norm = function($v){
    if ($v instanceof \Illuminate\Support\Collection) return $v->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
    if (is_array($v)) return collect($v)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
    if (is_string($v)) { $j = json_decode($v,true); if (json_last_error()===JSON_ERROR_NONE && is_array($j)) return collect($j)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter(); return collect([mb_strtolower(trim($v))])->filter(); }
    if (is_object($v)) return collect((array)$v)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter();
    if (is_numeric($v) || is_bool($v)) return collect([mb_strtolower((string)$v)]);
    return collect();
};
$nodeMatches = function(ImmLampiran $n) use($terms,$modeAll,$norm): bool {
    if ($terms->isEmpty()) return true;
    $hay = collect()->merge($norm($n->nama ?? ''))->merge($norm($n->file ?? ''))->merge($norm($n->keywords ?? ''));
    if ($hay->isEmpty()) return false;
    return $modeAll
        ? $terms->every(fn ($t) => $hay->contains(fn ($s) => str_contains($s, $t)))
        : $terms->some(fn ($t) => $hay->contains(fn ($s) => str_contains($s, $t)));
};
$branchMatches = function(ImmLampiran $n) use(&$branchMatches,$nodeMatches): bool {
    if ($nodeMatches($n)) return true;
    $kids = $n->relationLoaded('childrenRecursive') ? $n->childrenRecursive : $n->children()->with('childrenRecursive')->get();
    foreach ($kids as $c) if ($branchMatches($c)) return true;
    return false;
};
$shouldRender = $nodeMatches($lampiran);
if ($hasChildren) { $children = $children->filter(fn($c)=>$branchMatches($c))->values(); $hasChildren = $children->isNotEmpty(); }

// create sub url
$createUrl = \App\Filament\Resources\ImmLampiranResource::getUrl('create', [
    'parent_id' => $lampiran->id,
    'doc_type'  => class_basename($lampiran->documentable_type),
    'doc_id'    => $lampiran->documentable_id,
]);

$canUpdate = auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
$canManageTask = auth()->user()?->hasRole('Admin') ?? false;
$isPublic = optional(Filament::getCurrentPanel())->getId() === 'public';
$canManageImm = ! $isPublic && (
    (auth()->user() && method_exists(auth()->user(), 'hasAnyRole') && auth()->user()->hasAnyRole(['Admin','Editor']))
    || auth()->user()?->can('lampiran.create')
    || auth()->user()?->can('create', \App\Models\ImmLampiran::class)
);

// download source icon (sama spt IMM)
$roleCanDownload = Gate::allows('download-source');
$fileSrcPath     = trim((string) ($lampiran->file_src ?? ''));
$hasFileSrc      = ($fileSrcPath !== '');
$ext             = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$nonPdfInFile    = $hasFile && $ext !== 'pdf';
$canDownloadActive = $roleCanDownload && ($hasFileSrc || $nonPdfInFile);
$dlDisabledReason = $roleCanDownload ? 'File asli belum diunggah' : 'Khusus Admin/Editor/Staff';
$downloadSrcUrl = route('download.source', ['type' => 'imm-lampiran', 'id' => $lampiran->id]);
$canOpen = $hasFile && (auth()->user()?->hasAnyRole(['Admin','Editor','Staff']) ?? false);

@endphp

@once
<style>
  [x-cloak]{display:none!important}
  .kw-grid{display:flex;flex-wrap:wrap;gap:.3rem;align-items:flex-start}
  .kw-pill{display:inline-flex;align-items:center;white-space:nowrap;font-size:.625rem;line-height:1.1;padding:.0625rem .375rem;font-weight:600;border-radius:.375rem;background:#fffbeb;color:#92400e;border:1px solid #fde68a}
  .deadline-pill{font-size:.625rem;line-height:1;padding:.125rem .375rem;border-radius:.375rem;border:1px solid}
  .deadline-ok{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
  .deadline-soon{background:#fffbeb;color:#92400e;border-color:#fde68a}
  .deadline-over{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
</style>
@endonce

@if ($shouldRender)
<div class="rounded-lg border p-3 mb-2 w-full max-w-full overflow-hidden {{ $indent }} {{ $hasFile ? 'cursor-pointer hover:bg-blue-50/50' : '' }}"
     x-data="{ open: false }">

  <div class="flex items-start gap-2">
    @if ($hasChildren)
      <button type="button" class="mt-1 w-5 h-5 flex items-center justify-center border rounded hover:bg-gray-50 shrink-0"
              @click.stop="open = !open" :aria-expanded="open.toString()">
        <span x-show="!open">▸</span>
        <span x-show="open" x-cloak>▾</span>
      </button>
    @else
      <span class="mt-1 w-5 h-5 inline-block shrink-0"></span>
    @endif

    <div class="min-w-0 w-full">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="font-semibold break-words {{ $isCompletelyMissing ? 'text-red-600' : 'text-gray-900' }}"
          style="{{ $isCompletelyMissing ? 'color:#dc2626' : '' }}">
          {{ $lampiran->nama ?? 'Tanpa judul' }}
        </span>

        {{-- 🔔 DEADLINE BADGE --}}
        @if ($deadline)
          @php
            $deadlineText = $deadline->format('d/m/Y');
            $deadlineCls  = $overdue ? 'deadline-over' : ($soon ? 'deadline-soon' : 'deadline-ok');
            $title        = $overdue
              ? 'Lewat deadline'
              : ($soon ? 'Mendekati deadline' : 'Deadline aman');
          @endphp
          <span class="deadline-pill {{ $deadlineCls }}" title="{{ $title }}">
            Deadline: {{ $deadlineText }}
          </span>
        @endif

        @if ($canManageTask)
          <a href="{{ $createUrl }}" @click.stop class="text-xs px-2 py-0.5 rounded border border-gray-200 hover:bg-gray-50">+ Sub</a>
        @endif

        <div class="ml-auto flex items-center gap-2">
          @if ($hasFile && $canOpen)
            <a href="{{ $openUrl }}" target="_blank" rel="noopener"
              class="text-sm font-medium hover:underline" style="color:#2563eb" @click.stop>Buka</a>
          @elseif ($hasFile)
            <span class="text-sm text-gray-400 cursor-not-allowed" title="Khusus Admin/Editor/Staff">Buka</span>
          @elseif ($canUpdate)
            <a href="{{ $editUrl }}?missingFile=1" class="text-sm font-medium hover:underline text-amber-700" @click.stop>
              Tambahkan file
            </a>
          @else
            <span class="text-sm text-gray-500">File belum tersedia</span>
          @endif

          {{-- unduh file asli --}}
          @if ($roleCanDownload)
            @if ($canDownloadActive)
              <a href="{{ $downloadSrcUrl }}" target="_blank" rel="noopener" class="text-gray-600 hover:text-gray-900" title="Unduh file asli" @click.stop>
                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4" />
              </a>
            @else
              <span class="text-gray-300 cursor-not-allowed" title="{{ $dlDisabledReason }}">
                <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4" />
              </span>
            @endif
          @endif
        </div>

        @if (auth()->user()?->can('update', $lampiran) || $canManageImm)
          <a href="{{ $editUrl }}" class="ml-2 text-gray-400 hover:text-blue-600 shrink-0" title="Edit task" @click.stop>
            <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4" />
          </a>
        @endif

        <a href="#" class="text-gray-600 hover:text-gray-900" title="Lihat"
           @click.stop.prevent="
             $wire.dispatch('open-imm-lampiran-view', { id: {{ $lampiran->id }} });
             $dispatch('open-modal', { id: 'view-imm-lampiran-panel-{{ $lampiran->documentable_id }}' });
           ">
          <x-filament::icon icon="heroicon-m-eye" class="w-4 h-4" />
        </a>

        @if (auth()->user()?->can('delete', $lampiran) || $canManageTask)
          <button type="button" class="ml-2 text-gray-400 hover:text-red-600" title="Hapus task"
                  @click.stop="$dispatch('set-imm-lampiran-to-delete', { id: {{ $lampiran->id }}, docId: {{ $lampiran->documentable_id }} })">
            <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4" />
          </button>
        @endif
      </div>

      @if(!empty($lampiran->file))
        <div class="text-xs text-gray-500 break-words">{{ $lampiran->file }}</div>
      @endif

      @if($kw->isNotEmpty())
        <div class="mt-2 kw-grid">@foreach ($kw as $tag)<span class="kw-pill">{{ $tag }}</span>@endforeach</div>
      @endif

      @if ($hasChildren)
        <div class="mt-2" x-show="open" x-cloak>
          @foreach ($children as $child)
            @include('tables.rows.partials.audit-task-card-node', ['lampiran'=>$child, 'level'=>$level+1])
          @endforeach
        </div>
      @endif
    </div>
  </div>
</div>
@else
  @foreach ($children as $child)
    @include('tables.rows.partials.audit-task-card-node', ['lampiran'=>$child, 'level'=>$level])
  @endforeach
@endif
