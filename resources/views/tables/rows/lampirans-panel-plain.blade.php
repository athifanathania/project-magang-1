@php
use App\Models\Berkas as MBerkas;
use App\Models\Regular as MRegular;
use App\Models\Lampiran as MLampiran;
use App\Models\EventCustomer as MEventCustomer; // Pastikan model ini benar
use Filament\Facades\Filament;

/** @var mixed $record */

// 1) Tentukan root items (Logic tetap sama)
if (isset($record) && (
    $record instanceof MBerkas || 
    $record instanceof MRegular || 
    $record instanceof MEventCustomer 
)) {
    $items = $record->rootLampirans()
        ->with('childrenRecursive')
        ->orderBy('id')
        ->get();
} elseif (isset($record) && $record instanceof MLampiran) {
    $items = collect([$record->load('childrenRecursive')]);
} else {
    $items = ($lampirans ?? null) instanceof \Illuminate\Support\Collection
        ? $lampirans
        : collect($lampirans ?? []);
}

// 2) Baca state filter (Logic tetap sama)
$filtersState = method_exists($this, 'getTableFiltersForm')
    ? $this->getTableFiltersForm()->getRawState()
    : [];

$terms = collect(data_get($filtersState, 'q.terms', []))
    ->filter(fn ($t) => is_string($t) && trim($t) !== '')
    ->map(fn ($t) => mb_strtolower(trim($t)))
    ->values();

$modeAll = (bool) data_get($filtersState, 'q.all', false);

// 3) Normalizer (Logic tetap sama)
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

