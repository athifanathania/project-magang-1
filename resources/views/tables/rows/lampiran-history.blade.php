@php
    /** @var \App\Models\Lampiran $record */
    $rec = isset($getRecord) ? $getRecord() : ($record ?? null);

    // SELALU tarik versi terbaru dari DB (hindari stale cache)
    if ($rec) $rec = $rec->refresh();

    $all      = collect($rec?->file_versions ?? []);
    $versions = $all->reverse()->values();

    $tz = auth()->user()->timezone ?? config('app.timezone');
    if (blank($tz) || strtoupper($tz) === 'UTC') {
        $tz = 'Asia/Jakarta';
    }

    $fmtDate = function ($d) use ($tz) {
        if (blank($d)) return '-';

        try {
            // 1) EPOCH: detik / milidetik
            if (is_numeric($d)) {
                $i = (int) $d;
                $c = strlen((string) $i) >= 13
                    ? \Illuminate\Support\Carbon::createFromTimestampMs($i, 'UTC')
                    : \Illuminate\Support\Carbon::createFromTimestamp($i, 'UTC');
            } else {
                $s = trim((string) $d);

                // 2) ISO8601 (dengan Z atau offset)
                if (preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $s)) {
                    $c = \Illuminate\Support\Carbon::parse($s);
                } else {
                    // 3) Naive: coba anggap APP TZ dulu
                    $fmt = str_contains($s, '.') ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
                    $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $s, $tz);

                    // Jika “masa depan” jauh (indikasi aslinya UTC), reinterpret sbg UTC
                    if ($c->greaterThan(\Illuminate\Support\Carbon::now($tz)->addHours(2))) {
                        $c = \Illuminate\Support\Carbon::createFromFormat($fmt, $s, 'UTC')->setTimezone($tz);
                    }
                }
            }

            return $c->setTimezone($tz)
                    ->locale(app()->getLocale())
                    ->translatedFormat('d M Y H:i');
        } catch (\Throwable $e) {
            // Fallback terakhir
            try {
                return \Illuminate\Support\Carbon::parse($d, 'UTC')->setTimezone($tz)->translatedFormat('d M Y H:i');
            } catch (\Throwable $e2) {
                return (string) $d;
            }
        }
    };

    $fmtSize = function ($bytes) {
        if (! is_numeric($bytes)) return '-';
        $u = ['B','KB','MB','GB','TB']; $i=0; $s=(float)$bytes;
        while ($s>=1024 && $i<count($u)-1) { $s/=1024; $i++; }
        return number_format($s, $i?1:0).' '.$u[$i];
    };

    $extColor = function ($ext) {
        return match (strtolower((string) $ext)) {
            'pdf'                               => 'bg-red-100 text-red-700 ring-red-200',
            'xlsx','xls','csv'                  => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'doc','docx'                        => 'bg-blue-100 text-blue-700 ring-blue-200',
            'ppt','pptx'                        => 'bg-orange-100 text-orange-700 ring-orange-200',
            'jpg','jpeg','png','webp','svg'     => 'bg-purple-100 text-purple-700 ring-purple-200',
            default                             => 'bg-gray-100 text-gray-700 ring-gray-200',
        };
    };
@endphp

@if ($versions->isNotEmpty())
<div
  x-data="{
    toDeleteVersion: { lampiranId: null, index: null, name: '' }
  }"
