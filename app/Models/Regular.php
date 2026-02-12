<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasBerkasVersions;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Contracts\Activity;
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
        $nama = $this->nama ?? $this->getOriginal('nama') ?? $this->getAttribute('nama');
        
        $label = $nama ? $nama : "#{$this->id}";
        
        return "Regular: {$label}";
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

    public function tapActivity(Activity $activity, string $eventName)
    {
        $props = collect($activity->properties ?? []);

        $props = $props->merge([
            'ip'            => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'url'           => request()->header('Referer') ?? request()->fullUrl(), 
        ]);

        $labelSnapshot = $this->getActivityDisplayName(); 
        
        $props->put('snapshot_name', $labelSnapshot); 

        $activity->properties = $props;
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class);
    }
}