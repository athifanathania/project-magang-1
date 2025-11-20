<?php

namespace App\Filament\Resources\ImmManualMutuResource\Pages;

use App\Filament\Resources\ImmManualMutuResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;
use App\Models\ImmLampiran;
use Filament\Notifications\Notification;
use Livewire\Attributes\On; 
use App\Livewire\Concerns\HandlesImmLampiran;
use App\Livewire\Concerns\HandlesImmDocVersions;
use Illuminate\Support\Facades\DB;

class ListImmManualMutus extends ListRecords
{
    use HandlesImmLampiran;
    use HandlesImmDocVersions;
    
    protected static string $resource = ImmManualMutuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Manual Mutu')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false),
        ];
    }

    public function reorderImmChildren(?int $parentId, array $orderedIds): void
    {
        if (! (auth()->user()?->hasAnyRole(['Admin','Editor']) ?? false)) {
            abort(403);
        }

        // validasi: id yang dikirim memang saudara di parent yang sama
        $q = ImmLampiran::query()->whereIn('id', $orderedIds);
        $q = is_null($parentId)
            ? $q->whereNull('parent_id')
            : $q->where('parent_id', $parentId);

        $found = $q->pluck('id')->all();

        if (count($found) !== count($orderedIds)) {
            abort(422, 'Invalid items for this parent.');
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $i => $id) {
                ImmLampiran::whereKey($id)->update([
                    'sort_order' => $i + 1,
                ]);
            }
        });

        // biar Livewire TIDAK re-render komponen (DOM tetap seperti hasil drag)
        $this->skipRender();
    }
}
