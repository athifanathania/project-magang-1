<?php
// ListImmAuditInternals.php
namespace App\Filament\Resources\ImmAuditInternalResource\Pages;

use App\Filament\Resources\ImmAuditInternalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Livewire\Concerns\HandlesImmLampiran; 
use Illuminate\Support\Facades\DB;
use App\Models\ImmLampiran;

class ListImmAuditInternals extends ListRecords
{
    use HandlesImmLampiran;
    
    protected static string $resource = ImmAuditInternalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Departemen')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Admin']) ?? false),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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
