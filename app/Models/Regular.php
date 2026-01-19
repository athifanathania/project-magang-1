<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasBerkasVersions;        // reuse persis
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HumanReadableActivity;

class Regular extends Model
{
    use HasFactory, HasBerkasVersions, LogsActivity, HumanReadableActivity;

    protected $table = 'regulars';
    protected $guarded = [];
    protected $attributes = ['is_public' => true];

    protected $casts = [
        'keywords'                 => 'array',
        'is_public'                => 'boolean',
        'dokumen_versions'         => 'array',
        'dokumen_src_versions'     => 'array',
        'dokumen_uploaded_at'      => 'datetime',
        'dokumen_src_uploaded_at'  => 'datetime',
    ];

    /** relasi lampiran (opsional) */
    public function lampirans()
    {
        return $this->hasMany(\App\Models\Lampiran::class, 'regular_id');
    }

    public function rootLampirans()
    {
        return $this->lampirans()->whereNull('parent_id')->orderBy('id');
    }

    public function rootLampiransRecursive()
    {
        return $this->rootLampirans()->with('childrenRecursive');
    }

    public function getActivityDisplayName(): ?string
    {
        return $this->nama ?? "Regular #{$this->id}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logAll()
            ->logExcept(['dokumen_versions','dokumen_src_versions','updated_at','created_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Regular {$e}");
    }

}