// 4) Match node (Logic tetap sama)
$nodeMatches = function (MLampiran $n) use ($norm, $terms, $modeAll): bool {
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

$nodeOrDescendantMatches = function (MLampiran $n) use (&$nodeOrDescendantMatches, $nodeMatches): bool {
    if ($nodeMatches($n)) return true;
    $children = $n->relationLoaded('childrenRecursive')
        ? $n->childrenRecursive
        : $n->children()->with('childrenRecursive')->get();
    foreach ($children as $c) {
        if ($nodeOrDescendantMatches($c)) return true;
    }
    return false;
};

// 5) Filter roots (Logic tetap sama)
$sourceLampirans = $items;
$filtered = $sourceLampirans->filter(fn ($root) => $nodeOrDescendantMatches($root))->values();
$totalLampiranSemua = $sourceLampirans->count();
$totalLampiranCocok  = $filtered->count();

// ==========================================================
// 6) URL tombol header (BAGIAN YANG DIPERBAIKI)
// ==========================================================

$urlParams = [];
$docOwnerId = isset($record) ? $record->id : null;

// Cek tipe record untuk menentukan parameter URL
if (isset($record)) {
    if ($record instanceof MRegular) {
        // Jika Regular
        $urlParams['regular_id'] = $record->id;
    } 
    elseif ($record instanceof MEventCustomer) {
        // Jika Event Customer
        // 1. Set berkas_id (karena di DB lampirans relasinya ke berkas_id)
        $urlParams['berkas_id'] = $record->id; 
        // 2. KIRIM SINYAL 'from' => 'event_customer'
        $urlParams['from'] = 'event_customer'; 
    } 
    elseif ($record instanceof MBerkas) {
        // Jika Berkas (Event Biasa)
        $urlParams['berkas_id'] = $record->id;
    }
}

// Generate URL hanya jika ada params
$tambahUrl = !empty($urlParams)
    ? \App\Filament\Resources\LampiranResource::getUrl('create', $urlParams)
    : null;

// Tombol kelola (Index) opsional, pakai logic yg sama atau default
$kelolaUrl = !empty($urlParams)
    ? \App\Filament\Resources\LampiranResource::getUrl('index', $urlParams)
    : null;

// ==========================================================

// ✅ CEK HAK AKSES
$user = Filament::auth()->user();
$canViewLampiran   = $user?->can('lampiran.view')   ?? false;
$canCreateLampiran = $user?->can('lampiran.create') ?? false;
@endphp

{{-- 
    HTML DI BAWAHNYA TETAP SAMA SEPERTI SEBELUMNYA
    TIDAK PERLU DIUBAH 
--}}
@once
<style>
  [x-cloak]{display:none!important}
  .kw-grid{display:flex;flex-wrap:wrap;gap:.3rem;align-items:flex-start}
  .kw-pill{
    display:inline-flex;align-items:center;white-space:nowrap;
    font-size:.625rem;
    line-height:1.1;
    padding:.0625rem .375rem;
    font-weight:600;
    border-radius:.375rem;
    background:#fffbeb;
    color:#92400e;
    border:1px solid #fde68a;
  }
  .panel-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}
  .panel-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
  .btn-ghost{font-size:.75rem;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.375rem;background:#fff}
  .btn-ghost:hover{background:#f9fafb}
</style>
@endonce
@php $pageId = $this->getId(); @endphp

<div class="p-5"
    x-data="{
        toDelete: { id: null, ownerId: null },
        toDeleteVersion: { lampiranId: null, index: null, name: '' },
        pageId: '{{ $pageId }}'
    }"
    x-on:close-modal.window="
        document.body.classList.remove('fi-modal-open','overflow-hidden');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.style.overflow='';
        document.documentElement.style.overflow='';
    "
    x-on:set-lampiran-to-delete.window="
        toDelete = $event.detail;
        $dispatch('open-modal', { id: 'confirm-delete-lampiran-panel' });
    "
    x-on:ask-delete-version.window="
        toDeleteVersion = $event.detail;
        $dispatch('open-modal', { id: 'confirm-delete-version' });
    "
>
    <div class="panel-head mb-3">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800">Dokumen Pelengkap</h3>
            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
            {{ $totalLampiranCocok }} dari {{ $totalLampiranSemua }} dokumen
            </span>
        </div>

        @if ( ($kelolaUrl && $canViewLampiran) || ($tambahUrl && $canCreateLampiran) )
            <div class="panel-actions">
            @if ($tambahUrl && $canCreateLampiran)
                <a href="{{ $tambahUrl }}" class="btn-ghost">+ Tambah Lampiran</a>
            @endif
            </div>
        @endif
    </div>

    @if ($filtered->isEmpty())
        <div class="text-sm text-gray-500">Tidak ada lampiran yang cocok…</div>
    @else
        <div class="space-y-2">
            @foreach ($filtered as $lampiran)
                @include('tables.rows.partials.lampiran-card-node', [
                    'lampiran'     => $lampiran,
                    'level'        => 0,
                    'filterTerms'  => $terms->all(),
                    'filterAll'    => $modeAll,
                    'forceShowSub' => true,
                    'modalId'      => "view-lampiran-panel-{$record->id}",   
                    'ownerId'      => $docOwnerId,
                    'isRegular'    => ($record instanceof \App\Models\Regular),
                ])
            @endforeach
        </div>
    @endif

    {{-- MODAL HAPUS LAMPIRAN --}}
    <x-filament::modal id="confirm-delete-lampiran-panel" width="md" wire:ignore.self>
        <x-slot name="heading">Hapus lampiran?</x-slot>
        <x-slot name="description">
            <p class="text-sm leading-6 text-gray-600 break-words">
                Lampiran ini <b>beserta semua subnya</b> akan dihapus. <br>
                Tindakan tidak dapat dibatalkan.
            </p>
        </x-slot>
        <x-slot name="footer">
            <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'confirm-delete-lampiran-panel' })">Batal</x-filament::button>
            <x-filament::button type="button" color="danger" x-on:click.stop.prevent="
                    const y = window.scrollY;
                    $dispatch('close-modal', { id: 'confirm-delete-lampiran-panel' });
                    setTimeout(() => {
                        window.Livewire.find(pageId)
                            .call('handleDeleteLampiran', toDelete.id, toDelete.ownerId, 'panel')
                            .then(() => {
                                window.location.replace(window.location.pathname + window.location.search);
                            })
                            .finally(() => {
                                document.body.classList.remove('fi-modal-open','overflow-hidden');
                                document.documentElement.classList.remove('overflow-hidden');
                                document.body.style.overflow='';
                                document.documentElement.style.overflow='';
                                window.scrollTo({ top: y, behavior: 'auto' });
                            });
                    }, 150);
                ">Hapus</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- MODAL HAPUS VERSI --}}
    <x-filament::modal id="confirm-delete-version" width="xl" wire:ignore.self>
        <x-slot name="heading">Hapus versi lampiran?</x-slot>
        <x-slot name="description">
            <p class="text-sm leading-6 text-gray-600 break-words" style="overflow-wrap:anywhere;">
               Versi <b class="font-semibold text-gray-900 break-all" x-text="toDeleteVersion.name"></b> akan dihapus. <br>
                Tindakan tidak dapat dibatalkan.
            </p>
        </x-slot>
        <x-slot name="footer">
            <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'confirm-delete-version' })">Batal</x-filament::button>
            <x-filament::button type="button" color="danger" x-on:click.stop.prevent="
                    const y = window.scrollY;
                    $dispatch('close-modal', { id: 'confirm-delete-version' });
                    setTimeout(() => {
                        window.Livewire.find(pageId)
                            .call('handleDeleteLampiranVersion', toDeleteVersion.lampiranId, toDeleteVersion.index)
                            .finally(() => {
                                document.body.classList.remove('fi-modal-open','overflow-hidden');
                                document.documentElement.classList.remove('overflow-hidden');
                                document.body.style.overflow='';
                                document.documentElement.style.overflow='';
                                window.scrollTo({ top: y, behavior: 'auto' });
                            });
                    }, 150);
                ">Hapus</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- MODAL VIEWER --}}
    <x-filament::modal id="view-lampiran-panel-{{ $record->id }}" width="7xl" wire:ignore.self>
        <x-slot name="heading">Lihat Lampiran</x-slot>
        <livewire:lampiran-viewer :wire:key="'viewer-'.$record->id" />
        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'view-lampiran-panel-{{ $record->id }}' })">Tutup</x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>