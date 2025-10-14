<?php

namespace App\Models;

use App\Models\Concerns\HasImmVersions;
use Illuminate\Database\Eloquent\Model;
use App\Models\ImmLampiran;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Models\Concerns\HumanReadableActivity;

class ImmInstruksiStandar extends Model
{
    use HasImmVersions, LogsActivity, HumanReadableActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logOnly([
                'nama_dokumen','file','keywords','effective_at','expires_at','file_src',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Instruksi Standar {$e}");
    }



    protected $table = 'imm_instruksi_standar';

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
        return 'imm/instruksi_standar';
    }

    public function immLampirans()
    {
        return $this->morphMany(ImmLampiran::class, 'documentable');
    }

    public function rootImmLampirans()
    {
        return $this->immLampirans()->whereNull('parent_id');
    }
}
