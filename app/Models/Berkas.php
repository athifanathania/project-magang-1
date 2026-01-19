<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasBerkasVersions;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HumanReadableActivity;

class Berkas extends Model
{
    use HasFactory, HasBerkasVersions, LogsActivity, HumanReadableActivity;

    public function getActivityDisplayName(): ?string
    {
        $label = $this->nama ?? 'Berkas Tanpa Nama';
        
        if (!empty($this->kode_berkas)) {
            return "{$this->kode_berkas} - {$label}";
        }

        return $label;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logAll()
            ->logExcept(['dokumen_versions','dokumen_src_versions','updated_at','created_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Dokumen {$e}");
    }


    protected $table = 'berkas';
    protected $guarded = [];
    protected $attributes = ['is_public' => true];

    protected $casts = [
        'keywords'               => 'array',
        'is_public'              => 'boolean',
        'dokumen_versions'       => 'array',
        'dokumen_src_versions'   => 'array',
        'dokumen_uploaded_at'    => 'datetime',
        'dokumen_src_uploaded_at'=> 'datetime',
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
     |  SCOPES (opsional membantu)
     |===========================*/
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
}
