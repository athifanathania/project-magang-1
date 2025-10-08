<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

trait HasBerkasVersions
{
    protected static function storageDisk(): string
    {
        return 'private';
    }

    /** Helper logging yang aman utk CLI (request() bisa null). */
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
            // jangan ganggu alur simpan file/DB
        }
    }

    /** Versi sebagai collection (selalu array). */
    public function versionsList(): \Illuminate\Support\Collection
    {
        $raw = $this->dokumen_versions ?? [];
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            $raw = (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : [];
        }

        // HANYA row versi “asli” (punya file_path), dedupe by file_path, urut tua→baru
        return collect($raw)
            ->map(fn($r) => (array) $r)
            ->filter(fn($r) => !empty($r['file_path']))
            ->reverse()->unique(fn($r) => (string) $r['file_path'])
            ->reverse()
            ->values();
    }

    public function latestVersion(): ?array
    {
        return $this->versionsList()->last() ?: null;
    }

    public function nextRevisionCode(): string
    {
        $last = $this->latestVersion();
        if (!$last || !preg_match('/^rev(\d{1,})$/i', (string)($last['revision'] ?? ''), $m)) {
            return 'REV01';
        }
        $n = (int)$m[1] + 1;
        return 'REV' . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
    }

    public function addVersionFromUpload(UploadedFile $file, ?string $description = null, ?string $revision = null): array
    {
        if (!$this->exists) {
            $this->save(); // butuh ID untuk folder
        }

        $disk = static::storageDisk();
        $dir  = 'berkas/'.$this->getKey();

        $originalName = method_exists($file, 'getClientOriginalName')
            ? $file->getClientOriginalName()
            : (string) ($file->getClientOriginalName() ?? 'file');

        $ext  = method_exists($file, 'getClientOriginalExtension')
            ? strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION))
            : strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $size = method_exists($file, 'getSize') ? (int) $file->getSize() : null;

        $storedName = now()->format('Ymd_His').'-'.Str::random(6).'-'.$originalName;
        $path = Storage::disk($disk)->putFileAs($dir, $file, $storedName);

        $versions = $this->versionsList();

        // tutup versi lama
        if ($versions->isNotEmpty()) {
            $last = $versions->pop();
            if (empty($last['replaced_at'])) {
                $last['replaced_at'] = now()->toISOString();
            }
            $versions->push($last);
        }

        $revCode = $revision ?: $this->nextRevisionCode();

        $new = [
            'revision'    => $revCode,
            'filename'    => $originalName,
            'description' => $description,
            'file_path'   => $path,
            'file_ext'    => $ext,
            'file_size'   => $size,
            'uploaded_at' => now()->toISOString(),
            'replaced_at' => null,
        ];

        $versions->push($new);
        $versions = $versions->reverse()
            ->unique(fn ($r) => (string)($r['file_path'] ?? ''))
            ->reverse()
            ->values();

        // renumber & sinkron
        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            return $row;
        });

        $this->dokumen_versions = $versions->all();
        $this->dokumen          = $path;
        $this->save();

        $this->logVersionActivity('version_add', 'Tambah versi dokumen dari upload', [
            'revision'    => $new['revision'] ?? null,
            'filename'    => $new['filename'] ?? null,
            'file_path'   => $new['file_path'] ?? null,
            'file_ext'    => $new['file_ext'] ?? null,
            'file_size'   => $new['file_size'] ?? null,
            'description' => $new['description'] ?? null,
        ]);
        
        return $new;
    }

    /**
     * === SATU-SATUNYA pintu untuk nambah versi (mirror IMM) ===
     * Pakai ini saat ganti file aktif (path sudah tersimpan di disk).
     */
    public function addVersionFromPath(string $path, ?string $originalName = null, ?string $description = null, ?string $revision = null): array
    {
        if (!$this->exists) {
            $this->save();
        }

        $disk     = static::storageDisk();
        $fileName = $originalName ?: basename($path);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));
        $size     = null; try { $size = Storage::disk($disk)->size($path); } catch (\Throwable) {}

        $versions = $this->versionsList();

        // Kalau last sudah menunjuk ke path yang sama → jangan duplikasi, cukup pastikan "open".
        if ($versions->isNotEmpty()) {
            $lastIdx  = $versions->count() - 1;
            $last     = (array)$versions[$lastIdx];
            $lastPath = (string)($last['file_path'] ?? '');
            if ($lastPath === (string)$path) {
                $last['replaced_at'] = null;
                $last['filename']    = $fileName ?: ($last['filename'] ?? null);
                if ($description !== null) $last['description'] = trim($description);
                $last['file_ext']    = $ext;
                $last['file_size']   = $size;
                $versions->put($lastIdx, $last);

                // Renumber & sinkron
                $versions = $versions->values()->map(function ($row, $i) {
                    $row['revision'] = 'REV' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
                    return $row;
                });
                $this->dokumen_versions = $versions->all();
                $this->dokumen          = $path;
                $this->save();
                $this->logVersionActivity('version_reopen', 'Buka ulang versi aktif (path sama)', [
                    'revision'  => $last['revision'] ?? null,
                    'file_path' => $last['file_path'] ?? null,
                ]);

                return $last;
            }

            // Tutup versi lama bila belum tertutup
            if (empty($last['replaced_at'])) {
                $last['replaced_at'] = now()->toISOString();
                $versions->put($lastIdx, $last);
            }
        }

        $revCode = $revision ?: $this->nextRevisionCode();

        $newVersion = [
            'revision'    => $revCode,
            'filename'    => $fileName,
            'description' => $description,
            'file_path'   => $path,
            'file_ext'    => $ext,
            'file_size'   => $size,
            'uploaded_at' => now()->toISOString(),
            'replaced_at' => null,
        ];

        $versions->push($newVersion);
        $versions = $versions->reverse()
            ->unique(fn ($r) => (string)($r['file_path'] ?? ''))
            ->reverse()
            ->values();

        // Renumber & sinkron (aktif = last)
        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            return $row;
        });

        $this->dokumen_versions = $versions->all();
        $this->dokumen          = $path;
        $this->save();

        $this->logVersionActivity('version_replace', 'Ganti versi dokumen (add from path)', [
            'revision'     => $newVersion['revision'] ?? null,
            'filename'     => $newVersion['filename'] ?? null,
            'file_path'    => $newVersion['file_path'] ?? null,
            'file_ext'     => $newVersion['file_ext'] ?? null,
            'file_size'    => $newVersion['file_size'] ?? null,
            'description'  => $newVersion['description'] ?? null,
        ]);

        return $newVersion;
    }

    /** Alias lama, biar pemanggilan existing tetap jalan. */
    protected function appendNewDokumenVersionFromPath(string $path, ?string $originalName = null, ?string $description = null): void
    {
        $this->addVersionFromPath($path, $originalName, $description);
    }

    /** Sama seperti IMM: boleh hapus apa pun; kalau aktif yang kehapus → fallback ke sebelumnya. */
    public function deleteVersionAtIndex(int $index): bool
    {
        $versions = $this->versionsList();
        if ($index < 0 || $index >= $versions->count()) return false;

        $removed = $versions->pull($index);
        if (!empty($removed['file_path'])) {
            try { Storage::disk(static::storageDisk())->delete($removed['file_path']); } catch (\Throwable) {}
        }

        if ($versions->isEmpty()) {
            $this->dokumen = null;
            $this->dokumen_versions = [];
            $this->save();
            return true;
        }

        // Jika yang dihapus adalah last (aktif sebelum di-pull), index == count() sesudah pull
        if ($index === $versions->count()) {
            $prev = $versions->last();
            $this->dokumen = $prev['file_path'] ?? null;
        }

        // Renumber & pastikan last "open"
        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            return $row;
        });
        $lastIdx = $versions->count() - 1;
        $last    = $versions->get($lastIdx);
        $last['replaced_at'] = null;
        $versions->put($lastIdx, $last);

        $this->dokumen_versions = $versions->all();
        $this->save();

        $this->logVersionActivity('version_delete', 'Hapus versi dokumen', [
            'deleted_index' => $index,
            'deleted_path'  => $removed['file_path'] ?? null,
            'fallback_path' => $this->dokumen ?? null,
        ]);

        return true;
    }

    public function updateVersionDescription(int $index, string $description): bool
    {
        $versions = $this->versionsList();
        if ($index < 0 || $index >= $versions->count()) return false;

        $row = $versions->get($index);
        $row['description'] = trim($description);
        $versions->put($index, $row);

        $this->dokumen_versions = $versions->values()->all();
        $this->save();

        $this->logVersionActivity('version_desc_update', 'Ubah deskripsi revisi dokumen', [
            'index'       => $index,
            'description' => trim($description),
        ]);

        return true;
    }

    public function findVersionIndexByRevision(string $revision): ?int
    {
        $idx = $this->versionsList()
            ->search(fn ($v) => strcasecmp((string)($v['revision'] ?? ''), $revision) === 0);
        return $idx === false ? null : (int)$idx;
    }
}
