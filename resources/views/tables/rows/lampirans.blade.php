@php
use App\Models\Berkas as MBerkas;
use App\Models\Lampiran as MLampiran;
use Illuminate\Support\Facades\Storage;

/** @var mixed $record */

// 1) Sumber data: utamakan $record (Berkas) → root + children.
//    Tetap dukung fallback $lampirans (koleksi root) jika $record tidak dikirim.
if (isset($record) && $record instanceof MBerkas) {
    $items = $record->rootLampirans()
        ->with('childrenRecursive')
        ->orderBy('id')
        ->get();
} else {
    $items = ($lampirans ?? null) instanceof \Illuminate\Support\Collection
        ? $lampirans
        : collect($lampirans ?? []);
}

// helper keywords untuk kolom root
$normalizeKeywords = function ($raw) {
    if (blank($raw)) return [];

    if ($raw instanceof \Illuminate\Support\Collection)      $arr = $raw->all();
    elseif (is_array($raw))                                  $arr = $raw;
    elseif (is_string($raw)) {
        $j = json_decode($raw, true);
        $arr = (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : [$raw];
    } else {
        $arr = [(string) $raw];
    }

    return collect($arr)
        ->flatMap(fn ($item) =>
            is_array($item)
                ? $item
                : preg_split('/\s*,\s*/u', trim((string) $item), -1, PREG_SPLIT_NO_EMPTY)
        )
        ->map(fn ($s) => trim((string) $s, " \t\n\r\0\x0B\"'"))
        ->filter()
        ->unique()
        ->values()
        ->all();
};

// URL header actions (hanya muncul jika konteks Berkas)
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
  /* CHIP AMBER SAMA DENGAN TABEL DOKUMEN */
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


<div
  class="p-5"
  x-data="{ opens: {}, toDelete: { id: null, berkasId: null } }"
  x-on:set-lampiran-to-delete.window="
      toDelete = $event.detail;
      $dispatch('open-modal', { id: 'confirm-delete-lampiran' });
  "
>
    <div class="panel-head mb-3">
        <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-gray-800">Lampiran</h3>
            <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                {{ $items->count() }} lampiran
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

    @if ($items->isNotEmpty())
        <div class="rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm table-fixed">   {{-- ← tambah table-fixed --}}
                    <colgroup>
                        <col class="w-12" />
                        <col />
                        <col class="w-[38%]" />                   {{-- ← kecilkan keywords dari 50% ke 38% (atau 40%) --}}
                        <col class="w-[12rem] min-w-[12rem]" />   {{-- ← kunci lebar kolom Aksi ~192px --}}
                    </colgroup>
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-700">
                            <th class="px-4 py-3 font-medium text-center">#</th>
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Keywords</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @foreach ($items as $i => $lampiran)
                            @php
                                $nama        = data_get($lampiran, 'nama') ?: '-';
                                $file        = data_get($lampiran, 'file');
                                $noFile      = blank($file) || !Storage::disk('public')->exists($file);
                                $keywords    = $normalizeKeywords(data_get($lampiran, 'keywords'));
                                $children    = $lampiran->relationLoaded('childrenRecursive')
                                    ? $lampiran->childrenRecursive
                                    : $lampiran->children()->with('childrenRecursive')->get();
                                $hasChildren = $children->isNotEmpty();
                                $rowKey      = $lampiran->id;
                            @endphp

                            {{-- ROOT ROW --}}
                            <tr class="hover:bg-gray-50 align-top">
                                <td class="px-4 py-3 text-center text-gray-500">{{ $i + 1 }}</td>

                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-2">
                                        {{-- caret --}}
                                        @if ($hasChildren)
                                            <button
                                                type="button"
                                                class="mt-1 w-5 h-5 flex items-center justify-center border rounded hover:bg-gray-50 shrink-0"
                                                @click="opens[{{ $rowKey }}] = ! (opens[{{ $rowKey }}] ?? false)"
                                                :aria-expanded="(opens[{{ $rowKey }}] ?? false).toString()">
                                                <span x-show="!(opens[{{ $rowKey }}] ?? false)">▸</span>
                                                <span x-show="(opens[{{ $rowKey }}] ?? false)" x-cloak>▾</span>
                                            </button>
                                        @else
                                            <span class="mt-1 w-5 h-5 inline-block"></span>
                                        @endif

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span
                                                    @class([
                                                        'font-medium',
                                                        'break-words',
                                                        $noFile ? 'text-red-600' : 'text-gray-800',
                                                    ])
                                                    style="{{ $noFile ? 'color:#dc2626' : '' }}"  {{-- fallback jika kelas Tailwind kepurge --}}
                                                >
                                                    {{ $nama }}
                                                </span>

                                                {{-- + Sub khusus root --}}
                                                <a
                                                  href="{{ \App\Filament\Resources\LampiranResource::getUrl('create', [
                                                      'parent_id' => $lampiran->id,
                                                      'berkas_id' => $lampiran->berkas_id,
                                                  ]) }}"
                                                  class="text-xs px-2 py-0.5 rounded border border-gray-200 hover:bg-gray-50"
                                                >+ Sub</a>
                                            </div>

                                            @if ($file)
                                                <div class="text-xs text-gray-500 break-words">{{ $file }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    @if (count($keywords))
                                    <div class="kw-grid">
                                        @foreach ($keywords as $tag)
                                        <span class="kw-pill">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                    @else
                                    <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @php
                                            $file = data_get($lampiran, 'file');
                                            $fileUrl = $file ? \Storage::disk('public')->url($file) : null;
                                            $editUrl = \App\Filament\Resources\LampiranResource::getUrl('edit', ['record' => $lampiran]);
                                            $openUrl = $fileUrl ?: ($editUrl . '?missingFile=1');
                                        @endphp

                                        <a href="{{ $openUrl }}" {{ $fileUrl ? 'target=_blank rel=noopener' : '' }}
                                        class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-50 hover:border-blue-200 transition"
                                        title="{{ $fileUrl ? 'Buka file' : 'Tambahkan file lampiran' }}">
                                            <span>📄</span><span>Buka</span>
                                        </a>

                                        {{-- IKON EDIT --}}
                                        <a href="{{ $editUrl }}"
                                        class="ml-2 text-gray-400 hover:text-blue-600 shrink-0"
                                        title="Edit lampiran"
                                        @click.stop>
                                            <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4" />
                                        </a>

                                        {{-- IKON HAPUS --}}
                                        <button type="button"
                                                class="ml-2 text-gray-400 hover:text-red-600 shrink-0"
                                                title="Hapus lampiran"
                                                @click.stop="$dispatch('set-lampiran-to-delete', { id: {{ $lampiran->id }}, berkasId: {{ $lampiran->berkas_id }} })">
                                            <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            {{-- CHILDREN ROW --}}
                            @if ($hasChildren)
                                <tr x-show="opens[{{ $rowKey }}] ?? false" x-cloak>
                                    <td></td>
                                    <td colspan="3" class="pl-8">
                                        @foreach ($children as $child)
                                            @include('tables.rows.partials.lampiran-card-node', [
                                                'lampiran'      => $child,
                                                'level'         => 1,
                                                'forceShowSub'  => true,   // <— paksa tampil + Sub untuk semua sub di modal
                                            ])
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
            <div class="text-3xl mb-2">📎</div>
            <p class="text-gray-700 font-medium">Belum ada lampiran</p>
            @if ($tambahUrl)
                <p class="text-gray-500 text-sm mt-1">Gunakan tombol <a class="underline" href="{{ $tambahUrl }}">+ Tambah Lampiran</a> untuk membuat lampiran.</p>
            @endif
        </div>
    @endif
    <x-filament::modal id="confirm-delete-lampiran" width="md">
        <x-slot name="heading">Hapus lampiran?</x-slot>
        <x-slot name="description">
            Lampiran ini <b>beserta semua subnya</b> akan dihapus. Tindakan tidak dapat dibatalkan.
        </x-slot>

        <x-slot name="footer">
            <x-filament::button
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'confirm-delete-lampiran' })"
            >Batal</x-filament::button>

            <x-filament::button
                color="danger"
                x-on:click="
                    $wire.handleDeleteLampiran(toDelete.id, toDelete.berkasId, 'modal');
                    $dispatch('close-modal', { id: 'confirm-delete-lampiran' });
                "
            >Hapus</x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>