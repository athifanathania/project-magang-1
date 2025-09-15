@php
    /** @var \Illuminate\Database\Eloquent\Model $record */
    $versions = collect($record->versions ?? [])->values();
    $canEdit  = auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false;
    $canDl    = $canEdit || (auth()->user()?->hasAnyRole(['Staff']) ?? false);

    $type = \Illuminate\Support\Str::of(class_basename($record))
        ->lower()->replace('imm','')->trim()
        ->replace(['manualmutu','instruksistandar'], ['manual-mutu','instruksi-standar']);
    $type = (string) $type;
@endphp

<div
  x-data="{
    editV: { id: {{ $record->getKey() }}, type: '{{ $type }}', index: null, name: '', description: '' },
    delV:  { id: {{ $record->getKey() }}, type: '{{ $type }}', index: null, name: '' },
  }"
  x-on:imm-edit-version.window="
      editV.index = $event.detail.index;
      editV.name  = $event.detail.name ?? '';
      editV.description = $event.detail.description ?? '';
      $dispatch('open-modal', { id: 'imm-edit-version-modal-{{ $record->getKey() }}' });
  "
  x-on:imm-delete-version.window="
      delV.index = $event.detail.index;
      delV.name  = $event.detail.name ?? '';
      $dispatch('open-modal', { id: 'imm-delete-version-modal-{{ $record->getKey() }}' });
  "
>
  {{-- header info singkat tetap --}}
  {{-- ... (bagian atas tetap sama seperti sebelumnya) --}}

  <div class="rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden mt-4">
    <table class="w-full text-sm border-collapse table-fixed">
      <colgroup>
        <col class="w-12"><col><col class="w-28"><col><col class="w-32"><col class="w-32"><col class="w-28">
      </colgroup>
      <thead class="bg-gray-50/80">
        <tr class="text-gray-600">
          <th class="px-3 py-2 text-left border">No</th>
          <th class="px-3 py-2 text-left border">Nama Dokumen</th>
          <th class="px-3 py-2 text-left border">Revisi</th>
          <th class="px-3 py-2 text-left border">Deskripsi Revisi</th>
          <th class="px-3 py-2 text-left border">Tgl Terbit</th>
          <th class="px-3 py-2 text-left border">Tgl Ubah</th>
          <th class="px-3 py-2 text-left border">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($versions as $i => $v)
          @php
            $uploaded = \Illuminate\Support\Arr::get($v,'uploaded_at');
            $replaced = \Illuminate\Support\Arr::get($v,'replaced_at');
            $up = $uploaded ? \Illuminate\Support\Carbon::parse($uploaded)->format('d M Y H:i') : '-';
            $rp = $replaced ? \Illuminate\Support\Carbon::parse($replaced)->format('d M Y H:i') : '-';
          @endphp
          <tr class="odd:bg-white even:bg-gray-50/40 hover:bg-gray-50 align-top">
            <td class="px-3 py-2 border">{{ $i+1 }}</td>
            <td class="px-3 py-2 border break-words">
              <div class="font-medium text-gray-900">{{ $v['filename'] ?? '-' }}</div>
            </td>
            <td class="px-3 py-2 border">{{ $v['revision'] ?? '-' }}</td>
            <td class="px-3 py-2 border whitespace-pre-line">{{ $v['description'] ?? '-' }}</td>
            <td class="px-3 py-2 border">{{ $up }}</td>
            <td class="px-3 py-2 border">{{ $rp }}</td>
            <td class="px-3 py-1 border">
              <div class="flex items-center gap-1">
                @if ($canDl)
                  <a href="{{ route('media.imm.version', ['type'=>$type,'id'=>$record->getKey(),'index'=>$i]) }}"
                     class="inline-flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100"
                     title="Download">
                    <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600" />
                  </a>
                @endif
                @if ($canEdit)
                  <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100"
                          title="Edit deskripsi"
                          @click.stop="
                            editV.index = {{ $i }};
                            editV.name  = @js($v['filename'] ?? '-');
                            editV.description = @js($v['description'] ?? '');
                            $dispatch('open-modal', { id: 'imm-edit-version-modal-{{ $record->getKey() }}' });
                          ">
                    <x-filament::icon icon="heroicon-m-pencil-square" class="w-4 h-4 text-gray-700" />
                  </button>

                  <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-md hover:bg-gray-100"
                          title="Hapus versi"
                          @click.stop="
                            delV.index = {{ $i }};
                            delV.name  = @js($v['filename'] ?? '-');
                            $dispatch('open-modal', { id: 'imm-delete-version-modal-{{ $record->getKey() }}' });
                          ">
                    <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600" />
                  </button>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada riwayat.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Modal: EDIT deskripsi versi --}}
  <x-filament::modal id="imm-edit-version-modal-{{ $record->getKey() }}" width="lg" wire:ignore.self>
    <x-slot name="heading">Edit Deskripsi Versi</x-slot>
    <x-slot name="description">
      <div class="text-sm text-gray-600">
        Dokumen: <b x-text="editV.name"></b>
      </div>
      <textarea x-model="editV.description" class="mt-3 w-full rounded-md border-gray-300 text-sm" rows="5"
                placeholder="Tulis deskripsi perubahan..."></textarea>
    </x-slot>
    <x-slot name="footer">
      <x-filament::button color="gray" type="button"
        x-on:click="$dispatch('close-modal', { id: 'imm-edit-version-modal-{{ $record->getKey() }}' })">Batal</x-filament::button>
      <x-filament::button color="primary" type="button"
        x-on:click.stop.prevent="
          const y = window.scrollY;
          $dispatch('close-modal', { id: 'imm-edit-version-modal-{{ $record->getKey() }}' });
          setTimeout(() => {
            window.Livewire.dispatch('imm-update-version-desc', {
              type: editV.type, id: editV.id, index: editV.index, description: editV.description
            });
            setTimeout(() => { window.location.replace(window.location.pathname + window.location.search); }, 200);
            window.scrollTo({ top: y, behavior: 'auto' });
          }, 150);
        ">Simpan</x-filament::button>
    </x-slot>
  </x-filament::modal>

  {{-- Modal: HAPUS versi --}}
  <x-filament::modal id="imm-delete-version-modal-{{ $record->getKey() }}" width="md" wire:ignore.self>
    <x-slot name="heading">Hapus versi dokumen?</x-slot>
    <x-slot name="description">
      <p class="text-sm text-gray-600">Versi <b x-text="delV.name"></b> akan dihapus. Tindakan tidak dapat dibatalkan.</p>
    </x-slot>
    <x-slot name="footer">
      <x-filament::button color="gray" type="button"
        x-on:click="$dispatch('close-modal', { id: 'imm-delete-version-modal-{{ $record->getKey() }}' })">Batal</x-filament::button>
      <x-filament::button color="danger" type="button"
        x-on:click.stop.prevent="
          const y = window.scrollY;
          $dispatch('close-modal', { id: 'imm-delete-version-modal-{{ $record->getKey() }}' });
          setTimeout(() => {
            window.Livewire.dispatch('imm-delete-version', { type: delV.type, id: delV.id, index: delV.index });
            setTimeout(() => { window.location.replace(window.location.pathname + window.location.search); }, 200);
            window.scrollTo({ top: y, behavior: 'auto' });
          }, 150);
        ">Hapus</x-filament::button>
    </x-slot>
  </x-filament::modal>
</div>
