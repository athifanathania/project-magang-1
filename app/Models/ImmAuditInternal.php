<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImmAuditInternal extends Model
{
    use SoftDeletes;

    protected $table = 'imm_audit_internals';

    protected $fillable = [
        'departemen',
        'semester',
        'tahun',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(\App\Models\ImmLampiran::class, 'documentable_id')
            ->where('documentable_type', self::class);
    }
}
