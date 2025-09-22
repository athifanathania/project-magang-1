<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasBerkasVersions
{
    // --- Utilities ---
    protected function berkasVersions(): \Illuminate\Support\Collection
    {
        $raw = $this->dokumen_versions ?? [];
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $j : [];
        }
        return collect($raw)->values();
    }

    // Alias biar konsisten dengan trait IMM
    protected function versionsList(): \Illuminate\Support\Collection
    {
        return $this->berkasVersions();
    }

    public function latestVersion(): ?array
    {
        return $this->berkasVersions()->last() ?: null; // <- pakai berkasVersions()
    }

    /** Hitung kode revisi berikutnya: Rev01, Rev02, ... */
    public function nextRevisionCode(): string
    {
        $last = $this->latestVersion();
        if (!$last || !preg_match('/^rev(\d{1,})$/i', (string)($last['revision'] ?? ''), $m)) {
            return 'REV01';
        }
        $n = (int)$m[1] + 1; // <- ambil capture group yang benar
        return 'REV' . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
    }

    protected function appendNewDokumenVersionFromPath(string $path, ?string $originalName = null, ?string $description = null): void
    {
        $versions = $this->berkasVersions();

        // Tutup versi lama (beri replaced_at)
        if ($versions->isNotEmpty()) {
            $last = $versions->pop();
            if (empty($last['replaced_at'])) {
                $last['replaced_at'] = now()->toISOString();
            }
            $versions->push($last);
        }

        $fileName = $originalName ?: basename($path);
        $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));
        $size     = null;
        try { $size = Storage::disk('private')->size($path); } catch (\Throwable) {}

        $versions->push([
            'revision'    => $this->nextRevisionCode(),
            'filename'    => $fileName,
            'description' => $description,
            'file_path'   => $path,
            'file_ext'    => $ext,
            'file_size'   => $size,
            'uploaded_at' => now()->toISOString(),
            'replaced_at' => null,
        ]);

        // Renumber biar rapi (REV01..)
        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            return $row;
        });

        $this->dokumen_versions = $versions->all();
        $this->dokumen          = $path; // sinkron
    }

    public function deleteVersionAtIndex(int $index): bool
    {
        $versions = $this->berkasVersions();
        if ($index < 0 || $index >= $versions->count()) return false;

        $removed = $versions->pull($index);
        if (!empty($removed['file_path'])) {
            try { Storage::disk('private')->delete($removed['file_path']); } catch (\Throwable) {}
        }

        if ($versions->isEmpty()) {
            $this->dokumen = null;
            $this->dokumen_versions = [];
            $this->save();
            return true;
        }

        // Jika yang dihapus adalah versi aktif (terakhir secara kronologis),
        // fallback ke versi sebelumnya sebagai file aktif
        if ($index === $versions->count()) {
            $prev = $versions->last();
            $this->dokumen = $prev['file_path'] ?? null;
        }

        // Pastikan versi TERAKHIR (aktif) tidak punya replaced_at
        $lastIdx = $versions->count() - 1;
        $last    = $versions->get($lastIdx);
        if (!empty($last['replaced_at'])) {
            $last['replaced_at'] = null;
            $versions->put($lastIdx, $last);
        }

        // Renumber revisi supaya berurutan
        $versions = $versions->values()->map(function ($row, $i) {
            $row['revision'] = 'REV' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            return $row;
        });

        $this->dokumen_versions = $versions->all();
        $this->save();

        return true;
    }

    public function updateVersionDescription(int $index, string $description): bool
    {
        $versions = $this->berkasVersions();
        if ($index < 0 || $index >= $versions->count()) return false;

        $row = $versions->get($index);
        $row['description'] = trim($description);
        $versions->put($index, $row);

        $this->dokumen_versions = $versions->values()->all();
        $this->save();

        return true;
    }

    public static function bootHasBerkasVersions(): void
    {
        static::saving(function ($model) {
            if (! filled($model->dokumen)) return;

            $current  = (string) $model->dokumen;
            $versions = $model->berkasVersions()->values();

            // 1) NORMALISASI: path/ext/size -> file_path/file_ext/file_size
            $versions = $versions->map(function ($row) {
                $row = (array) $row;
                if (empty($row['file_path']) && !empty($row['path'])) {
                    $row['file_path'] = $row['path'];
                }
                if (empty($row['file_ext']) && !empty($row['ext'])) {
                    $row['file_ext'] = $row['ext'];
                }
                if (!array_key_exists('file_size', $row) && array_key_exists('size', $row)) {
                    $row['file_size'] = $row['size'];
                }
                unset($row['path'], $row['ext'], $row['size']);
                return $row;
            })->filter(fn ($r) => filled($r['file_path']))->values();

            // 2) DEDUPE file_path (pertahankan urutan tertua->terbaru)
            $seen = [];
            $versions = $versions->filter(function ($row) use (&$seen) {
                $k = (string) ($row['file_path'] ?? '');
                if (isset($seen[$k])) return false;
                $seen[$k] = true;
                return true;
            })->values();

            // 3) PROMOTE kalau current path sudah ada
            $idxCur = $versions->search(fn ($v) => (string) ($v['file_path'] ?? '') === $current);
            if ($idxCur !== false) {
                $lastIdx = $versions->count() - 1;
                if ($idxCur !== $lastIdx && $lastIdx >= 0) {
                    // tutup versi sebelumnya yang paling akhir (kalau masih open)
                    $last = $versions->get($lastIdx);
                    if (empty($last['replaced_at'])) {
                        $last['replaced_at'] = now()->toISOString();
                        $versions->put($lastIdx, $last);
                    }
                    // pindahkan current ke ekor; pastikan aktif
                    $cur = $versions->pull($idxCur);
                    $cur['replaced_at'] = null;
                    if (empty($cur['uploaded_at'])) {
                        $cur['uploaded_at'] = now()->toISOString();
                    }
                    $versions->push($cur);
                } else {
                    // sudah paling akhir → pastikan aktif
                    $last = $versions->get($lastIdx);
                    if (!empty($last['replaced_at'])) {
                        $last['replaced_at'] = null;
                        $versions->put($lastIdx, $last);
                    }
                }
            } else {
                // 4) PATH BARU → append versi baru
                $model->appendNewDokumenVersionFromPath($current, basename($current));
                $versions = $model->berkasVersions()->values();
            }

            // 5) RENUMBER REV01..REVnn
            $versions = $versions->values()->map(function ($row, $i) {
                $row['revision'] = 'REV' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
                return $row;
            });

            $model->dokumen_versions = $versions->all();
            $model->dokumen          = $current; // sinkron
        });
    }
}
