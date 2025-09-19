@php
use App\Models\ImmLampiran as ML;
use Filament\Facades\Filament;

/** @var \Illuminate\Database\Eloquent\Model $record */

// dukung type campur: FQCN, morph class (alias), & short name
$types = array_unique(array_filter([
    get_class($record),                 // App\Models\ImmInstruksiStandar (FQCN)
    ltrim($record->getMorphClass(), '\\'), // bisa FQCN / alias tergantung morphMap
    class_basename($record),            // ImmInstruksiStandar (short)
]));

// ambil root lampiran untuk dokumen ini
$items = ML::query()
    ->whereIn('documentable_type', $types)
    ->where('documentable_id', $record->getKey())
    ->whereNull('parent_id')
    ->with('childrenRecursive')
    ->orderBy('id')
    ->get();

// ===== baca state filter dari tabel (sama persis) =====
$filtersState = method_exists($this, 'getTableFiltersForm')
    ? $this->getTableFiltersForm()->getRawState()
    : [];

$terms = collect(data_get($filtersState, 'q.terms', []))
    ->filter(fn ($t) => is_string($t) && trim($t) !== '')
    ->map(fn ($t) => mb_strtolower(trim($t)))
    ->values();

$modeAll = (bool) data_get($filtersState, 'q.all', false);

// ===== normalizer & matcher (copy dari panel berkas) =====
$norm = function ($v) {
    if ($v instanceof \Illuminate\Support\Collection) {
        return $v->flatten()->filter()->map(fn ($x) => mb_strtolower(trim((string) $x)));
    }
    if (is_array($v)) {
        return collect($v)->flatten()->filter()->map(fn ($x) => mb_strtolower(trim((string) $x)));
    }
    if (is_string($v)) {
        $j = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
            return collect($j)->flatten()->filter()->map(fn ($x) => mb_strtolower(trim((string) $x)));
        }
        return collect([mb_strtolower(trim($v))]);
    }
    if (is_object($v)) {
        return collect((array) $v)->flatten()->filter()->map(fn ($x) => mb_strtolower(trim((string) $x)));
    }
    if (is_numeric($v) || is_bool($v)) {
        return collect([mb_strtolower((string) $v)]);
    }
    return collect();
};

$nodeMatches = function (ML $n) use ($norm, $terms, $modeAll): bool {
    if ($terms->isEmpty()) return true;

    $hay = collect()
        ->merge($norm($n->nama ?? ''))
        ->merge($norm($n->file ?? ''))
        ->merge($norm($n->keywords ?? ''));

    if ($hay->isEmpty()) return false;

    return $modeAll
        ? $terms->every(fn ($t) => $hay->contains(fn ($s) => str_contains($s, $t)))
        : $terms->some(fn ($t) => $hay->contains(fn ($s) => str_contains($s, $t)));
};

$nodeOrDescendantMatches = function (ML $n) use (&$nodeOrDescendantMatches, $nodeMatches): bool {
    if ($nodeMatches($n)) return true;

    $children = $n->relationLoaded('childrenRecursive')
        ? $n->childrenRecursive
        : $n->children()->with('childrenRecursive')->get();

    foreach ($children as $c) {
        if ($nodeOrDescendantMatches($c)) return true;
    }
    return false;
};

$source   = $items;
$filtered = $source->filter(fn ($root) => $nodeOrDescendantMatches($root))->values();

$totalSemua = $source->count();
$totalCocok = $filtered->count();

$tambahUrl = \App\Filament\Resources\ImmLampiranResource::getUrl('create', [
    'doc_type' => class_basename($record),
    'doc_id'   => $record->getKey(),
]);

// === HAK AKSES ===
$user     = Filament::auth()->user();
$isPublic = optional(Filament::getCurrentPanel())->getId() === 'public';
$canManageImm = ! $isPublic && (
    // role standar
    ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin','Editor']))
    // atau permission kustom
    || $user?->can('lampiran.create')
    // atau policy (kalau nanti ada)
    || $user?->can('create', \App\Models\ImmLampiran::class)
);

$resourceClass = method_exists($this, 'getResource') ? $this->getResource() : null;

    $heading = match (true) {
        // via resource class (halaman admin)
        $resourceClass === \App\Filament\Resources\ImmManualMutuResource::class
            || $record instanceof \App\Models\ImmManualMutu
            => 'Lihat Lampiran Manual Mutu',

        $resourceClass === \App\Filament\Resources\ImmProsedurResource::class
            || $record instanceof \App\Models\ImmProsedur
            => 'Lihat Lampiran Prosedur',

        $resourceClass === \App\Filament\Resources\ImmInstruksiStandarResource::class
            || $record instanceof \App\Models\ImmInstruksiStandar
            => 'Lihat Lampiran Instruksi & Standar Kerja',

        $resourceClass === \App\Filament\Resources\ImmFormulirResource::class
            || $record instanceof \App\Models\ImmFormulir
            => 'Lihat Lampiran Formulir',

        default => 'Lihat Lampiran IMM',
    };
