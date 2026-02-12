<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasBerkasVersions;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Contracts\Activity;
use App\Models\Concerns\HumanReadableActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Berkas extends Model
{
    use HasFactory, HasBerkasVersions, LogsActivity, HumanReadableActivity;

    protected $table = 'berkas';
    protected $guarded = [];
    protected $attributes = ['is_public' => true];

    protected $casts = [
        'keywords'                => 'array',
        'is_public'               => 'boolean',
        'dokumen_versions'        => 'array',
        'dokumen_src_versions'    => 'array',
        'dokumen_uploaded_at'     => 'datetime',
        'dokumen_src_uploaded_at' => 'datetime',
    ];

    /* ===========================
     |  RELATIONS
     |===========================*/
    public function lampirans()
    {
        return $this->hasMany(Lampiran::class);
    }

    public function rootLampirans()
    {
        return $this->hasMany(\App\Models\Lampiran::class)
            ->whereNull('parent_id')
            ->orderBy('id'); // sesuaikan jika perlu
    }

    public function rootLampiransRecursive()
    {
        return $this->rootLampirans()->with('childrenRecursive');
    }

    /* ===========================
     |  SCOPES
     |===========================*/
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    // --- ACTIVITY LOG CONFIGURATION ---

    public function getActivityDisplayName(): ?string
    {
        $label = $this->nama ?? 'Event Tanpa Nama'; 
        
        $identifier = $label;
        if (!empty($this->kode_berkas)) {
            $identifier = "{$this->kode_berkas} - {$label}";
        }

        return "Event: {$identifier}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logAll()
            ->logExcept(['dokumen_versions','dokumen_src_versions','updated_at','created_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Event {$e}");
    }

    public function tapActivity(Activity $activity, string $eventName)
    {
        $currentUrl = request()->fullUrl();
        if (str_contains($currentUrl, '/livewire/') || str_contains($currentUrl, 'server-memo')) {
            $referer = request()->header('Referer');
            if ($referer) {
                $currentUrl = $referer;
            }
        }

        $activity->properties = $activity->properties->merge([
            'ip'            => request()->ip(),
            'user_agent'    => substr((string) request()->userAgent(), 0, 500),
            'url'           => $currentUrl,
            'snapshot_name' => $this->getActivityDisplayName(), 
        ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}