>
  <div class="mt-4">
    <h3 class="text-sm font-semibold">Riwayat lampiran</h3>

    <div class="mt-2 rounded-xl ring-1 ring-gray-200 shadow-sm overflow-hidden">
      <table class="w-full text-sm border-collapse table-fixed">
        <colgroup>
          <col class="w-12">
          <col>
          <col class="w-44">
          <col class="w-44">
          <col class="w-24">
          @if (auth()->user()?->hasAnyRole(['Admin','Editor']))
            <col class="w-28">
          @endif
        </colgroup>

        <thead class="bg-gray-50/80">
          <tr class="text-gray-600">
            <th class="px-3 py-2 text-left border border-gray-200">#</th>
            <th class="px-3 py-2 text-left border border-gray-200">Nama File</th>
            <th class="px-3 py-2 text-left border border-gray-200">Tanggal Upload</th>
            <th class="px-3 py-2 text-left border border-gray-200">Tanggal Ubah</th>
            <th class="px-3 py-2 text-left border border-gray-200">Ukuran</th>
            @if (auth()->user()?->hasAnyRole(['Admin','Editor']))
              <th class="px-3 py-2 text-left border border-gray-200">Aksi</th>
            @endif
          </tr>
        </thead>

        <tbody>
          @foreach ($versions as $i => $v)
            @php
              $originalIndex = $all->count() - 1 - $i;
              $ext = $v['ext'] ?? \Illuminate\Support\Str::of($v['filename'] ?? '')->afterLast('.')->lower();
            @endphp

            <tr class="odd:bg-white even:bg-gray-50/30 hover:bg-gray-50/70">
              <td class="px-3 py-2 text-gray-500 border">{{ $i + 1 }}</td>

              <td class="px-3 py-2 border">
                <div class="flex items-start gap-2">
                  <x-filament::icon icon="heroicon-m-document-text" class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
                  <div class="whitespace-normal break-words break-all">
                    <div class="font-medium text-gray-900">{{ $v['filename'] ?? '-' }}</div>
                    <span class="inline-flex items-center rounded ring-1 ring-inset {{ $extColor($ext) }} px-1"
                      style="font-size:9px; line-height:10px; padding-top:1px; padding-bottom:1px;">
                      {{ strtoupper($ext ?: 'FILE') }}
                    </span>
                  </div>
                </div>
              </td>

              <td class="px-3 py-2 border whitespace-normal">{{ $fmtDate($v['uploaded_at'] ?? null) }}</td>
              <td class="px-3 py-2 border whitespace-normal">{{ $fmtDate($v['replaced_at'] ?? null) }}</td>
              <td class="px-3 py-2 border whitespace-nowrap">{{ $fmtSize($v['size'] ?? null) }}</td>

              @if (auth()->user()?->hasAnyRole(['Admin','Editor']))
                <td class="px-3 py-2 border">
                  <div class="flex items-center gap-1">
                    <a href="{{ route('media.lampiran.version', ['lampiran' => $rec->getKey(), 'index' => $originalIndex]) }}"
                       class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                       title="Download">
                      <x-filament::icon icon="heroicon-m-arrow-down-tray" class="w-4 h-4 text-blue-600" />
                    </a>

                    {{-- tombol ikon 🗑️ tetap sama: set state + open modal lokal --}}
                    <button type="button"
                    class="inline-flex items-center justify-center w-7 h-7 rounded-md hover:bg-gray-100"
                    title="Hapus versi"
                    @click.stop.prevent="
                        toDeleteVersion = { lampiranId: {{ $rec->getKey() }}, index: {{ $originalIndex }}, name: @js($v['filename'] ?? '-') };
                        $dispatch('open-modal', { id: 'confirm-delete-version-{{ $rec->getKey() }}' });
                    ">
                    <x-filament::icon icon="heroicon-m-trash" class="w-4 h-4 text-red-600" />
                    </button>
                  </div>
                </td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Modal konfirmasi (satu kali per lampiran) --}}
 <x-filament::modal id="confirm-delete-version-{{ $rec->getKey() }}" width="xl" wire:ignore.self>
    <x-slot name="heading">Hapus versi lampiran?</x-slot>

    <x-slot name="description">
        <p class="text-sm leading-6 text-gray-600 break-words" style="overflow-wrap:anywhere;">
            Versi <b class="font-semibold text-gray-900 break-all" x-text="toDeleteVersion.name"></b> akan dihapus. <br>
            Tindakan tidak dapat dibatalkan.
        </p>
    </x-slot>

    <x-slot name="footer">
      <x-filament::button color="gray" type="button"
        x-on:click="$dispatch('close-modal', { id: 'confirm-delete-version-{{ $rec->getKey() }}' })">
        Batal
      </x-filament::button>

      <x-filament::button color="danger" type="button"
        x-on:click.stop.prevent="
            const y = window.scrollY;
            $dispatch('close-modal', { id: 'confirm-delete-version-{{ $rec->getKey() }}' });

            setTimeout(() => {
                // kirim ke parent untuk eksekusi server-side
                window.Livewire.dispatch('delete-lampiran-version', {
                    lampiranId: toDeleteVersion.lampiranId,
                    index: toDeleteVersion.index
                });

                // ✅ beri sedikit jeda lalu reload halaman utama
                setTimeout(() => {
                    window.location.replace(window.location.pathname + window.location.search);
                }, 300);

                document.body.classList.remove('fi-modal-open','overflow-hidden');
                document.documentElement.classList.remove('overflow-hidden');
                document.body.style.overflow='';
                document.documentElement.style.overflow='';
                window.scrollTo({ top: y, behavior: 'auto' });
            }, 150);
        "
    >
        Hapus
    </x-filament::button>

    </x-slot>
  </x-filament::modal>
</div>
@else
  <p class="text-sm text-gray-500 mt-2">Belum ada riwayat.</p>
@endif
