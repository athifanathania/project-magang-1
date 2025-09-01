@php
use App\Models\Berkas as MBerkas;
use App\Models\Lampiran as MLampiran;

/** @var mixed $record */

// 1) Tentukan root items (mendukung konteks Berkas dan Lampiran)
if (isset($record) && $record instanceof MBerkas) {
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

// 2) Baca state filter dari table (jika ada)
$filtersState = method_exists($this, 'getTableFiltersForm')
    ? $this->getTableFiltersForm()->getRawState()
    : [];

$terms = collect(data_get($filtersState, 'q.terms', []))
    ->filter(fn ($t) => is_string($t) && trim($t) !== '')
    ->map(fn ($t) => mb_strtolower(trim($t)))
    ->values();

$modeAll = (bool) data_get($filtersState, 'q.all', false);

// 3) Normalizer untuk pencarian
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
        return collect([mb_strtolower($v)]);
    }
    if (is_object($v)) {
        return collect((array) $v)->flatten()->filter()->map(fn ($x) => mb_strtolower(trim((string) $x)));
    }
    if (is_numeric($v) || is_bool($v)) {
        return collect([mb_strtolower((string) $v)]);
    }
    return collect();
};

// 4) Match node atau salah satu descendant-nya
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

// 5) Filter roots
$sourceLampirans = $items;
$filtered = $sourceLampirans->filter(fn ($root) => $nodeOrDescendantMatches($root))->values();

$totalLampiranSemua = $sourceLampirans->count();
$totalLampiranCocok  = $filtered->count();

// 6) URL tombol header (muncul kalau konteks Berkas)
$kelolaUrl  = (isset($record) && $record instanceof MBerkas)
    ? \App\Filament\Resources\LampiranResource::getUrl('index', ['berkas_id' => $record->id])
    : null;

$tambahUrl  = (isset($record) && $record instanceof MBerkas)
    ? \App\Filament\Resources\LampiranResource::getUrl('create', ['berkas_id' => $record->id])
    : null;
@endphp

@once
<style>
  [x-cloak]{display:none!important}
  /* SAMAKAN CHIP DENGAN TABEL DOKUMEN */
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

  /* header panel tetep */
  .panel-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap}
  .panel-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
  .btn-ghost{font-size:.75rem;padding:.25rem .5rem;border:1px solid #e5e7eb;border-radius:.375rem;background:#fff}
  .btn-ghost:hover{background:#f9fafb}
</style>
@endonce
<div
  class="p-5"
  x-data="{ toDelete: { id: null, berkasId: null } }"
  x-on:set-lampiran-to-delete.window="
      toDelete = $event.detail;
      $dispatch('open-modal', { id: 'confirm-delete-lampiran-panel' });
  "
>
    {{-- ======= konten panel existing kamu (judul, header actions, daftar lampiran) tetap ======= --}}
    {{-- pastikan tombol 🗑️ di root & di partial lampiran-card-node mengirim event:
         $dispatch('set-lampiran-to-delete', { id: {{ $lampiran->id }}, berkasId: {{ $lampiran->berkas_id }} })
    --}}


    <div class="panel-head mb-3">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800">Lampiran</h3>
            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                {{ $totalLampiranCocok }} dari {{ $totalLampiranSemua }} lampiran
            </span>
        </div>

        @if ($kelolaUrl || $tambahUrl)
            <div class="panel-actions">
                @if ($kelolaUrl)
                    <a href="{{ $kelolaUrl }}" class="btn-ghost">Kelola Lampiran</a>
                @endif
                @if ($tambahUrl)
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
                ])
            @endforeach
        </div>
    @endif
    <x-filament::modal id="confirm-delete-lampiran-panel" width="md">
        <x-slot name="heading">Hapus lampiran?</x-slot>
        <x-slot name="description">
            <p class="text-sm leading-6 text-gray-600 break-words">
                Lampiran ini <b>beserta semua subnya</b> akan dihapus. <br> Tindakan tidak dapat dibatalkan.
            </p>
        </x-slot>
        <x-slot name="footer">
            <x-filament::button
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-delete-lampiran-panel' })"
            >Batal</x-filament::button>

            <x-filament::button
                color="danger"
                x-data
                x-on:click="
                    $dispatch('close-modal', { id: 'confirm-delete-lampiran-panel' });

                    //    baru panggil Livewire (yang akan $refresh komponen)
                    setTimeout(() => {
                        $wire.handleDeleteLampiran(toDelete.id, toDelete.berkasId, 'panel');
                    }, 50);
                "
            >
                Hapus
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
