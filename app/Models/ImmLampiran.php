<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Contracts\Activity; 
use App\Models\Concerns\HumanReadableActivity;
use Illuminate\Support\Str;

class ImmLampiran extends Model
{
    use SoftDeletes, LogsActivity, HumanReadableActivity;

    public function getParentLabel(): string
    {
        if (!$this->documentable_type) return 'Lampiran';
        $className = class_basename($this->documentable_type);
        $cleanName = Str::replaceFirst('Imm', '', $className);
        return Str::headline($cleanName);
    }

    public function getActivityDisplayName(): ?string
    {
        $fileName  = $this->file ? basename($this->file) : null;
        $basicName = $this->nama ?? $fileName ?? "Lampiran #{$this->id}";
        
        $parentLabel = $this->getParentLabel();
        
        if ($parentLabel === 'Lampiran' || empty($parentLabel)) {
            return $basicName;
        }

        return "{$parentLabel}: {$basicName}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logOnly([
                'nama','file','keywords','parent_id',
                'documentable_type','documentable_id','deadline_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $event) {
                $parent = $this->getParentLabel(); 
                return "Lampiran Imm {$parent} {$event}"; 
            });
    }

    public function tapActivity(Activity $activity, string $event): void
    {
        // 1. Logic Subject (Khusus Audit Internal)
        if ($this->getParentLabel() === 'Audit Internal') {
            $activity->subject_type = \App\Models\ImmAuditInternal::class;
            $activity->subject_id   = $this->documentable_id;
        }

        $props = collect($activity->properties ?? []);
        $props = $props->merge([
            'ip'            => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'url'           => request()->header('Referer') ?? request()->fullUrl(),
        ]);

        $label = $this->getActivityDisplayName();
        $activity->properties = $props->put('snapshot_name', $label);
    }

    protected ?string $oldFilePath = null;
    protected ?string $oldUploadedAt = null;

    protected $guarded = [];
    protected $casts = [
        'keywords' => 'array',
        'file_versions' => 'array',
        'file_src_versions'    => 'array',
        'deadline_at'       => 'date',
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
        return $this->hasMany(self::class, 'parent_id')
            ->orderByRaw('COALESCE(sort_order, id) ASC');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (is_null($m->sort_order)) {
                $max = self::where('parent_id', $m->parent_id)->max('sort_order');
                $m->sort_order = is_null($max) ? 1 : ($max + 1);
            }
        });

        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $m->oldFilePath   = $m->getOriginal('file');
                $m->oldUploadedAt = optional(
                    $m->getOriginal('updated_at') ?? $m->getOriginal('created_at')
                )->toDateTimeString();
            }
        });

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

    public function appendFileVersion(?string $oldPath, ?int $userId = null, ?string $uploadedAt = null): void
    {
        if (!$oldPath) return;

        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        $newPath = 'imm_lampiran/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

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
                    $versions[] = $v;
                }
            } else {
                $meta[$k] = $v;
            }
        }

        $currentDesc = trim((string)($meta['__current_desc'] ?? ''));
        unset($meta['__current_desc']);

        $versions[] = [
            'file_path' => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => $uploadedAt ?: optional($this->updated_at ?? $this->created_at)->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
            'description' => $currentDesc !== '' ? $currentDesc : null,
        ];

        $out = $versions;
        foreach ($meta as $k => $v) { $out[$k] = $v; }

        $this->file_versions = $out;
    }


    public function deleteVersionAtIndex(int $index): bool
    {
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
                    $versions[] = $v;
                }
            } else {
                $meta[$k] = $v;
            }
        }

        if ($index < 0 || $index >= count($versions)) return false;

        $disk = \Storage::disk('private');
        $v    = $versions[$index] ?? null;
        if (is_array($v) && !empty($v['file_path']) && $disk->exists($v['file_path'])) {
            $disk->delete($v['file_path']);
        }
        array_splice($versions, $index, 1);

        $out = array_values($versions);
        foreach ($meta as $k => $val) { $out[$k] = $val; }

        $this->file_versions = $out;
        $this->saveQuietly();

        return true;
    }
}