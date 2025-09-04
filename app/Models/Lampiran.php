<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Lampiran extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function berkas()
    {
        return $this->belongsTo(\App\Models\Berkas::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    /** Scope bantu untuk root nodes */
    public function scopeRoot($q)
    {
        return $q->whereNull('parent_id');
    }

    protected static function booted(): void
    {
        // Pastikan anak selalu mewarisi berkas_id parent
        static::saving(function (Lampiran $m) {
            // Cegah parent menjadi turunan dari $m (mencegah siklus)
            if ($m->parent_id && $m->id) {
                $p = static::find($m->parent_id);
                while ($p) {
                    if ($p->id === $m->id) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'Parent tidak boleh merupakan turunan dari item ini.',
                        ]);
                    }
                    $p = $p->parent_id ? static::find($p->parent_id) : null;
                }
            }
            if ($m->parent_id) {
                $m->berkas_id = static::whereKey($m->parent_id)->value('berkas_id');
            }
        });

        // Jika berkas_id berubah (mis. dipindah cabang), propagasikan ke seluruh keturunan
        static::saved(function (Lampiran $m) {
            if ($m->wasChanged('berkas_id')) {
                $m->updateDescendantsBerkasId($m->berkas_id);
            }
        });

        // Hapus subtree jika belum pakai FK cascade di DB
        static::deleting(function (Lampiran $m) {
            // Jika pakai soft deletes, ubah ke ->each->delete()
            $m->children()->get()->each->delete();
        });

        // Saat file lampiran diganti, pindahkan versi lama & catat
        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $old = $m->getOriginal('file');
                $m->appendFileVersion($old, auth()->id());
            }
        });
    }

    protected $casts = [
        'file_versions' => 'array',   // <— penting
        // 'keywords' => 'array', 
    ];


    /** Rekursif: set berkas_id semua turunan = $newId */
    public function updateDescendantsBerkasId($newId): void
    {
        $this->children()->update(['berkas_id' => $newId]);

        // lanjutkan ke cucu-cicit
        foreach ($this->children as $child) {
            $child->updateDescendantsBerkasId($newId);
        }
    }

    protected function keywords(): Attribute
    {
        $sanitize = function ($arr) {
            return collect($arr)
                ->flatten()
                ->map(fn ($x) => trim((string) $x, " \t\n\r\0\x0B\"'")) // buang spasi & " atau '
                ->filter()
                ->unique()
                ->values()
                ->all();
        };

        return Attribute::get(function ($value) use ($sanitize) {
            if (blank($value)) return [];
            if (is_array($value)) return $sanitize($value);

            if (is_string($value)) {
                $json = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    return $sanitize($json);
                }
                // CSV "a, b, c"
                return $sanitize(preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY));
            }

            return $sanitize((array) $value);
        })->set(function ($value) use ($sanitize) {
            // terima array / collection / csv / json → simpan JSON array bersih
            $arr = $value instanceof \Illuminate\Support\Collection ? $value->all()
                : (is_array($value) ? $value
                : (is_string($value)
                    ? (json_decode($value, true) ?? preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY))
                    : (array) $value));

            return json_encode($sanitize($arr), JSON_UNESCAPED_UNICODE);
        });
    }

    public function appendFileVersion(?string $oldPath, ?int $userId = null): void
    {
        if (!$oldPath) return;

        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        $newPath = 'lampiran/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        $versions = $this->file_versions ?? [];
        $versions[] = [
            'path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => now()->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
        ];
        $this->file_versions = $versions;
    }

}
