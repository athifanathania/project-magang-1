<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HumanReadableActivity;
use Spatie\Activitylog\Contracts\Activity; 

class ImmAuditInternal extends Model
{
    use SoftDeletes, LogsActivity, HumanReadableActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logOnly(['departemen','semester','tahun'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Dokumen Audit {$e}");
    }

    public function getActivityDisplayName(): string
    {
        return "Audit Internal: {$this->departemen} {$this->semester} {$this->tahun}";
    }

    public function tapActivity(\Spatie\Activitylog\Contracts\Activity $activity, string $eventName)
    {
        $activity->properties = $activity->properties->merge([
            'ip'            => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'url'           => request()->header('Referer') ?? request()->fullUrl(),
            'snapshot_name' => $this->getActivityDisplayName(),
        ]);
    }

    protected $table = 'imm_audit_internals';
    protected $fillable = ['departemen','semester','tahun'];

    public function tasks(): HasMany
    {
        return $this->hasMany(ImmLampiran::class, 'documentable_id')
            ->where('documentable_type', self::class);
    }
}