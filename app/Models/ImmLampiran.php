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
        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $m->oldFilePath   = $m->getOriginal('file');
                // ambil waktu "terbit" versi lama = kapan file aktif itu terakhir diset
                $m->oldUploadedAt = optional(
                    $m->getOriginal('updated_at') ?? $m->getOriginal('created_at')
                )->toDateTimeString();
            }
        });

        static::saved(function (self $m) {
            if ($m->wasChanged('file') && $m->oldFilePath) {
                $m->appendFileVersion($m->oldFilePath, auth()->id(), $m->oldUploadedAt);
                $m->oldFilePath = null;
                $m->oldUploadedAt = null;
                $m->saveQuietly();
            }
        });

        static::deleting(function (self $m) {
            $m->children()->get()->each->delete();
        });
    }

    public function appendFileVersion(?string $oldPath, ?int $userId = null, ?string $uploadedAt = null): void
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
            // penting: uploaded_at = kapan versi lama “terbit”
            'uploaded_at' => $uploadedAt ?: now()->toDateTimeString(),
            // dan baru sekarang “diubah/diganti”
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
