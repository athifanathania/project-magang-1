<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasBerkasVersions;

class Berkas extends Model
{
    use HasFactory;
    use HasBerkasVersions;

    // (opsional tapi aman) tegaskan nama tabel
    protected $table = 'berkas';

    // kamu sudah pakai guarded = [] → semua kolom mass-assignable
    protected $guarded = [];

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
        'dokumen_uploaded_at'  => 'datetime',
        'dokumen_src_uploaded_at' => 'datetime',
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
    protected ?string $oldDokumenSrcPath = null;

    protected static function booted(): void
    {
        static::updating(function (self $m) {
            $m->oldDokumenPath = $m->getOriginal('dokumen');
            $m->oldDokumenSrcPath = $m->getOriginal('dokumen_src');
        });

        static::saved(function (self $m) {
            $changed = false;

            // === versi utk DOKUMEN (utama) ===
            if ($m->wasChanged('dokumen') && $m->oldDokumenPath) {
                $m->appendDokumenVersion($m->oldDokumenPath, auth()->id());
                $m->oldDokumenPath = null;
                $changed = true;
            }

            // === versi utk DOKUMEN_SRC (file asli) ===
            if ($m->wasChanged('dokumen_src') && $m->oldDokumenSrcPath) {
                $m->appendDokumenSrcVersion($m->oldDokumenSrcPath, auth()->id()); // ⬅️ NEW
                $m->oldDokumenSrcPath = null;
                $changed = true;
            }

            if ($changed) {
                $m->saveQuietly();
            }
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

        $versions   = $this->dokumen_versions ?? [];
        $versions[] = [
            'path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => now()->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
        ];
        $this->dokumen_versions = $versions;
    }

    public function appendDokumenSrcVersion(?string $oldPath, ?int $userId = null): void
    {
        if (!$oldPath) return;
        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        $newPath = 'berkas/_source_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        $versions   = $this->dokumen_src_versions ?? [];
        $versions[] = [
            'path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => now()->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
        ];
        $this->dokumen_src_versions = $versions;
    }

}
