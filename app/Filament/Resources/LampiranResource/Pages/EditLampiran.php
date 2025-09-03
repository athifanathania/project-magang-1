<?php

namespace App\Filament\Resources\LampiranResource\Pages;

use App\Filament\Resources\LampiranResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Filament\Resources\BerkasResource;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class EditLampiran extends EditRecord
{
    protected static string $resource = LampiranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function mount($record): void
    {
        parent::mount($record);

        if (request()->boolean('missingFile')) {
            Notification::make()
                ->title('Silahkan Tambahkan File Lampiran')
                ->warning()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        // kalau mau balik ke index Berkas, optionally kirim berkas_id
        return BerkasResource::getUrl('index', [
            'berkas_id' => $this->record->berkas_id, // boleh dihapus kalau tak dipakai
        ]);
    }

    protected function resolveRecord(string|int $key): EloquentModel
    {
        $model = static::getResource()::getModel();

        // Ambil record langsung dari model UTAMA (tanpa join alias 'b')
        return $model::query()->findOrFail($key);
    }


}
