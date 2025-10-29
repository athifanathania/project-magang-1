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

    protected function logVersionActivity(string $event, string $message, array $properties = []): void
    {
        try {
            activity()
                ->useLog('web')
                ->causedBy(optional(auth())->user())
                ->performedOn($this)
                ->event($event)
                ->withProperties($properties + [
                    'model'      => static::class,
                    'model_id'   => $this->getKey(),
                    'route'      => optional(request())->path(),
                    'ip'         => optional(request())->ip(),
                    'user_agent' => substr((string) optional(request())->userAgent(), 0, 500),
                ])
                ->log($message);
        } catch (\Throwable $e) {
            // no-op
        }
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

    public function nextRevisionCode(): string
    {
        $last = $this->latestVersion();
        if (!$last || !preg_match('/^rev(\d{1,})$/i', (string) ($last['revision'] ?? ''), $m)) {
            return 'REV00'; 
        }
        $n = (int) $m[1] + 1;
        return 'REV' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
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

        $originalName = method_exists($file, 'getClientOriginalName')
        ? $file->getClientOriginalName()
        : (string) ($file->getClientOriginalName() ?? 'file');

        $ext  = method_exists($file, 'getClientOriginalExtension')
            ? strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION))
            : strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $size = method_exists($file, 'getSize') ? (int) $file->getSize() : null;

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

        // sinkron kolom aktif
        $this->file_versions = $versions->all();
        $this->file          = $path;
        $this->revision      = $revCode;
        if (blank($this->effective_at)) {
            $this->effective_at = now()->toDateString();
        }
        $this->save();

        $this->logVersionActivity('version_add', 'Tambah versi IMM dari upload', [
            'revision'    => $newVersion['revision'] ?? null,
            'filename'    => $newVersion['filename'] ?? null,
            'file_path'   => $newVersion['file_path'] ?? null,
            'file_ext'    => $newVersion['file_ext'] ?? null,
            'file_size'   => $newVersion['file_size'] ?? null,
            'description' => $newVersion['description'] ?? null,
        ]);

        return $newVersion;
    }

    public function deleteVersionAtIndex(int $index): bool
    {
        $versions = $this->versionsList();
        if ($index < 0 || $index >= $versions->count()) return false;

        $removed = $versions->pull($index);

        if (!empty($removed['file_path'])) {
            Storage::disk(static::storageDisk())->delete($removed['file_path']);
        }

        if ($versions->isEmpty()) {
            $this->file = null;
            $this->revision = null;
            $this->file_versions = [];
            $this->save();

            $this->logVersionActivity('version_delete', 'Hapus versi terakhir IMM', [
                'deleted_index' => $index,
                'deleted_path'  => $removed['file_path'] ?? null,
                'fallback_path' => null,
            ]);

            return true;
        }

        // kalau yang dihapus versi aktif, fallback ke sebelumnya
        if ($index === $versions->count()) {
            $prev = $versions->last();
            $this->file     = $prev['file_path'] ?? null;
            $this->revision = $prev['revision']  ?? null;
        }

        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)$i, 2, '0', STR_PAD_LEFT); 
            return $row;
        });

        // sinkronkan current revision ke terakhir
        $this->revision      = $versions->last()['revision'] ?? $this->revision;
        $this->file_versions = $versions->all();
        $this->save();

        $this->logVersionActivity('version_delete', 'Hapus versi IMM', [
            'deleted_index' => $index,
            'deleted_path'  => $removed['file_path'] ?? null,
            'fallback_path' => $this->file ?? null,
        ]);

        return true;
    }

    public function updateVersionDescription(int $index, string $description): bool
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $versions = $this->versionsList();
        if ($index < 0 || $index >= $versions->count()) return false;

        $row = $versions->get($index);
        $row['description'] = trim($description);
        $versions->put($index, $row);

        $this->file_versions = $versions->values()->all();
        $this->save();

        $this->logVersionActivity('version_desc_update', 'Ubah deskripsi revisi IMM', [
            'index'       => $index,
            'description' => trim($description),
        ]);

        return true;
    }

    /** Cari index versi by revision (Rev01, Rev02, ...). */
    public function findVersionIndexByRevision(string $revision): ?int
    {
        $idx = $this->versionsList()
            ->search(fn ($v) => strcasecmp((string) ($v['revision'] ?? ''), $revision) === 0);
        return $idx === false ? null : (int) $idx;
    }

    public function addVersionFromPath(
        string $path,
        ?string $originalName = null,
        ?string $description = null,
        ?string $revision = null
    ): array {
        if (!$this->exists) $this->save();

        $disk     = static::storageDisk();
        $fileName = $originalName ?: basename($path);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));
        $size     = null; try { $size = Storage::disk($disk)->size($path); } catch (\Throwable) {}

        $versions = $this->versionsList();

        // tutup versi lama bila masih open
        if ($versions->isNotEmpty()) {
            $last = $versions->pop();
            if (empty($last['replaced_at'])) $last['replaced_at'] = now()->toISOString();
            $versions->push($last);
        }

        $revCode = $revision ?: $this->nextRevisionCode();

        $new = [
            'revision'    => $revCode,
            'filename'    => $fileName,
            'description' => $description,
            'file_path'   => $path,
            'file_ext'    => $ext,
            'file_size'   => $size,
            'uploaded_at' => now()->toISOString(),
            'replaced_at' => null,
        ];

        $versions->push($new);

        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            return $row;
        });

        $this->file          = $path;
        $this->revision      = $versions->last()['revision'] ?? $revCode;
        if (blank($this->effective_at)) $this->effective_at = now()->toDateString();
        $this->file_versions = $versions->all();
        $this->save();

        $this->logVersionActivity('version_replace', 'Sinkron versi IMM dari path', [
            'revision'  => $this->revision,
            'file_path' => $path,
        ]);

        return $new;
    }

}
