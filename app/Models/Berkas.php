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
        'keywords'         => 'array',
        'is_public'        => 'boolean',
        'dokumen_versions' => 'array', 
        'dokumen_src_versions'   => 'array',
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

    protected ?string $oldDokumenPath = null;

    protected static function booted(): void
    {
        static::updating(function (self $m) {
            $m->oldDokumenPath = $m->getOriginal('dokumen');
        });

        static::saved(function (self $m) {
            if (! $m->wasChanged('dokumen')) {
                return;
            }

            $old = $m->oldDokumenPath;
            if (! $old) {
                return;
            }

            $m->appendDokumenVersion($old, auth()->id());

            $m->oldDokumenPath = null;

            $m->saveQuietly();
        });
    }

    
    public function appendDokumenVersion(?string $oldPath, ?int $userId = null): void
    {
        if (!$oldPath) return;

        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        $newPath = 'berkas/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        $versions = $this->dokumen_versions ?? [];
        $versions[] = [
            'path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => now()->toDateTimeString(), // tak available? pakai now
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
        ];
        $this->dokumen_versions = $versions;
    }

}
