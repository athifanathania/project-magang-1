<?php

namespace App\Models;

use App\Models\Concerns\HasImmVersions;
use Illuminate\Database\Eloquent\Model;
use App\Models\ImmLampiran;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HumanReadableActivity;

class ImmManualMutu extends Model
{
    use HasImmVersions, LogsActivity, HumanReadableActivity;

    public function getActivityDisplayName(): ?string
    {
        // Pastikan ini sesuai kolom di database, misalnya 'nama_dokumen'
        return $this->nama_dokumen ?? "Manual Mutu #{$this->id}";
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
            ->setDescriptionForEvent(fn (string $e) => "Manual Mutu {$e}");
    }


    protected $table = 'imm_manual_mutu';

    protected $guarded = [];

    protected $casts = [
        'keywords'         => 'array',
        'file_versions'=> 'array',
        'effective_at'     => 'date',
        // 'expires_at'       => 'date',
        'file_src_versions'    => 'array',
    ];

    protected static function storageBaseDir(): string
    {
        return 'imm/manual_mutu';
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
