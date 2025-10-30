@php
use App\Models\Berkas as MBerkas;
use App\Models\Regular as MRegular;
use App\Models\Lampiran as MLampiran;
use Filament\Facades\Filament;

/** @var mixed $record */

// 1) Tentukan root items (mendukung konteks Berkas dan Lampiran)
if (isset($record) && ($record instanceof MBerkas || $record instanceof MRegular)) {
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
$docOwnerId = (isset($record) && ($record instanceof MBerkas || $record instanceof MRegular))
    ? $record->id
    : null;

$kelolaUrl = $docOwnerId
    ? \App\Filament\Resources\LampiranResource::getUrl('index', [
        $record instanceof MRegular ? 'regular_id' : 'berkas_id' => $docOwnerId,
    ])
    : null;

$tambahUrl = $docOwnerId
  ? \App\Filament\Resources\LampiranResource::getUrl('create', [
        $record instanceof MRegular ? 'regular_id' : 'berkas_id' => $docOwnerId,
    ])
  : null;

// ✅ CEK HAK AKSES (viewer publik akan false)
$user = Filament::auth()->user();
$canViewLampiran   = $user?->can('lampiran.view')   ?? false;
$canCreateLampiran = $user?->can('lampiran.create') ?? false;
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
@php $pageId = $this->getId(); @endphp

<div
    class="p-5"
    x-data="{
        // state konfirmasi hapus lampiran (node)
        toDelete: { id: null, ownerId: null },
        // state konfirmasi hapus versi
        toDeleteVersion: { lampiranId: null, index: null, name: '' },
        // id komponen Livewire halaman (ListBerkas)
        pageId: '{{ $pageId }}'
    }"

    {{-- bersihkan kelas body/html setiap ada event close-modal dari Filament --}}
    x-on:close-modal.window="
        document.body.classList.remove('fi-modal-open','overflow-hidden');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.style.overflow='';
        document.documentElement.style.overflow='';
    "

    {{-- buka modal konfirmasi hapus lampiran dari tombol 🗑️ di kartu --}}
    x-on:set-lampiran-to-delete.window="
        toDelete = $event.detail;
        $dispatch('open-modal', { id: 'confirm-delete-lampiran-panel' });
    "

    {{-- buka modal konfirmasi hapus VERSI (dipanggil dari lampiran-history.blade) --}}
    x-on:ask-delete-version.window="
        toDeleteVersion = $event.detail;
        $dispatch('open-modal', { id: 'confirm-delete-version' });
    "
>


    {{-- ======= konten panel existing kamu (judul, header actions, daftar lampiran) tetap ======= --}}
    {{-- pastikan tombol 🗑️ di root & di partial lampiran-card-node mengirim event:
         $dispatch('set-lampiran-to-delete', { id: {{ $lampiran->id }}, berkasId: {{ $lampiran->berkas_id }} })
    --}}


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

    <x-filament::modal id="confirm-delete-lampiran-panel" width="md" wire:ignore.self>
        <x-slot name="heading">Hapus lampiran?</x-slot>

        <x-slot name="description">
            <p class="text-sm leading-6 text-gray-600 break-words">
                Lampiran ini <b>beserta semua subnya</b> akan dihapus. <br>
                Tindakan tidak dapat dibatalkan.
            </p>
        </x-slot>

        <x-slot name="footer">
            {{-- ✅ Tutup modal yang benar --}}
            <x-filament::button
                type="button"
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-delete-lampiran-panel' })"
            >Batal</x-filament::button>

            {{-- ✅ Panggil handler yang benar + pakai state `toDelete` --}}
            <x-filament::button
                type="button"
                color="danger"
                x-on:click.stop.prevent="
                    const y = window.scrollY;
                    $dispatch('close-modal', { id: 'confirm-delete-lampiran-panel' });

                    setTimeout(() => {
                        window.Livewire.find(pageId)
                            .call('handleDeleteLampiran', toDelete.id, toDelete.ownerId, 'panel')
                            .then(() => {
                                // opsional: reload supaya state bersih & tidak auto-buka modal
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
                "
            >Hapus</x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- ===================================== --}}
    {{-- Modal: Hapus VERSI file (riwayat)     --}}
    {{-- ===================================== --}}
    <x-filament::modal id="confirm-delete-version" width="xl" wire:ignore.self>
        <x-slot name="heading">Hapus versi lampiran?</x-slot>

        <x-slot name="description">
            <p class="text-sm leading-6 text-gray-600 break-words" style="overflow-wrap:anywhere;">
               Versi <b class="font-semibold text-gray-900 break-all" x-text="toDeleteVersion.name"></b> akan dihapus. <br>
                Tindakan tidak dapat dibatalkan.
            </p>
        </x-slot>

        <x-slot name="footer">
            <x-filament::button
                type="button"
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-delete-version' })"
            >Batal</x-filament::button>

            <x-filament::button
                type="button"
                color="danger"
                x-on:click.stop.prevent="
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
                "
            >Hapus</x-filament::button>
        </x-slot>
    </x-filament::modal>
    <!-- 🔹 Modal VIEW lampiran -->
    <x-filament::modal id="view-lampiran-panel-{{ $record->id }}" width="7xl" wire:ignore.self>
        <x-slot name="heading">Lihat Lampiran</x-slot>

        {{-- viewer selalu ada; dia akan memuat data saat terima event --}}
        <livewire:lampiran-viewer :wire:key="'viewer-'.$record->id" />

        <x-slot name="footer">
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'view-lampiran-panel-{{ $record->id }}' })">
                Tutup
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
