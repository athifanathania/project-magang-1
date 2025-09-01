<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berkas extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function lampirans()
    {
        return $this->hasMany(Lampiran::class);
    }

    public function rootLampirans()
    {
        return $this->hasMany(\App\Models\Lampiran::class)
            ->whereNull('parent_id')
            ->orderBy('id'); // atau urutan lain yang kamu mau
    }

    public function rootLampiransRecursive()
    {
        return $this->rootLampirans()->with('childrenRecursive');
    }

    protected $casts = [
        'keywords' => 'array',  
    ];
}
