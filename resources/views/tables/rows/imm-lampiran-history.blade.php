{{-- resources/views/tables/rows/imm-lampiran-history.blade.php --}}
@php
    use Illuminate\Support\Facades\Storage;

    /** @var \Illuminate\Database\Eloquent\Model $record */
    $rec = isset($getRecord) ? $getRecord() : ($record ?? null);
    if ($rec) $rec = $rec->refresh();

    $lampiranName = $rec?->nama ?: 'Lampiran';

    $all = collect($rec?->file_versions ?? []);

    // sisipkan baris file aktif di akhir lalu nanti direverse (jadi paling atas)
    if (!blank($rec?->file)) {
        $currSize = null;
        try {
            if (Storage::disk('private')->exists($rec->file)) {
                $currSize = Storage::disk('private')->size($rec->file);
            }
        } catch (\Throwable $e) {}

        $all->push([
            'filename'    => basename($rec->file),
            'path'        => $rec->file,
            'size'        => $currSize,
            'ext'         => pathinfo($rec->file, PATHINFO_EXTENSION),
            // pakai updated_at (fallback created_at) sbg tgl terbit file aktif
            'uploaded_at' => optional($rec->updated_at ?? $rec->created_at)->toDateTimeString(),
            'replaced_at' => null,
            // biar label REV konsisten saja—hitung di view dari index
        ]);
    }

    // tampilkan terbaru dulu
    $versions = $all->reverse()->values();

    $canEdit     = auth()->user()?->hasAnyRole(['Admin','Editor']);
    $canDelete   = $canEdit;
    $canDownload = auth()->user()?->hasAnyRole(['Admin','Editor','Staff']);
    $showActions = $canEdit || $canDelete || $canDownload;

    $tz = auth()->user()->timezone ?? config('app.timezone');
    if (blank($tz) || strtoupper($tz) === 'UTC') $tz = 'Asia/Jakarta';

    $fmtDate = function ($d) use ($tz) {
        if (blank($d)) return '-';
        try {
            if (is_numeric($d)) {
                $i = (int) $d;
                $c = strlen((string) $i) >= 13
                    ? \Illuminate\Support\Carbon::createFromTimestampMs($i, 'UTC')
                    : \Illuminate\Support\Carbon::createFromTimestamp($i, 'UTC');
            } else {
                $s = trim((string) $d);
                if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $s)) {
                    $c = \Illuminate\Support\Carbon::parse($s);
                } else {
                    $fmt = str_contains($s, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
                    $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $s, $tz);
                    if ($c->greaterThan(\Illuminate\Support\Carbon::now($tz)->addHours(2))) {
                        $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $s, 'UTC')->setTimezone($tz);
                    }
                }
            }
            return $c->setTimezone($tz)->format('d/m/y H.i');
        } catch (\Throwable $e) {
            try { return \Illuminate\Support\Carbon::parse($d, 'UTC')->setTimezone($tz)->format('d/m/y H.i'); }
            catch (\Throwable $e2) { return (string) $d; }
        }
    };

    $fmtSize = function ($bytes) {
        if (!is_numeric($bytes) || $bytes < 0) return '-';
        $u = ['B','KB','MB','GB','TB']; $i=0; $s=(float)$bytes;
        while ($s>=1024 && $i<count($u)-1) { $s/=1024; $i++; }
        return number_format($s, $i?1:0).' '.$u[$i];
    };

    // === sama persis seperti modal: warna label berdasar ekstensi
    $extColor = function ($ext) {
        return match (strtolower((string) $ext)) {
            'pdf'                           => 'bg-red-100 text-red-700 ring-red-200',
            'xlsx','xls','csv'              => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'doc','docx'                    => 'bg-blue-100 text-blue-700 ring-blue-200',
            'ppt','pptx'                    => 'bg-orange-100 text-orange-700 ring-orange-200',
            'jpg','jpeg','png','webp','svg' => 'bg-purple-100 text-purple-700 ring-purple-200',
            default                         => 'bg-gray-100 text-gray-700 ring-gray-200',
        };
    };
@endphp

@if ($versions->isNotEmpty())
<div
  x-data="{
    toDeleteVersion: { lampiranId: null, index: null, name: '' },
    editVersion:     { lampiranId: null, index: null, name: '', description: '' },
    pageId: '{{ $this->getId() }}', 
  }"
