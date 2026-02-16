<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Contracts\Activity;
// Pastikan trait ini ada karena dipakai di Berkas
use App\Models\Concerns\HumanReadableActivity; 

class Customer extends Model
{
    use LogsActivity, HumanReadableActivity;

    protected $guarded = [];

    protected $casts = [
        'document_templates' => 'array', 
    ];

    /* ===========================
     |  RELATIONS
     |===========================*/
    public function berkas(): HasMany
    {
        return $this->hasMany(Berkas::class); 
    }

    /* ===========================
     |  ACTIVITY LOG CONFIGURATION
     |===========================*/

    public function getActivityDisplayName(): ?string
    {
        return "Customer: " . ($this->name ?? 'Tanpa Nama');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web') 
            ->logAll()  
            ->logExcept(['updated_at', 'created_at']) 
            ->logOnlyDirty() 
            ->dontSubmitEmptyLogs()  
            ->setDescriptionForEvent(fn (string $eventName) => "Customer {$eventName}");
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