<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class ImmLampiran extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'keywords' => 'array',
        'file_versions' => 'array',
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

    protected ?string $oldFilePath = null;

    protected static function booted(): void
    {
        // Simpan path lama jika file berubah
        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $m->oldFilePath = $m->getOriginal('file');
            }
        });

        static::saved(function (self $m) {
            if ($m->wasChanged('file') && $m->oldFilePath) {
                $m->appendFileVersion($m->oldFilePath, auth()->id());
                $m->oldFilePath = null;
                $m->saveQuietly();
            }
        });

        static::deleting(function (self $m) {
            $m->children()->get()->each->delete();
        });
    }

    public function appendFileVersion(?string $oldPath, ?int $userId = null): void
    {
        if (!$oldPath) return;

        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        $newPath = 'imm_lampiran/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        $versions = $this->file_versions ?? [];
        $versions[] = [
            'path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => now()->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
        ];
        $this->file_versions = $versions;
    }

    public function deleteVersionAtIndex(int $index): bool
    {
        $versions = $this->file_versions ?? [];
        if (! array_key_exists($index, $versions)) return false;

        $disk = \Storage::disk('private');
        $v = $versions[$index];
        if (!empty($v['path']) && $disk->exists($v['path'])) {
            $disk->delete($v['path']);
        }

        array_splice($versions, $index, 1);
        $this->file_versions = array_values($versions);
        $this->saveQuietly();

        return true;
    }

}
