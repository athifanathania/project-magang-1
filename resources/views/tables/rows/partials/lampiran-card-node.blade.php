@props([
  'lampiran',
  'level' => 0,
  'forceShowSub' => false,
  'filterTerms' => [],
  'filterAll' => false,
  // default id modal; parent bisa override
  'modalId' => 'view-lampiran-panel',
])

@php
    use Illuminate\Support\Facades\Storage;

    // === Ambil SEMUA children (pakai eager kalau sudah dimuat) ===
    $childrenAll = $lampiran->relationLoaded('childrenRecursive')
        ? $lampiran->childrenRecursive
        : $lampiran->children()->with('childrenRecursive')->get();

    // dipakai untuk render (nanti bisa terfilter oleh pencarian)
    $children    = $childrenAll;
    $hasChildren = $childrenAll->isNotEmpty();

    // === Status file node ini ===
    $filePath = trim((string) ($lampiran->file ?? ''));
    $hasFile  = ($filePath !== '');
    $noFile   = ! $hasFile;

    $editUrl  = \App\Filament\Resources\LampiranResource::getUrl('edit', ['record' => $lampiran]);

    // === Aturan warna: MERAH hanya bila LEAF & TIDAK punya file ===
    $isCompletelyMissing = (!$hasChildren) && (!$hasFile);

    // URL untuk buka file jika node ini punya file sendiri
    $openUrl = $hasFile
        ? route('media.berkas.lampiran', ['berkas' => $lampiran->berkas_id, 'lampiran' => $lampiran->id])
        : null;

    // ── PARSE KEYWORDS (robust: pecah CSV di semua level)
    $rawKw = $lampiran->keywords ?? null;

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

    $kw = collect($toArray($rawKw))
        ->flatMap(fn ($item) =>
            is_array($item)
                ? $item
                : preg_split('/\s*,\s*/u', trim((string) $item), -1, PREG_SPLIT_NO_EMPTY)
        )
        ->map(fn ($x) => trim((string) $x, " \t\n\r\0\x0B\"'"))
        ->filter()
        ->unique()
        ->values();

    // indent kiri biar tampak hierarki (maks 16)
    $indent = 'pl-' . min(($level * 4), 16);

    // URL helper
    $createUrl = \App\Filament\Resources\LampiranResource::getUrl('create', [
        'parent_id' => $lampiran->id,
        'berkas_id' => $lampiran->berkas_id,
    ]);

    $canUpdate = auth()->user()?->can('update', $lampiran) ?? false;

    // === BACA STATE FILTER dari tabel (TagsInput q.terms + toggle q.all)
    $filtersState = method_exists($this, 'getTableFiltersForm')
        ? $this->getTableFiltersForm()->getRawState()
        : [];

    $terms = collect(data_get($filtersState, 'q.terms', []))
        ->filter(fn ($t) => is_string($t) && trim($t) !== '')
        ->map(fn ($t) => mb_strtolower(trim($t)))
        ->values();

    $modeAll = (bool) data_get($filtersState, 'q.all', false);

    // === Normalizer & matcher
    $norm = function ($v) {
        if ($v instanceof \Illuminate\Support\Collection) $v = $v->all();
        if (is_array($v)) {
            return collect($v)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter()->values();
        }
        if (is_string($v)) {
            $j = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) {
                return collect($j)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter()->values();
            }
            return collect([mb_strtolower(trim($v))])->filter()->values();
        }
        if (is_object($v)) return collect((array)$v)->flatten()->map(fn($x)=>mb_strtolower(trim((string)$x)))->filter()->values();
        if (is_numeric($v) || is_bool($v)) return collect([mb_strtolower((string)$v)]);
        return collect();
    };

    $nodeMatches = function (\App\Models\Lampiran $n) use ($terms, $modeAll, $norm): bool {
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

    $branchMatches = function (\App\Models\Lampiran $n) use (&$branchMatches, $nodeMatches): bool {
        if ($nodeMatches($n)) return true;

        $kids = $n->relationLoaded('childrenRecursive')
            ? $n->childrenRecursive
            : $n->children()->with('childrenRecursive')->get();

        foreach ($kids as $c) {
            if ($branchMatches($c)) return true;
        }
        return false;
    };

    // Hanya render kalau node ini sendiri yang match:
    $shouldRender = $nodeMatches($lampiran);

    // Saring children: hanya cabang yang match
    if ($hasChildren) {
        $children = $children->filter(fn ($c) => $branchMatches($c))->values();
        $hasChildren = $children->isNotEmpty();
    }
@endphp

@once
    <style>
    [x-cloak]{display:none!important}
    /* SAMAKAN DENGAN TABEL DOKUMEN */
    .kw-grid{display:flex;flex-wrap:wrap;gap:.3rem;align-items:flex-start}
    .kw-pill{
        display:inline-flex;align-items:center;white-space:nowrap;
        font-size:.625rem;        /* ≈10px */
        line-height:1.1;
        padding:.0625rem .375rem; /* 1px 6px */
        font-weight:600;
        border-radius:.375rem;
        background:#fffbeb;       /* amber-50 */
        color:#92400e;            /* amber-700 */
        border:1px solid #fde68a; /* amber-200 */
    }
    </style>
@endonce


@if ($shouldRender)
    <div
    wire:key="lampiran-card-{{ $lampiran->id }}"
    class="rounded-lg border p-3 mb-2 w-full max-w-full overflow-hidden {{ $indent }} {{ $hasFile ? 'cursor-pointer hover:bg-blue-50/50' : '' }}"
    x-data="{ open: false }"
    data-file-url="{{ $hasFile ? $openUrl : '' }}"
    data-edit-url="{{ (!$hasFile && $canUpdate) ? ($editUrl . '?missingFile=1') : '' }}"
    @click="
        // cegah aksi lain di halaman ini
        $event.stopPropagation();
        // 1) kalau ada file → buka tab baru dan SELESAI (return)
        if ($el.dataset.fileUrl) {
            window.open($el.dataset.fileUrl, '_blank');
            return;
        }
        // 2) kalau tidak ada file & user boleh update → pergi ke edit
        if ($el.dataset.editUrl) {
            window.location.assign($el.dataset.editUrl);
            return;
        }
        // 3) viewer tanpa file → tampilkan notif
        $dispatch('show-no-file', { name: '{{ addslashes($lampiran->nama ?? 'Lampiran') }}' });
    "
    >
        <div class="flex items-start gap-2">
            {{-- caret hanya kalau ada anak --}}
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
                    <span
                        @class([
                            'font-semibold',
                            'break-words',
                            'text-red-600' => $isCompletelyMissing,   // merah hanya jika cabang benar2 kosong
                            'text-gray-900' => ! $isCompletelyMissing,
                        ])
                        style="{{ $isCompletelyMissing ? 'color:#dc2626' : '' }}"
                    >
                        {{ $lampiran->nama ?? 'Tanpa judul' }}
                    </span>

                    @can('create', \App\Models\Lampiran::class)
                    <a href="{{ $createUrl }}" @click.stop
                        class="text-xs px-2 py-0.5 rounded border border-gray-200 hover:bg-gray-50">
                        + Sub
                    </a>
                    @endcan

                    {{-- tombol kanan --}}
                    <div class="ml-auto flex items-center gap-2">
                    @if ($hasFile)
                        <a href="{{ $openUrl }}" target="_blank" rel="noopener"
                        class="text-sm font-medium hover:underline" style="color:#2563eb"
                        @click.stop>
                        Buka
                        </a>
                    @elseif ($canUpdate)
                        <a href="{{ $editUrl }}?missingFile=1"
                        class="text-sm font-medium hover:underline text-amber-700"
                        @click.stop>
                        Tambahkan file
                        </a>
                    @else
                        <button type="button"
                                class="text-sm font-medium text-gray-500 hover:text-gray-700"
                                @click.stop.prevent="$dispatch('show-no-file', { name: '{{ addslashes($lampiran->nama ?? 'Lampiran') }}' })">
                        File belum tersedia
                        </button>
                    @endif
                    </div>
                    <a href="#"
                        class="text-gray-600 hover:text-gray-900"
                        title="Lihat"
                        @click.stop.prevent="
                            // 1) suruh viewer memuat id ini (tanpa re-render induk)
                            $wire.dispatch('open-lampiran-view', { id: {{ $lampiran->id }} })
                            // 2) buka modalnya
                            $dispatch('open-modal', { id: '{{ $modalId }}' })
                        ">
                        <x-filament::icon icon="heroicon-m-eye" class="w-4 h-4" />
                    </a>

                    @can('update', $lampiran)
                    <a href="{{ $editUrl }}" class="ml-2 text-gray-400 hover:text-blue-600 shrink-0" title="Edit lampiran" @click.stop>
                        <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4" />
                    </a>
                    @endcan

                    @can('delete', $lampiran)
                    <button type="button" class="ml-2 text-gray-400 hover:text-red-600" title="Hapus lampiran"
                            @click.stop="$dispatch('set-lampiran-to-delete', { id: {{ $lampiran->id }}, berkasId: {{ $lampiran->berkas_id }} })">
                        <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4" />
                    </button>
                    @endcan

                </div>

                {{-- tampilkan path file kecil di bawah judul --}}
                @if(!empty($lampiran->file))
                    <div class="text-xs text-gray-500 break-words">
                        {{ $lampiran->file }}
                    </div>
                @endif

                {{-- keywords --}}
                @if($kw->isNotEmpty())
                    <div class="mt-2 kw-grid">
                        @foreach ($kw as $tag)
                        <span class="kw-pill">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif

                {{-- anak-anaknya (rekursif) --}}
                @if ($hasChildren)
                    <div class="mt-2" x-show="open" x-cloak>
                        @foreach ($children as $child)
                            @include('tables.rows.partials.lampiran-card-node', [
                                'lampiran'      => $child,
                                'level'         => $level + 1,
                                'filterTerms'   => $terms->all() ?? $filterTerms,
                                'filterAll'     => $modeAll ?? $filterAll,
                                'forceShowSub'  => $forceShowSub,   // penting
                            ])
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>  
@else
    {{-- Parent tidak cocok: tampilkan anak-anak yang masih match di LEVEL YANG SAMA --}}
    @foreach ($children as $child)
        @include('tables.rows.partials.lampiran-card-node', [
            'lampiran' => $child,
            'level'    => $level,   // bukan $level + 1, karena parent disembunyikan
        ])
    @endforeach
@endif