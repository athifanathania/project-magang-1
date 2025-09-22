{{-- resources/views/tables/rows/berkas-history.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;

    $rec = isset($getRecord) ? $getRecord() : ($record ?? null);
    if ($rec) $rec = $rec->refresh();

    // ===== Kronologis: tertua -> terbaru
    $all = collect($rec?->dokumen_versions ?? [])->values();

    // 1) Pastikan versi AKTIF (=$rec->dokumen) berada di ekor array kronologis
    $currentPath = (string) ($rec?->dokumen ?? '');
    if ($currentPath !== '') {
        $idxCur = $all->search(function ($v) use ($currentPath) {
            $p = $v['file_path'] ?? $v['path'] ?? null;
            return (string) $p === $currentPath;
        });
        if ($idxCur !== false && $idxCur !== $all->count() - 1) {
            $cur = $all->pull($idxCur);
            $all->push($cur);
        }
    }

    // 2) Tampilan: terbaru -> tertua
    $versions = $all->reverse()->values();

    // ===== akses & formatter (sama seperti IMM) =====
    $canEdit     = auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
    $canDelete   = $canEdit;
    $canDownload = $canEdit || (auth()->user()?->hasAnyRole(['Staff']) ?? false);
    $showActions = $canEdit || $canDelete || $canDownload;

    $tz = auth()->user()->timezone ?? config('app.timezone') ?: 'Asia/Jakarta';
    $fmtDate = function ($d) use ($tz) {
        if (blank($d)) return '-';
        try {
            if (is_numeric($d)) {
                $i = (int) $d;
                $c = strlen((string)$i) >= 13
                    ? \Illuminate\Support\Carbon::createFromTimestampMs($i, 'UTC')
                    : \Illuminate\Support\Carbon::createFromTimestamp($i, 'UTC');
            } else {
                $s = trim((string)$d);
                if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $s)) $c = \Illuminate\Support\Carbon::parse($s);
                else {
                    $fmt = str_contains($s, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
                    $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $s, $tz);
                    if ($c->greaterThan(\Illuminate\Support\Carbon::now($tz)->addHours(2))) {
                        $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $s, 'UTC')->setTimezone($tz);
                    }
                }
            }
            return $c->setTimezone($tz)->format('d/m/y H.i');
        } catch (\Throwable) {
            try { return \Illuminate\Support\Carbon::parse($d,'UTC')->setTimezone($tz)->format('d/m/y H.i'); }
            catch (\Throwable) { return (string)$d; }
        }
    };

    $fmtSize = function ($bytes) {
        if (!is_numeric($bytes) || $bytes < 0) return '-';
        $u = ['B','KB','MB','GB','TB']; $i = 0; $s = (float) $bytes;
        while ($s >= 1024 && $i < count($u) - 1) { $s /= 1024; $i++; }
        return number_format($s, $i ? 1 : 0) . ' ' . $u[$i];
    };

    $extColor = fn ($ext) => match (strtolower((string) $ext)) {
        'pdf'               => 'bg-red-100 text-red-700 ring-red-200',
        'xlsx','xls','csv'  => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        'doc','docx'        => 'bg-blue-100 text-blue-700 ring-blue-200',
        'ppt','pptx'        => 'bg-orange-100 text-orange-700 ring-orange-200',
        'jpg','jpeg','png','webp','svg' => 'bg-purple-100 text-purple-700 ring-purple-200',
        default             => 'bg-gray-100 text-gray-700 ring-gray-200',
    };

    // 3) Rekonstruksi uploaded_at kronologis untuk fallback (persis pola IMM)
    $uploadAtChron = [];
    foreach ($all as $j => $ver) {
        $u = $ver['uploaded_at'] ?? null;
        if (blank($u)) {
            $u = $j === 0
                ? ($rec?->dokumen_uploaded_at ?? $rec?->created_at ?? null)
                : ($all[$j - 1]['replaced_at'] ?? null);
        }
        $uploadAtChron[$j] = $u;
    }
@endphp

@if ($versions->isNotEmpty())
<div
  x-data="{
    editVersion: { id: null, index: null, name: '', description: '' },
    toDelete:    { id: null, index: null, name: '' },
    pageId: '{{ $this->getId() }}',
  }"
