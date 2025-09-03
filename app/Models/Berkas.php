<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Berkas extends Model
{
    use HasFactory;

    // (opsional tapi aman) tegaskan nama tabel
    protected $table = 'berkas';

    // kamu sudah pakai guarded = [] → semua kolom mass-assignable
    protected $guarded = [];

    /**
     * Default value lokal di model (backup kalau ada data lama tanpa nilai).
     * DB-mu sudah default true lewat migration, ini hanya berjaga-jaga.
     */
    protected $attributes = [
        'is_public' => true,
    ];

    /**
     * Casting kolom.
     */
    protected $casts = [
        'keywords'  => 'array',
        'is_public' => 'boolean', // <-- penting untuk policy & UI
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
