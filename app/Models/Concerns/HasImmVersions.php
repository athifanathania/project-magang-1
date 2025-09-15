<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

trait HasImmVersions
{
    /** Disk penyimpanan file versi (privat agar di-stream via route). */
    protected static function storageDisk(): string
    {
        return 'private';
    }

    /** Folder dasar tiap model; override di masing-masing model. */
    abstract protected static function storageBaseDir(): string;

    /** Normalisasi keywords ke array string rapi. */
    protected function normalizeKeywords(mixed $value): array
    {
        if (blank($value)) return [];
        if (is_string($value)) {
            $j = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($j)) $value = $j;
            else $value = preg_split('/\s*,\s*/u', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }
        if ($value instanceof \Illuminate\Support\Collection) $value = $value->all();
        if (!is_array($value)) $value = [(string) $value];

        return collect($value)
            ->flatten()
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Mutator keywords -> json. */
    public function setKeywordsAttribute($value): void
    {
        $this->attributes['keywords'] = json_encode($this->normalizeKeywords($value));
    }

    /** Versi sebagai collection (selalu array). */
    public function versionsList(): \Illuminate\Support\Collection
    {
        $raw = $this->file_versions ?? [];
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            $raw = (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : [];
        }
        return collect($raw)->values();
    }


    /** Ambil versi terakhir (current) atau null. */
    public function latestVersion(): ?array
    {
        return $this->versionsList()->last() ?: null;
    }

    /** Hitung kode revisi berikutnya: Rev01, Rev02, ... */
    public function nextRevisionCode(): string
    {
        $last = $this->latestVersion();
        if (!$last || !preg_match('/^rev(\d{1,})$/i', (string) ($last['revision'] ?? ''), $m)) {
            return 'Rev01';
        }
        $n = (int) $m[1] + 1;
        return 'Rev' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        // (kalau mau 3 digit: STR_PAD_LEFT ke 3)
    }

    /**
     * Tambah versi baru dari upload.
     * - Simpan file ke disk privat.
     * - Close versi lama (set replaced_at).
     * - Set kolom file/current_revision ke versi baru.
     *
     * @return array versi baru (jika sukses)
     */
    public function addVersionFromUpload(UploadedFile $file, ?string $description = null, ?string $revision = null): array
    {
        if (!$this->exists) {
            // butuh ID untuk folder; pastikan model disimpan dulu
            $this->save();
        }

        $disk = static::storageDisk();
        $dir  = rtrim(static::storageBaseDir(), '/').'/'.$this->getKey();

        $originalName = $file->getClientOriginalName();
        $ext   = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $size  = (int) $file->getSize();

        $storedName = now()->format('Ymd_His') . '-' . Str::random(6) . '-' . $originalName;
        $path = Storage::disk($disk)->putFileAs($dir, $file, $storedName);

        $versions = $this->versionsList();

        // tutup versi lama jika belum punya replaced_at
        if ($versions->isNotEmpty()) {
            $last = $versions->pop();
            if (empty($last['replaced_at'])) {
                $last['replaced_at'] = now()->toISOString();
            }
            $versions->push($last);
        }

        $revCode = $revision ?: $this->nextRevisionCode();

        $newVersion = [
            'revision'     => $revCode,
            'filename'     => $originalName,
            'description'  => $description,
            'file_path'    => $path,
            'file_ext'     => $ext,
            'file_size'    => $size,
            'uploaded_at'  => now()->toISOString(),
            'replaced_at'  => null,
        ];

        $versions->push($newVersion);

        // sync ke kolom model
        $this->file_versions    = $versions->all();
        $this->file             = $path;
        $this->current_revision = $revCode; // kalau kolom ini sudah dihapus, baris ini boleh kamu hilangkan
        if (blank($this->effective_at)) {
            $this->effective_at = now()->toDateString();
        }

        $this->save();

        return $newVersion;
    }

    /**
     * Hapus versi berdasarkan index (0-based) sesuai urutan tersimpan.
     * - Menghapus file fisik
     * - Perbarui kolom file/current_revision jika versi aktif dihapus
     */
    public function deleteVersionAtIndex(int $index): bool
    {
        $versions = $this->versionsList();
        if ($index < 0 || $index >= $versions->count()) return false;

        $removed = $versions->pull($index);

        // hapus file fisik (abaikan error)
        if (!empty($removed['file_path'])) {
            Storage::disk(static::storageDisk())->delete($removed['file_path']);
        }

        // jika yang dihapus adalah versi terakhir (aktif), fallback ke versi sebelumnya
        if ($index === $versions->count()) {
            $prev = $versions->last();
            $this->file             = $prev['file_path'] ?? null;
            $this->current_revision = $prev['revision']  ?? null;
        }

        $this->file_versions = $versions->values()->all();
        $this->save();

        return true;
    }

    /** Cari index versi by revision (Rev01, Rev02, ...). */
    public function findVersionIndexByRevision(string $revision): ?int
    {
        $idx = $this->versionsList()
            ->search(fn ($v) => strcasecmp((string) ($v['revision'] ?? ''), $revision) === 0);
        return $idx === false ? null : (int) $idx;
    }
}
