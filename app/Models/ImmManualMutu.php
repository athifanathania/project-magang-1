<?php

namespace App\Models;

use App\Models\Concerns\HasImmVersions;
use Illuminate\Database\Eloquent\Model;
use App\Models\ImmLampiran;

class ImmManualMutu extends Model
{
    use HasImmVersions;

    protected $table = 'imm_manual_mutu';

    protected $guarded = [];

    protected $casts = [
        'keywords'         => 'array',
        'file_versions'=> 'array',
        'effective_at'     => 'date',
        'expires_at'       => 'date',
        'file_src_versions'    => 'array',
    ];

    protected static function storageBaseDir(): string
    {
        return 'imm/manual_mutu';
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