@endphp

@once
<style>
  [x-cloak]{display:none!important}
  .kw-grid{display:flex;flex-wrap:wrap;gap:.3rem;align-items:flex-start}
  .kw-pill{display:inline-flex;align-items:center;white-space:nowrap;font-size:.625rem;line-height:1.1;padding:.0625rem .375rem;font-weight:600;border-radius:.375rem;background:#fffbeb;color:#92400e;border:1px solid #fde68a}
  .panel-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}
  .btn-ghost{font-size:.75rem;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.375rem;background:#fff}
  .btn-ghost:hover{background:#f9fafb}
</style>
@endonce

<div class="p-5"
    x-data="{
        toDelete: { id: null, docId: null },
        toDeleteVersion: { lampiranId: null, index: null, name: '' },
        pageId: '{{ $this->getId() }}'
    }"

    x-on:set-imm-lampiran-to-delete.window="
        toDelete = $event.detail;
        $dispatch('open-modal', { id: 'confirm-delete-imm-lampiran-panel' });
    "

    x-on:ask-delete-imm-version.window="
        toDeleteVersion = $event.detail;
        $dispatch('open-modal', { id: 'confirm-delete-imm-version' });
    "
>
  <div class="panel-head mb-3">
    <div class="flex items-center gap-2">
      <h3 class="text-lg font-semibold text-gray-800">Lampiran</h3>
      <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
        {{ $totalCocok }} dari {{ $totalSemua }} lampiran
      </span>
    </div>

    @if ($canManageImm)
    <a href="{{ $tambahUrl }}" class="btn-ghost">+ Tambah Lampiran</a>
    @endif
  </div>

  @if ($filtered->isEmpty())
    <div class="text-sm text-gray-500">Tidak ada lampiran yang cocok…</div>
  @else
    <div class="space-y-2">
      @foreach ($filtered as $lampiran)
        @include('tables.rows.partials.imm-lampiran-card-node', [
          'lampiran'     => $lampiran,
          'level'        => 0,
          'filterTerms'  => $terms->all(),
          'filterAll'    => $modeAll,
        ])
      @endforeach
    </div>
  @endif

<x-filament::modal id="view-imm-lampiran-panel-{{ $record->getKey() }}" width="7xl" wire:ignore.self>
    <x-slot name="heading">{{ $heading }}</x-slot>

    <livewire:imm-lampiran-viewer :wire:key="'viewer-imm-'.$record->id" />

    <x-slot name="footer">
        <x-filament::button color="gray"
            x-on:click="$dispatch('close-modal', { id: 'view-imm-lampiran-panel-{{ $record->getKey() }}' })">
            Tutup
        </x-filament::button>
    </x-slot>
</x-filament::modal>

<x-filament::modal id="confirm-delete-imm-lampiran-panel" width="md" wire:ignore.self>
    <x-slot name="heading">Hapus lampiran?</x-slot>
    <x-slot name="description">
        <p class="text-sm text-gray-600">Lampiran ini <b>beserta semua sub</b> akan dihapus.<br>Tindakan tidak dapat dibatalkan.</p>
    </x-slot>
    <x-slot name="footer">
        <x-filament::button color="gray"
            x-on:click="$dispatch('close-modal', { id: 'confirm-delete-imm-lampiran-panel' })">
            Batal
        </x-filament::button>
        <x-filament::button color="danger"
            x-on:click.stop.prevent="
                $dispatch('close-modal', { id: 'confirm-delete-imm-lampiran-panel' });
                window.Livewire.find(pageId).call('handleDeleteImmLampiran', toDelete.id, toDelete.docId)
            ">
            Hapus
        </x-filament::button>
    </x-slot>
</x-filament::modal>
<x-filament::modal id="confirm-delete-imm-version" width="xl" wire:ignore.self>
    <x-slot name="heading">Hapus versi lampiran?</x-slot>
    <x-slot name="description">
        <p class="text-sm text-gray-600 break-words" style="overflow-wrap:anywhere;">
           Versi <b class="font-semibold text-gray-900 break-all" x-text="toDeleteVersion.name"></b> akan dihapus. <br>
           Tindakan ini tidak dapat dibatalkan.
        </p>
    </x-slot>
    <x-slot name="footer">
        <x-filament::button color="gray"
            x-on:click="$dispatch('close-modal', { id: 'confirm-delete-imm-version' })">
            Batal
        </x-filament::button>
        <x-filament::button color="danger"
        x-on:click.stop.prevent="
            $dispatch('close-modal', { id: 'confirm-delete-imm-version' });
            window.Livewire.find(pageId).call('onDeleteImmVersion', {
            lampiranId: Number(toDeleteVersion.lampiranId ?? toDeleteVersion.id ?? 0),
            index: Number(toDeleteVersion.index ?? -1)
            });
        ">
        Hapus
        </x-filament::button>
    </x-slot>
</x-filament::modal>
</div>
