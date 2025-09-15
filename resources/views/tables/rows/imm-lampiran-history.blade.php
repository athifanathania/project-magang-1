{{-- resources/views/tables/rows/imm-lampiran-history.blade.php --}}
@php
    /** @var \Illuminate\Database\Eloquent\Model $record */
    $rec = isset($getRecord) ? $getRecord() : ($record ?? null);

    // Selalu tarik data terbaru dari DB
    if ($rec) $rec = $rec->refresh();

    $all      = collect($rec?->file_versions ?? []);
    $versions = $all->reverse()->values();

    // Role logic
    $canEdit     = auth()->user()?->hasAnyRole(['Admin','Editor']);
    $canDelete   = $canEdit;
    $canDownload = auth()->user()?->hasAnyRole(['Admin','Editor','Staff']);

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
            return $c->setTimezone($tz)
                    ->locale(app()->getLocale())
                    ->translatedFormat('d M Y H:i');
        } catch (\Throwable $e) {
            try {
                return \Illuminate\Support\Carbon::parse($d, 'UTC')->setTimezone($tz)->translatedFormat('d M Y H:i');
            } catch (\Throwable $e2) {
                return (string) $d;
            }
        }
    };
@endphp

@if ($versions->isNotEmpty())
<div
  x-data="{ toDeleteVersion: { docId: null, index: null, name: '' } }"
>
  <div class="mt-4">
    <h3 class="text-sm font-semibold">Riwayat Dokumen</h3>

    <div class="mt-2 rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
      <table class="w-full text-sm border-collapse table-fixed">
        <colgroup>
          <col class="w-12">   {{-- No --}}
          <col>                {{-- Nama Dokumen --}}
          <col class="w-28">   {{-- Revisi --}}
          <col class="w-64">   {{-- Deskripsi Revisi --}}
          <col class="w-36">   {{-- Tgl Terbit --}}
          <col class="w-36">   {{-- Tgl Ubah --}}
          <col class="w-28">   {{-- Aksi --}}
        </colgroup>

        <thead class="bg-gray-50/80">
          <tr class="text-gray-600">
            <th class="px-3 py-2 border">No</th>
            <th class="px-3 py-2 border">Nama Dokumen</th>
            <th class="px-3 py-2 border">Revisi</th>
            <th class="px-3 py-2 border">Deskripsi Revisi</th>
            <th class="px-3 py-2 border">Tgl Terbit</th>
            <th class="px-3 py-2 border">Tgl Ubah</th>
            <th class="px-3 py-2 border">Aksi</th>
          </tr>
        </thead>

        <tbody>
          @foreach ($versions as $i => $v)
            @php
              $originalIndex = $all->count() - 1 - $i;
            @endphp

            <tr class="odd:bg-white even:bg-gray-50/30 hover:bg-gray-50/70">
              <td class="px-3 py-2 border text-gray-500">{{ $i + 1 }}</td>
              <td class="px-3 py-2 border">{{ $v['nama_dokumen'] ?? '-' }}</td>
              <td class="px-3 py-2 border">{{ $v['revision'] ?? '-' }}</td>
              <td class="px-3 py-2 border">{{ $v['description'] ?? '-' }}</td>
              <td class="px-3 py-2 border">{{ $fmtDate($v['uploaded_at'] ?? null) }}</td>
              <td class="px-3 py-2 border">{{ $fmtDate($v['replaced_at'] ?? null) }}</td>

              <td class="px-3 py-2 border">
                <div class="flex items-center gap-1">
                  @if ($canDownload)
                    <a href="{{ route('media.imm.version', ['id' => $rec->getKey(), 'index' => $originalIndex]) }}"
                       class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                       title="Download">
                      <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600"/>
                    </a>
                  @endif

                  @if ($canEdit)
                    <button type="button"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                      title="Edit revisi"
                      @click.stop.prevent="alert('TODO: Edit revisi IMM')">
                      <x-filament::icon icon="heroicon-m-pencil" class="w-4 h-4 text-gray-600"/>
                    </button>
                  @endif

                  @if ($canDelete)
                    <button type="button"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                      title="Hapus revisi"
                      @click.stop.prevent="
                          toDeleteVersion = { docId: {{ $rec->getKey() }}, index: {{ $originalIndex }}, name: @js($v['nama_dokumen'] ?? '-') };
                          $dispatch('open-modal', { id: 'confirm-delete-imm-version-{{ $rec->getKey() }}' });
                      ">
                      <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600"/>
                    </button>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Modal konfirmasi hapus revisi --}}
  <x-filament::modal id="confirm-delete-imm-version-{{ $rec->getKey() }}" width="xl" wire:ignore.self>
    <x-slot name="heading">Hapus revisi dokumen?</x-slot>
    <x-slot name="description">
      <p class="text-sm text-gray-600 break-words">
        Revisi <b class="font-semibold text-gray-900 break-all" x-text="toDeleteVersion.name"></b> akan dihapus.<br>
        Tindakan ini tidak dapat dibatalkan.
      </p>
    </x-slot>
    <x-slot name="footer">
      <x-filament::button color="gray"
        x-on:click="$dispatch('close-modal', { id: 'confirm-delete-imm-version-{{ $rec->getKey() }}' })">
        Batal
      </x-filament::button>
      <x-filament::button color="danger"
        x-on:click.stop.prevent="
          $dispatch('close-modal', { id: 'confirm-delete-imm-version-{{ $rec->getKey() }}' });
          window.Livewire.dispatch('delete-imm-lampiran-version', {
            lampiranId: toDeleteVersion.docId,
            index: toDeleteVersion.index
          });
        ">
        Hapus
      </x-filament::button>
    </x-slot>
  </x-filament::modal>
</div>
@else
  <p class="text-sm text-gray-500 mt-2">Belum ada riwayat revisi.</p>
@endif
