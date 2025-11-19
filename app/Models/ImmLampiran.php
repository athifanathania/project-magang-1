<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\Models\Activity;
use App\Models\Concerns\HumanReadableActivity;

class ImmLampiran extends Model
{
    use SoftDeletes, LogsActivity, HumanReadableActivity {
        HumanReadableActivity::tapActivity as private tapActivityLabel;
    }

    protected function belongsToAuditInternal(): bool
    {
        return class_basename((string) $this->documentable_type) === 'ImmAuditInternal';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('web')
            ->logOnly([
                'nama','file','keywords','parent_id',
                'documentable_type','documentable_id','deadline_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $event) {
                return $this->belongsToAuditInternal()
                    ? "List temuan audit {$event}"
                    : "Lampiran Imm {$event}";
            });
    }

    public function tapActivity(Activity $activity, string $event): void
    {
        // 1) Arahkan subject ke induk Audit Internal bila perlu
        if ($this->belongsToAuditInternal()) {
            $activity->subject_type = \App\Models\ImmAuditInternal::class;
            $activity->subject_id   = $this->documentable_id;
        }

        // 2) Set object_label yang human-readable
        $props = collect($activity->properties ?? []);

        if ($activity->subject_type === \App\Models\ImmAuditInternal::class) {
            // Bangun label dari subjek (dokumen audit), bukan dari lampiran.
            $parent = \App\Models\ImmAuditInternal::find($activity->subject_id);
            $base   = class_basename(\App\Models\ImmAuditInternal::class);

            // Ambil “nama” yang paling masuk akal di model induk
            $name = $parent->nama
                ?? $parent->nama_dokumen
                ?? "{$base}#{$activity->subject_id}";

            $label = "{$base}: {$name}";
        } else {
            // Fallback: pakai helper dari trait (lampiran biasa, dll.)
            $label = $this->activityObjectLabel();
        }

        $activity->properties = $props->put('object_label', $label);
    }

    protected ?string $oldFilePath = null;
    protected ?string $oldUploadedAt = null;

    protected $guarded = [];
    protected $casts = [
        'keywords' => 'array',
        'file_versions' => 'array',
        'file_src_versions'    => 'array',
        'deadline_at'       => 'date',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderByRaw('COALESCE(sort_order, id) ASC');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    protected static function booted(): void
    {
        // default sort_order di ekor saudara saat create
        static::creating(function (self $m) {
            if (is_null($m->sort_order)) {
                $max = self::where('parent_id', $m->parent_id)->max('sort_order');
                $m->sort_order = is_null($max) ? 1 : ($max + 1);
            }
        });

        // updating: simpan path & waktu 'terbit' versi lama
        static::updating(function (self $m) {
            if ($m->isDirty('file')) {
                $m->oldFilePath   = $m->getOriginal('file');
                $m->oldUploadedAt = optional(
                    $m->getOriginal('updated_at') ?? $m->getOriginal('created_at')
                )->toDateTimeString();
            }
        });

        // saved: pindahkan file lama ke folder _versions + catat uploaded_at
        static::saved(function (self $m) {
            if ($m->wasChanged('file') && $m->oldFilePath) {
                $m->appendFileVersion($m->oldFilePath, auth()->id(), $m->oldUploadedAt);
                $m->oldFilePath   = null;
                $m->oldUploadedAt = null;
                $m->saveQuietly();
            }
        });

        static::deleting(function (self $m) {
            $m->children()->get()->each->delete();
        });
    }

    // terima uploadedAt lama
    public function appendFileVersion(?string $oldPath, ?int $userId = null, ?string $uploadedAt = null): void
    {
        if (!$oldPath) return;

        $disk = \Storage::disk('private');
        if (!$disk->exists($oldPath)) return;

        // 1) Pindahkan file fisik ke folder _versions/{id}/...
        $newPath = 'imm_lampiran/_versions/'.$this->id.'/'.now()->format('Ymd_His').'-'.basename($oldPath);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($oldPath, $newPath);

        // 2) Normalisasi file_versions -> pisah versi numerik & meta
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

        // 3) Ambil deskripsi versi AKTIF dari meta, lalu kosongkan meta tsb
        $currentDesc = trim((string)($meta['__current_desc'] ?? ''));
        unset($meta['__current_desc']); // versi baru harus mulai tanpa deskripsi

        // 4) Tambah entry versi (untuk file lama yang baru dipindah)
        $versions[] = [
            'file_path' => $newPath,
            'filename'    => basename($oldPath),
            'size'        => $disk->size($newPath),
            'ext'         => pathinfo($newPath, PATHINFO_EXTENSION),
            'uploaded_at' => $uploadedAt ?: optional($this->updated_at ?? $this->created_at)->toDateTimeString(),
            'replaced_at' => now()->toDateTimeString(),
            'by'          => $userId,
            'description' => $currentDesc !== '' ? $currentDesc : null,
        ];

        // 5) Satukan kembali: versi numerik + meta non-numerik
        $out = $versions;
        foreach ($meta as $k => $v) { $out[$k] = $v; }

        $this->file_versions = $out;

        // set sort_order default di ekor list saudara
        static::creating(function (self $m) {
            if (is_null($m->sort_order)) {
                $max = self::where('parent_id', $m->parent_id)->max('sort_order');
                $m->sort_order = is_null($max) ? 1 : ($max + 1);
            }
        });
    }


    public function deleteVersionAtIndex(int $index): bool
    {
        // --- Normalisasi raw
        $raw = $this->getAttribute('file_versions');
        if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
        elseif (is_string($raw)) { $dec = json_decode($raw, true); $raw = is_array($dec) ? $dec : []; }
        elseif (!is_array($raw)) { $raw = []; }

        // --- Pisah versi numerik valid & meta non-numerik (termasuk __current_desc)
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

        // --- Validasi index
        if ($index < 0 || $index >= count($versions)) return false;

        // --- Hapus file fisik (jika ada) lalu keluarkan dari array
        $disk = \Storage::disk('private');
        $v    = $versions[$index] ?? null;
        if (is_array($v) && !empty($v['file_path']) && $disk->exists($v['file_path'])) {
            $disk->delete($v['file_path']);
        }
        array_splice($versions, $index, 1); // reindex otomatis 0..n-2

        // --- Satukan kembali: versi numerik + meta TETAP ADA
        $out = array_values($versions);
        foreach ($meta as $k => $val) { $out[$k] = $val; }

        $this->file_versions = $out;
        $this->saveQuietly();

        return true;
    }

}
