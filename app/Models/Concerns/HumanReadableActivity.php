<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Activity;

trait HumanReadableActivity
{
    public function activityObjectLabel(): string
    {
        // Default yang cukup bagus untuk semua model.
        $base = class_basename(static::class);
        $name = $this->getActivityDisplayName();

        return $name ? "{$base}: {$name}" : "{$base}#{$this->getKey()}";
    }

    public function getActivityDisplayName(): ?string
    {
        foreach (['nama', 'nama_dokumen', 'kode_berkas', 'title'] as $col) {
            if (!empty($this->{$col})) return (string) $this->{$col};
        }
        return null;
    }

    public function tapActivity(\Spatie\Activitylog\Models\Activity $activity, string $event): void
    {
        $props = $activity->properties ?? collect();
        $activity->properties = $props->put('object_label', $this->activityObjectLabel());
    }
}
