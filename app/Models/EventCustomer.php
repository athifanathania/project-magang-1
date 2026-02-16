<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
// Import Activity Log
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity;
use App\Models\Concerns\HumanReadableActivity;

class EventCustomer extends Model
{
    // Tambahkan LogsActivity dan HumanReadableActivity
    use HasFactory, LogsActivity, HumanReadableActivity;

    protected $table = 'event_customers';

    protected $fillable = [
        'thumbnail',
        'cust_name',
        'model',
        'kode_berkas',
        'nama',
        'detail',
        'keywords',
        'dokumen',
        'dokumen_src',
        'is_public',
    ];

    protected $casts = [
        'keywords' => 'array', 
        'is_public' => 'boolean',
    ];

    /* ===========================
     |  RELATIONS
     |===========================*/
    public function lampirans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Lampiran::class, 'berkas_id');
    }

    public function rootLampirans(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->lampirans()->whereNull('parent_id');
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    /* ===========================
     |  LOGIC VERSIONING
     |===========================*/
    public function addVersionFromUpload($file)
    {
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('event_customers', $filename, 'private');

        $this->update(['dokumen' => $path]);

        return ['file_path' => $path];
    }

    public function versionsList()
    {
        return collect([]);
    }

    /* ===========================
     |  ACTIVITY LOG CONFIGURATION
     |===========================*/

    public function getActivityDisplayName(): ?string
    {
        $cust = $this->cust_name ?? 'No Cust';
        $part = $this->nama ?? 'No Name';
        return "Event Customer: {$cust} - {$part}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logAll()
            ->logExcept(['updated_at', 'created_at']) 
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Event Customer {$eventName}");
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
}