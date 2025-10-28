<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Models\Concerns\HumanReadableActivity;

class Lampiran extends Model
{
    use HasFactory, LogsActivity, HumanReadableActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logOnly(['nama','file','keywords','parent_id','berkas_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $e) => "Lampiran {$e}");
    }

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

    protected ?string $oldFilePath = null;

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

        // Simpan path lama hanya jika 'file' berubah
        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $m->oldFilePath = $m->getOriginal('file');
            }
        });

        static::saved(function (self $m) {
            // stop kalau 'file' tidak berubah
            if (! $m->wasChanged('file')) {
                return;
            }

            $old = $m->oldFilePath;
            if (! $old) {
                return;
            }

            $m->appendFileVersion($old, auth()->id());

            // bersihkan dan simpan riwayat tanpa event
            $m->oldFilePath = null;
            $m->saveQuietly();
        });

        static::deleting(function (Lampiran $m) {
            $m->children()->get()->each->delete();
        });
    }

    protected $casts = [
        'file_versions' => 'array',   
        'file_src_versions'    => 'array',
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

        // 1) Pindahkan file fisik ke folder _versions/{id}/...
        $newPath = 'lampiran/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        // 2) Normalisasi raw -> pisahkan versi numerik & meta
        $raw = $this->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        $versions = [];
        $meta     = [];
        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string)$k);
            if ($isNumeric) {
                if (is_array($v) && (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))) {
                    $versions[] = $v; // rebase 0..n-1
                }
            } else {
                $meta[$k] = $v;
            }
        }

        // 3) Ambil deskripsi VERSI AKTIF dari meta, lalu kosongkan
        $currentDesc = trim((string)($meta['__current_desc'] ?? ''));
        unset($meta['__current_desc']);

        // 4) Tambah entry versi (untuk file lama yang baru dipindah)
        $uploadedAt = null;
        try { $ts = $disk->lastModified($newPath); $uploadedAt = \Illuminate\Support\Carbon::createFromTimestamp($ts)->toDateTimeString(); }
        catch (\Throwable) {}

        $versions[] = [
            'file_path'        => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => $uploadedAt ?? optional($this->updated_at ?? $this->created_at)->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
            'description' => $currentDesc !== '' ? $currentDesc : null,
        ];

        // 5) Satukan kembali
        $out = $versions;
        foreach ($meta as $k => $v) { $out[$k] = $v; }

        $this->file_versions = $this->renumberFileVersions($out);
    }

    public function deleteVersionAtIndex(int $index): bool
    {
        // Normalisasi raw
        $raw = $this->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        $versions = [];
        $meta     = [];
        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string) $k);
            if ($isNumeric) {
                if (
                    is_array($v) &&
                    (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))
                ) {
                    $versions[] = $v;
                }
            } else {
                $meta[$k] = $v;
            }
        }

        if ($index < 0 || $index >= count($versions)) return false;

        $disk = \Storage::disk('private');
        $v    = $versions[$index] ?? null;
        if (is_array($v) && !empty($v['file_path']) && $disk->exists($v['file_path'])) {
            $disk->delete($v['file_path']);
        }
        array_splice($versions, $index, 1);

        $out = array_values($versions);
        foreach ($meta as $k => $val) { $out[$k] = $val; }

        $this->file_versions = $this->renumberFileVersions($out);
        $this->saveQuietly();

        return true;
    }

    public function updateVersionDescription(int $index, string $description): bool
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $desc = trim($description);

        $raw = $this->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        $versions = [];
        $meta     = [];
        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string)$k);
            if ($isNumeric) {
                if (is_array($v) && (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))) {
                    $versions[] = $v;
                }
            } else {
                $meta[$k] = $v;
            }
        }

        $activeIndex = count($versions); // index “baris aktif” pada blade

        if ($index < $activeIndex) {
            $versions[$index]['description'] = ($desc === '') ? null : $desc;
        } elseif ($index === $activeIndex) {
            $meta['__current_desc'] = ($desc === '') ? null : $desc;
        } else {
            return false;
        }

        $out = $versions;
        foreach ($meta as $k => $v) { $out[$k] = $v; }

        $this->file_versions = $this->renumberFileVersions($out);
        $this->saveQuietly();

        return true;
    }

    public function addPdfVersion(array $ver): void
    {
        $list = collect($this->file_versions ?? []);
        $list->prepend($ver); // versi terbaru di depan
        $this->file_versions = $list->values()->all();
        $this->saveQuietly();
    }

    public function addSourceVersion(array $ver): void
    {
        $list = collect($this->file_src_versions ?? []);
        $list->prepend($ver);
        $this->file_src_versions = $list->values()->all();
        $this->saveQuietly();
    }

    protected function renumberFileVersions(array $raw): array
    {
        // Pecah numeric entries (versi) & meta (kunci string)
        $versions = [];
        $meta     = [];

        foreach ($raw as $k => $v) {
            $isNumeric = is_int($k) || ctype_digit((string)$k);
            if ($isNumeric) {
                if (is_array($v) && (isset($v['file_path']) || isset($v['path']) || isset($v['filename']))) {
                    $versions[] = $v; // reindex 0..n-1
                }
            } else {
                $meta[$k] = $v;       // simpan meta (mis. __current_desc)
            }
        }

        // === NOMORI ULANG: REV00, REV01, ...
        foreach ($versions as $i => &$row) {
            $row['revision'] = 'REV' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        }
        unset($row);

        // Gabungkan kembali (numeric dulu baru meta)
        $out = $versions;
        foreach ($meta as $k => $v) {
            $out[$k] = $v;
        }
        return $out;
    }

}
