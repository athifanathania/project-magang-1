<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ImmLampiran extends Model
{
    use SoftDeletes;
    protected ?string $oldFilePath = null;
    protected ?string $oldUploadedAt = null;

    protected $guarded = [];
    protected $casts = [
        'keywords' => 'array',
        'file_versions' => 'array',
        'file_src_versions'    => 'array',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    protected static function booted(): void
    {
        // updating: simpan path & waktu 'terbit' versi lama
        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $m->oldFilePath   = $m->getOriginal('file');
                $m->oldUploadedAt = optional(
                    $m->getOriginal('updated_at') ?? $m->getOriginal('created_at')
                )->toDateTimeString();
            }
        });

        // saved: pindahkan file lama ke folder _versions + catat uploaded_at (waktu terbit lama)
        static::saved(function (self $m) {
            if ($m->wasChanged('file') && $m->oldFilePath) {
                $m->appendFileVersion($m->oldFilePath, auth()->id(), $m->oldUploadedAt);
                $m->oldFilePath   = null;
                $m->oldUploadedAt = null;
                $m->saveQuietly();
            }
        });

        static::deleting(function (self $m) {
            $m->children()->get()->each->delete();
        });
    }

    // terima uploadedAt lama
    public function appendFileVersion(?string $oldPath, ?int $userId = null, ?string $uploadedAt = null): void
    {
        if (!$oldPath) return;

        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        // 1) Pindahkan file fisik ke folder _versions/{id}/...
        $newPath = 'imm_lampiran/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        // 2) Normalisasi file_versions -> pisah versi numerik & meta
        $raw = $this->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        $versions = [];
        $meta     = [];
        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string)$k);
            if ($isNumeric) {
                if (is_array($v) && (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))) {
                    $versions[] = $v; // rebase 0..n-1
                }
            } else {
                $meta[$k] = $v;
            }
        }

        // 3) Ambil deskripsi versi AKTIF dari meta, lalu kosongkan meta tsb
        $currentDesc = trim((string)($meta['__current_desc'] ?? ''));
        unset($meta['__current_desc']); // versi baru harus mulai tanpa deskripsi

        // 4) Tambah entry versi (untuk file lama yang baru dipindah)
        $versions[] = [
            'path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => $uploadedAt ?: optional($this->updated_at ?? $this->created_at)->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
            'description' => $currentDesc !== '' ? $currentDesc : null, // ⬅️ pindahkan deskripsi ke VERSI lama
        ];

        // 5) Satukan kembali: versi numerik + meta non-numerik
        $out = $versions;
        foreach ($meta as $k => $v) { $out[$k] = $v; }

        $this->file_versions = $out;
    }


    public function deleteVersionAtIndex(int $index): bool
    {
        // --- Normalisasi raw
        $raw = $this->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        // --- Pisah versi numerik valid & meta non-numerik (termasuk __current_desc)
        $versions = [];
        $meta     = [];
        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string)$k);
            if ($isNumeric) {
                if (is_array($v) && (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))) {
                    $versions[] = $v; // rebase 0..n-1
                }
            } else {
                $meta[$k] = $v;
            }
        }

        // --- Validasi index
        if ($index < 0 || $index >= count($versions)) return false;

        // --- Hapus file fisik (jika ada) lalu keluarkan dari array
        $disk = \Storage::disk('private');
        $v    = $versions[$index] ?? null;
        if (is_array($v) && !empty($v['path']) && $disk->exists($v['path'])) {
            $disk->delete($v['path']);
        }
        array_splice($versions, $index, 1); // reindex otomatis 0..n-2

        // --- Satukan kembali: versi numerik + meta TETAP ADA
        $out = array_values($versions);
        foreach ($meta as $k => $val) { $out[$k] = $val; }

        $this->file_versions = $out;
        $this->saveQuietly();

        return true;
    }

}