>
  <div class="mt-4">
    <h3 class="text-sm font-semibold">Riwayat file dari lampiran <span class="text-gray-900">"{{ $lampiranName }}"</span></h3>

    <div class="mt-2 rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
      <div class="max-w-full overflow-x-auto">
      <table class="w-full text-sm border-collapse table-fixed">
        <colgroup>
          <col class="w-10">             {{-- No --}}
          <col >        {{-- Nama File (FIXED, supaya wrap) --}}
          <col class="w-20">             {{-- Revisi --}}
          <col class="w-40">             {{-- Deskripsi --}}
          <col class="w-20">             {{-- Tgl Terbit --}}
          <col class="w-20">             {{-- Tgl Ubah --}}
          <col class="w-20">             {{-- Ukuran --}}
          @if ($showActions)
            <col class="w-20">           {{-- Aksi --}}
          @endif
        </colgroup>

        <thead class="bg-gray-50/80">
          <tr class="text-gray-600">
            <th class="px-3 py-2 border">No</th>
            <th class="px-3 py-2 border">Nama File</th>
            <th class="px-3 py-2 border">Revisi</th>
            <th class="px-3 py-2 border">Deskripsi Revisi</th>
            <th class="px-3 py-2 border">Tgl Terbit</th>
            <th class="px-3 py-2 border">Tgl Ubah</th>
            <th class="px-3 py-2 border">Ukuran</th>
            @if ($showActions)
            <th class="px-3 py-2 border">Aksi</th>
            @endif
          </tr>
        </thead>

        <tbody>
          @foreach ($versions as $i => $v)
            @php
                $originalIndex = $all->count() - 1 - $i;

                // nama file asli (fallback basename dari path)
                $fileName = trim((string)($v['filename'] ?? basename((string)($v['path'] ?? '')))) ?: '-';

                // ekstensi + warna label
                $ext = strtolower($v['ext'] ?? pathinfo($fileName, PATHINFO_EXTENSION) ?? '');
                $extClass = $extColor($ext);

                // ukuran
                $sizeBytes = $v['size'] ?? null;
                if (!$sizeBytes && !empty($v['path']) && Storage::disk('private')->exists($v['path'])) {
                    try { $sizeBytes = Storage::disk('private')->size($v['path']); } catch (\Throwable $e) {}
                }
                $sizeText = $sizeBytes ? $fmtSize($sizeBytes) : '-';

                // REVxx
                $revRaw = (string)($v['revision'] ?? '');
                $revNum = preg_match('/\d+/', $revRaw, $m) ? max(1, (int)$m[0]) : ($originalIndex + 1);
                $displayRevision = 'REV' . str_pad($revNum, 2, '0', STR_PAD_LEFT);
            @endphp

            <tr class="odd:bg-white even:bg-gray-50/30 hover:bg-gray-50/70">
              <td class="px-3 py-2 border text-gray-500">{{ $i + 1 }}</td>
              <td class="px-3 py-2 border align-top whitespace-normal break-words break-all w-[200px] max-w-[200px] overflow-hidden">
                <div class="flex items-start gap-2 min-w-0">
                  <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                  <div class="min-w-0 flex-1">
                    <div class="font-medium text-gray-900 break-words break-all [overflow-wrap:anywhere]">
                      {{ $fileName }}
                    </div>
                    <span
                      class="inline-flex items-center rounded ring-1 ring-inset px-1 mt-1 {{ $extClass }}"
                      style="font-size:9px; line-height:10px; padding-top:1px; padding-bottom:1px;"
                    >
                      {{ strtoupper($ext ?: 'FILE') }}
                    </span>
                  </div>
                </div>
              </td>

              <td class="px-3 py-2 border">{{ $displayRevision }}</td>
              <td class="px-3 py-2 border">{{ $v['description'] ?? '-' }}</td>
              @php
                  $originalIndex = $all->count() - 1 - $i;

                  // REVxx dari posisi (hindari 00)
                  $revNum = max(1, $originalIndex + 1);
                  $displayRevision = 'REV' . str_pad($revNum, 2, '0', STR_PAD_LEFT);
              @endphp
              <td class="px-3 py-2 border">{{ $fmtDate($v['uploaded_at'] ?? null) }}</td>
              <td class="px-3 py-2 border">{{ $fmtDate($v['replaced_at'] ?? null) }}</td>
              <td class="px-3 py-2 border whitespace-nowrap">{{ $sizeText }}</td>

              @if ($showActions)
              <td class="px-3 py-2 border text-center align-middle">
                <div class="inline-flex items-center justify-center gap-1">
                  @if ($canDownload)
                    <a href="{{ route('media.imm.version', ['id' => $rec->getKey(), 'index' => $originalIndex]) }}"
                       class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100" title="Download">
                      <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600"/>
                    </a>
                  @endif

                  @if ($canEdit)
                    <button type="button"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                      title="Edit revisi"
                      @click.stop.prevent="
                        editVersion = {
                          lampiranId: {{ $rec->getKey() }},
                          index: {{ $originalIndex }},
                          name:  @js($fileName),
                          description: @js($v['description'] ?? '')
                        };
                        $dispatch('open-modal', { id: 'edit-imm-version-{{ $rec->getKey() }}' });
                      ">
                      <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4 text-gray-600"/>
                    </button>
                  @endif

                  @if ($canDelete)
                    <button type="button"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                      title="Hapus revisi"
                      @click.stop.prevent="
                        toDeleteVersion = { lampiranId: {{ $rec->getKey() }}, index: {{ $originalIndex }}, name: @js($fileName) };
                        $dispatch('open-modal', { id: 'confirm-delete-imm-version-{{ $rec->getKey() }}' });
                      ">
                      <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600"/>
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

  <x-filament::modal id="confirm-delete-imm-version-{{ $rec->getKey() }}" width="xl" wire:ignore.self>
    <x-slot name="heading">Hapus versi lampiran?</x-slot>

    <x-slot name="description">
      <p class="text-sm text-gray-600 break-words">
        Versi <b class="font-semibold text-gray-900 break-all" x-text="toDeleteVersion.name"></b> akan dihapus.<br>
        Tindakan ini tidak dapat dibatalkan.
      </p>
    </x-slot>

    <x-slot name="footer">
      <x-filament::button color="gray"
        x-on:click="$dispatch('close-modal', { id: 'confirm-delete-imm-version-{{ $rec->getKey() }}' })">
        Batal
      </x-filament::button>

      <x-filament::button color="danger" type="button"
        x-on:click.stop.prevent="
          $dispatch('close-modal', { id: 'confirm-delete-imm-version-{{ $rec->getKey() }}' });
          const payload = {
            lampiranId: Number(toDeleteVersion.lampiranId),
            index: Number(toDeleteVersion.index)
          };
          window.Livewire.dispatch('imm-delete-version', payload);
        ">
        Hapus
      </x-filament::button>
    </x-slot>
  </x-filament::modal>

  @php($heading = null) @php($description = null) @php($footer = null)
  <x-filament::modal id="edit-imm-version-{{ $rec->getKey() }}" width="2xl" wire:ignore.self>
    <x-slot name="heading">Edit deskripsi revisi</x-slot>

    <x-slot name="description">
      <div class="text-sm text-gray-600">
        Ubah deskripsi untuk file
        <b class="font-semibold text-gray-900 break-all" x-text="editVersion.name"></b>
      </div>
      <div class="mt-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Revisi</label>
        <textarea
          x-model="editVersion.description"
          rows="4"
          class="fi-input block w-full rounded-lg border-gray-300 text-sm"
          placeholder="Tulis deskripsi perubahan..."
        ></textarea>
      </div>
    </x-slot>

    <x-slot name="footer">
      <x-filament::button color="gray"
        x-on:click="$dispatch('close-modal', { id: 'edit-imm-version-{{ $rec->getKey() }}' })">
        Batal
      </x-filament::button>

      <x-filament::button color="primary" type="button"
        x-on:click.stop.prevent="
          const payload = {
            lampiranId: Number(editVersion.lampiranId ?? 0),
            index:      Number(editVersion.index ?? -1),
            description:String(editVersion.description ?? '')
          };
          window.Livewire.dispatch('imm-update-version-desc', payload);
          $dispatch('close-modal', { id: 'edit-imm-version-{{ $rec->getKey() }}' });
        ">
        Simpan
      </x-filament::button>

    </x-slot>
  </x-filament::modal>
</div>
@else
  <p class="text-sm text-gray-500 mt-2">Belum ada riwayat revisi.</p>
@endif