>
  <div class="mt-2 rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
    <div class="max-w-full overflow-x-auto">
      <table class="w-full text-sm border-collapse table-fixed">
        <colgroup>
          <col class="w-10">
          <col>               {{-- Nama Dokumen --}}
          <col class="w-20">  {{-- Revisi --}}
          <col class="w-40">  {{-- Deskripsi --}}
          <col class="w-24">  {{-- Tgl Terbit --}}
          <col class="w-24">  {{-- Tgl Ubah --}}
          <col class="w-20">  {{-- Ukuran --}}
          @if ($showActions)
            <col class="w-10"> {{-- Aksi --}}
          @endif
        </colgroup>

        <thead class="bg-gray-50/80">
          <tr class="text-gray-600">
            <th class="px-3 py-2 border">No</th>
            <th class="px-3 py-2 border">Nama Dokumen</th>
            <th class="px-3 py-2 border">Revisi</th>
            <th class="px-3 py-2 border">Deskripsi Revisi</th>
            <th class="px-3 py-2 border">Tgl Terbit</th>
            <th class="px-3 py-2 border">Tgl Ubah</th>
            <th class="px-3 py-2 border">Ukuran</th>
            @if ($showActions) <th class="px-3 py-2 border text-center">Aksi</th> @endif
          </tr>
        </thead>

        <tbody>
            @foreach ($versions as $i => $v)
                @php
                    $count      = $all->count();
                    // index kronologis (0 = paling tua) yang sesuai dengan baris tampilan ini
                    $chronIndex = $count - 1 - $i;

                    // Meta file
                    $path     = $v['file_path'] ?? $v['path'] ?? null;
                    $fileName = trim((string)($v['filename'] ?? ($path ? basename($path) : ''))) ?: '-';
                    $ext      = strtolower($v['file_ext'] ?? $v['ext'] ?? pathinfo($fileName, PATHINFO_EXTENSION) ?? '');
                    $extClass = $extColor($ext);

                    // Ukuran
                    $sizeBytes = $v['file_size'] ?? $v['size'] ?? null;
                    if (!$sizeBytes && $path && Storage::disk('private')->exists($path)) {
                        try { $sizeBytes = Storage::disk('private')->size($path); } catch (\Throwable) {}
                    }
                    $sizeText = $sizeBytes ? $fmtSize($sizeBytes) : '-';

                    $displayRevision = 'REV' . str_pad((string)($chronIndex + 1), 2, '0', STR_PAD_LEFT);

                    // === TANGGAL === (fallback ala IMM)
                    $isLatest = $chronIndex === ($count - 1);
                    // Tgl Terbit
                    $uploadAt  = $v['uploaded_at'] ?? ($uploadAtChron[$chronIndex] ?? null);

                    // Tgl Ubah: kosong bila latest; selain itu pakai replaced_at atau uploaded_at versi sesudahnya
                    $nextUpload = ($chronIndex + 1 < $count)
                        ? ($all[$chronIndex + 1]['uploaded_at'] ?? ($uploadAtChron[$chronIndex + 1] ?? null))
                        : null;

                    $changedAt = $isLatest ? null : ($v['replaced_at'] ?? $nextUpload);
                @endphp

                <tr class="odd:bg-white even:bg-gray-50/30 hover:bg-gray-50/70">
                <td class="px-3 py-2 border text-gray-500">{{ $i + 1 }}</td>

                <td class="px-3 py-2 border align-top whitespace-normal break-words break-all">
                    <div class="flex items-start gap-2 min-w-0">
                    <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-gray-900 break-words break-all [overflow-wrap:anywhere]">
                        {{ $fileName }}
                        </div>
                        <span class="inline-flex items-center rounded ring-1 ring-inset px-1 mt-1 {{ $extClass }}"
                            style="font-size:9px;line-height:10px;padding-top:1px;padding-bottom:1px;">
                        {{ strtoupper($ext ?: 'FILE') }}
                        </span>
                    </div>
                    </div>
                </td>

                <td class="px-3 py-2 border">{{ $displayRevision }}</td>
                <td class="px-3 py-2 border break-words">{{ ($v['description'] ?? '') !== '' ? $v['description'] : '-' }}</td>
                <td class="px-3 py-2 border">{{ $fmtDate($uploadAt) }}</td>
                <td class="px-3 py-2 border">{{ $changedAt ? $fmtDate($changedAt) : '-' }}</td>
                <td class="px-3 py-2 border whitespace-nowrap">{{ $sizeText }}</td>

                @if ($showActions)
                <td class="px-3 py-2 border text-center align-middle">
                    <div class="inline-flex items-center justify-center gap-1">
                    @if ($canDownload)
                        <a href="{{ route('media.berkas.version', ['berkas' => $rec->getKey(), 'index' => $chronIndex]) }}"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                        title="Download">
                        <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600" />
                        </a>
                    @endif

                    @if ($canEdit)
                        <button type="button"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                        title="Edit deskripsi"
                        @click.stop.prevent="
                            editVersion = {
                            id: {{ $rec->getKey() }},
                            index: {{ $chronIndex }},
                            name: @js($fileName),
                            description: @js($v['description'] ?? '')
                            };
                            $dispatch('open-modal', { id: 'edit-berkas-doc-version-{{ $rec->getKey() }}' });
                        ">
                        <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4 text-gray-600" />
                        </button>

                        <button type="button"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                        title="Hapus versi"
                        @click.stop.prevent="
                            toDelete = { id: {{ $rec->getKey() }}, index: {{ $chronIndex }}, name: @js($fileName) };
                            $dispatch('open-modal', { id: 'confirm-del-berkas-doc-ver-{{ $rec->getKey() }}' });
                        ">
                        <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600" />
                        </button>
                    @endif
                    </div>
                </td>
                @endif
                </tr>
            @endforeach
            </tbody>
      </table>
    </div>
  </div>

  {{-- Modal HAPUS versi --}}
  <x-filament::modal id="confirm-del-berkas-doc-ver-{{ $rec->getKey() }}" width="md" wire:ignore.self>
    <x-slot name="heading">Hapus versi?</x-slot>
    <x-slot name="description">
      <p class="text-sm text-gray-600">
        Versi <b class="font-semibold text-gray-900 break-all" x-text="toDelete.name"></b> akan dihapus. Tindakan tidak dapat dibatalkan.
      </p>
    </x-slot>
    <x-slot name="footer">
      <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'confirm-del-berkas-doc-ver-{{ $rec->getKey() }}' })">
        Batal
      </x-filament::button>
      <x-filament::button color="danger"
        x-on:click.stop.prevent="
          $dispatch('close-modal', { id: 'confirm-del-berkas-doc-ver-{{ $rec->getKey() }}' });
          window.Livewire.find(pageId).call('onDocDeleteVersion', { type: 'berkas', id: toDelete.id, index: toDelete.index });
        ">
        Hapus
      </x-filament::button>
    </x-slot>
  </x-filament::modal>

  {{-- Modal EDIT deskripsi revisi --}}
  @php($heading=null) @php($description=null) @php($footer=null)
  <x-filament::modal id="edit-berkas-doc-version-{{ $rec->getKey() }}" width="2xl" wire:ignore.self>
    <x-slot name="heading">Edit deskripsi revisi</x-slot>
    <x-slot name="description">
      <div class="text-sm text-gray-600">
        Ubah deskripsi untuk file
        <b class="font-semibold text-gray-900 break-all" x-text="editVersion.name"></b>
      </div>
      <div class="mt-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Revisi</label>
        <textarea x-model="editVersion.description" rows="4" class="fi-input block w-full rounded-lg border-gray-300 text-sm"
                  placeholder="Tulis deskripsi perubahan..."></textarea>
      </div>
    </x-slot>
    <x-slot name="footer">
      <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'edit-berkas-doc-version-{{ $rec->getKey() }}' })">
        Batal
      </x-filament::button>
      <x-filament::button color="primary"
        x-on:click.stop.prevent="
          const payload = {
            type: 'berkas',
            id: Number(editVersion.id ?? 0),
            index: Number(editVersion.index ?? -1),
            description: String(editVersion.description ?? '')
          };
          window.Livewire.find(pageId).call('onDocUpdateVersionDesc', payload);
          $dispatch('close-modal', { id: 'edit-berkas-doc-version-{{ $rec->getKey() }}' });
          editVersion = { id: null, index: null, name: '', description: '' };
        ">
        Simpan
      </x-filament::button>
    </x-slot>
  </x-filament::modal>
</div>
@else
  <p class="text-sm text-gray-500 mt-2">Belum ada riwayat   .</p>
@endif
