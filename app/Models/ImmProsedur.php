<?php

namespace App\Models;

use App\Models\Concerns\HasImmVersions;
use Illuminate\Database\Eloquent\Model;
use App\Models\ImmLampiran;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HumanReadableActivity;

class ImmProsedur extends Model
{
    use HasImmVersions, LogsActivity, HumanReadableActivity;

    public function getActivityDisplayName(): ?string
    {
        return $this->nama_dokumen ?? "Prosedur #{$this->id}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logOnly([
                'nama_dokumen','file','keywords','effective_at','expires_at','file_src',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Prosedur {$e}");
    }

    protected $table = 'imm_prosedur';

    protected $guarded = [];

    protected $casts = [
        'keywords'     => 'array',
        'file_versions'=> 'array',
        'effective_at' => 'date',
        // 'expires_at'   => 'date',
        'file_src_versions'    => 'array',
    ];

    protected static function storageBaseDir(): string
    {
        return 'imm/prosedur';
    }

    public function lampirans()
    {
        return $this->morphMany(\App\Models\ImmLampiran::class, 'documentable');
    }

    public function rootLampirans()
    {
        return $this->lampirans()->whereNull('parent_id');
    }

    public function immLampirans()     { return $this->lampirans(); }
    public function rootImmLampirans() { return $this->rootLampirans(); }
}
