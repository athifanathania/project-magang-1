<?php

namespace App\Filament\Resources\LampiranResource\Pages;

use App\Filament\Resources\LampiranResource;
use Filament\Resources\Pages\EditRecord;

class ViewLampiran extends EditRecord
{
    protected static string $resource = LampiranResource::class;

    // WAJIB public (bukan protected)
    public function getTitle(): string
    {
        return 'Lihat Lampiran';
    }

    // Hilangkan action header (Save/Delete)
    protected function getHeaderActions(): array
    {
        return [];
    }

    // Hilangkan action form (Save, Cancel, dsb)
    protected function getFormActions(): array
    {
        return [];
    }

    // Set “mode view” → form jadi readonly + tampilkan riwayat
    protected function mutateFormDataBeforeFill(array $data): array
    {
        request()->merge(['view' => 1]);
        return $data;
    }
}
