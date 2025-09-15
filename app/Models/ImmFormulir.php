<?php

namespace App\Models;

use App\Models\Concerns\HasImmVersions;
use Illuminate\Database\Eloquent\Model;

class ImmFormulir extends Model
{
    use HasImmVersions;

    protected $table = 'imm_formulir';

    protected $guarded = [];

    protected $casts = [
        'keywords'     => 'array',
        'versions'     => 'array',
        'effective_at' => 'date',
        'expires_at'   => 'date',
    ];

    protected static function storageBaseDir(): string
    {
        return 'imm/formulir';
    }
}
