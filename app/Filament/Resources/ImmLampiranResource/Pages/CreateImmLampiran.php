<?php

namespace App\Filament\Resources\ImmLampiranResource\Pages;

use App\Filament\Resources\ImmLampiranResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateImmLampiran extends CreateRecord
{
    protected static string $resource = ImmLampiranResource::class;

    public $storedPreviousUrl = null;

    public function mount(): void
    {
        $this->storedPreviousUrl = url()->previous();
        parent::mount();
    }

    /** Normalisasi: terima FQCN atau short name, kembalikan FQCN yang valid */
    private function normalizeDocType(?string $type): ?string
    {
        if (! $type) return null;
        $type = ltrim($type, '\\');

        if (class_exists($type)) return $type;

        $fqcn = 'App\\Models\\' . $type;
        return class_exists($fqcn) ? $fqcn : null;
    }

    /** Map model -> resource IMM (pakai FQCN) */
    private function mapModelToResource(?string $model): ?string
    {
        $m = $this->normalizeDocType($model);

        return match ($m) {
            \App\Models\ImmManualMutu::class       => \App\Filament\Resources\ImmManualMutuResource::class,
            \App\Models\ImmProsedur::class         => \App\Filament\Resources\ImmProsedurResource::class,
            \App\Models\ImmInstruksiStandar::class => \App\Filament\Resources\ImmInstruksiStandarResource::class,
            \App\Models\ImmFormulir::class         => \App\Filament\Resources\ImmFormulirResource::class,
            \App\Models\ImmAuditInternal::class    => \App\Filament\Resources\ImmAuditInternalResource::class,
            default => null,
        };
    }

    private function backUrl(): string
    {
        if ($this->storedPreviousUrl && !str_contains($this->storedPreviousUrl, '/create')) {
            return $this->storedPreviousUrl;
        }

        $type = $this->record?->documentable_type
            ?? request('documentable_type')
            ?? request('doc_type');

        if ($res = $this->mapModelToResource($type)) {
            return $res::getUrl('index');
        }

        return route('filament.admin.pages.dashboard');
    }

    protected function getRedirectUrl(): string
    {
        return $this->backUrl();
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Lampiran tersimpan')->success();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($t = request('doc_type')) {
            $data['documentable_type'] = $this->normalizeDocType($t) ?? $t;
        }
        if ($id = request('doc_id')) {
            $data['documentable_id'] = (int) $id;
        }

        $parent = $data['parent_id'] ?? request('parent_id');
        $data['parent_id'] = ($parent && (int) $parent > 0) ? (int) $parent : null;

        return $data;
    }

    private function isAuditContext(): bool
    {
        $type = request('documentable_type') ?: request('doc_type');
        return $this->normalizeDocType($type) === \App\Models\ImmAuditInternal::class;
    }

    public function getTitle(): string
    {
        return $this->isAuditContext() ? 'Create Temuan Audit' : 'Create Imm Lampiran';
    }

    public function getHeading(): string
    {
        return $this->getTitle();
    }

    public function getBreadcrumb(): string
    {
        return $this->getTitle();
    }

    protected function afterCreate(): void
    {
        $rec = $this->record;

        // Path upload awal biasanya di folder tmp.
        $tmp = (string) ($rec->file ?? '');
        $isTmp = $tmp !== '' && (
            str_starts_with($tmp, 'imm/lampiran/tmp/')
            || str_starts_with($tmp, 'imm-lampiran/tmp/')
            || str_starts_with($tmp, 'imm/tmp/')
        );

        if ($isTmp) {
            $disk = \Storage::disk('private');
            if ($disk->exists($tmp)) {
                $dir    = 'imm/lampiran/'.$rec->getKey();
                $name   = basename($tmp);
                $target = $dir.'/'.$name;

                $disk->makeDirectory($dir);
                $disk->move($tmp, $target);

                $rec->file = $target;

                $size = null; try { $size = $disk->size($target); } catch (\Throwable) {}

                $raw = $rec->getAttribute('file_versions');
                if ($raw instanceof \Illuminate\Support\Collection) $raw = $raw->all();
                elseif (is_string($raw)) { $j = json_decode($raw, true); $raw = is_array($j) ? $j : []; }
                elseif (!is_array($raw)) { $raw = []; }

                $first = [
                    'revision'     => 'REV00',
                    'filename'     => basename($target),
                    'description'  => null,
                    'file_path'    => $target,
                    'path'         => $target,
                    'file_ext'     => strtolower(pathinfo($target, PATHINFO_EXTENSION)),
                    'ext'          => strtolower(pathinfo($target, PATHINFO_EXTENSION)),
                    'file_size'    => $size,
                    'size'         => $size,
                    'uploaded_at'  => now()->toISOString(),
                    'replaced_at'  => null,
                ];

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
                $versions[] = $first;

                $versions = collect($versions)->values()->map(function ($row, $i) {
                    $row['revision'] = 'REV' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                    return $row;
                })->all();

                $out = $versions;
                foreach ($meta as $k => $v) { $out[$k] = $v; }

                $rec->file_versions = $out;
                $rec->save();
            }
        }

        // 4. Redirect menggunakan logika backUrl() yang sudah diperbarui
        $this->redirect($this->backUrl(), navigate: true);
    }
}