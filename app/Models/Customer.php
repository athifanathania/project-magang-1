<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'document_templates' => 'array', 
    ];

    public function berkas(): HasMany
    {
        return $this->hasMany(Berkas::class); 
    }
}