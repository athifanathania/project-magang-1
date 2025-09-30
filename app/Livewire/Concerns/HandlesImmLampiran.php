<?php

namespace App\Livewire\Concerns;

use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;

trait HandlesImmLampiran
{
    public function handleDeleteImmLampiran(int $lampiranId, int $docId): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $lampiran = ImmLampiran::query()
            ->whereKey($lampiranId)
            ->where('documentable_id', $docId)
            ->first();

        if (! $lampiran) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $lampiran->delete();
        $this->dispatch('$refresh');
        Notification::make()->title('Lampiran terhapus')->success()->send();
    }

    public function handleDeleteImmLampiranVersion(int $lampiranId, int $index): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        if (! $lampiranId || $index < 0) {
            Notification::make()->title('Payload hapus versi tidak valid.')->danger()->send();
            return;
        }

        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $m = ImmLampiran::find($lampiranId);
        if (! $m) {
            Notification::make()->title('Lampiran tidak ditemukan')->danger()->send();
            return;
        }

        $ok = $m->deleteVersionAtIndex($index);
        Notification::make()->title($ok ? 'Versi lampiran dihapus' : 'Versi tidak ditemukan')->{$ok ? 'success' : 'danger'}()->send();

        $this->dispatch('$refresh');
    }

    // === DIPANGGIL LANGSUNG DARI JS: window.Livewire.find(pageId).call('onDeleteImmVersion', { lampiranId, index })
    public function onDeleteImmVersion(array $payload): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $id  = (int)($payload['lampiranId'] ?? $payload['id'] ?? 0);
        $idx = (int)($payload['index'] ?? -1);

        if (! $id || $idx < 0) {
            Notification::make()->title('Payload hapus versi tidak valid.')->danger()->send();
            return;
        }

        $this->handleDeleteImmLampiranVersion($id, $idx);
    }

    public function updateVersionDescription(array $payload): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);
   
        $id   = (int)($payload['lampiranId'] ?? $payload['id'] ?? 0);
        $idx  = (int)($payload['index'] ?? -1);
        $desc = trim((string)($payload['description'] ?? ''));

        if (! $id || $idx < 0) {
            Notification::make()->title('Payload edit revisi tidak valid.')->danger()->send();
            return;
        }
        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            Notification::make()->title('Tidak diizinkan.')->danger()->send();
            return;
        }

        $m = ImmLampiran::find($id);
        if (! $m) { Notification::make()->title('Lampiran tidak ditemukan')->danger()->send(); return; }

        // Ambil raw apa adanya
        $raw = $m->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        // Pisahkan: hanya key numerik yg isinya ARRAY (valid versi), sisanya meta
        $versions = [];
        $meta     = [];
        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string)$k);
            if ($isNumeric) {
                if (is_array($v) && (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))) {
                    $versions[] = $v;      // rebase 0..n-1
                } // else: buang sampah lama (string/invalid), JANGAN dibungkus!
            } else {
                $meta[$k] = $v;
            }
        }

        $activeIndex = count($versions); // index “versi aktif” (baris teratas yang kamu sisipkan di blade)

        if ($idx < $activeIndex) {
            // Edit versi lama (sudah tersimpan di array)
            $versions[$idx]['description'] = $desc;
        } elseif ($idx === $activeIndex) {
            // Edit versi aktif (file sekarang) → simpan di meta
            $meta['__current_desc'] = $desc;
        } else {
            Notification::make()->title('Versi tidak ditemukan')->danger()->send();
            return;
        }

        // Satukan kembali (jaga urutan numerik + key meta non-numerik)
        $out = $versions;
        foreach ($meta as $k => $v) { $out[$k] = $v; }

        $m->file_versions = $out;
        $m->save();

        $this->dispatch('$refresh');
        Notification::make()->title('Deskripsi revisi diperbarui')->success()->send();
    }

}
