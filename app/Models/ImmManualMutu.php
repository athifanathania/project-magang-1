<?php

namespace App\Models;

use App\Models\Concerns\HasImmVersions;
use Illuminate\Database\Eloquent\Model;

class ImmManualMutu extends Model
{
    use HasImmVersions;

    protected $table = 'imm_manual_mutu';

    protected $guarded = [];

    protected $casts = [
        'keywords'         => 'array',
        'versions'         => 'array',
        'effective_at'     => 'date',
        'expires_at'       => 'date',
    ];

    protected static function storageBaseDir(): string
    {
        return 'imm/manual_mutu';
    }
}
