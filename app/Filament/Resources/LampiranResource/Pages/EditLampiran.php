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
        if ($this->record->berkas_id) {
            return \App\Filament\Resources\BerkasResource::getUrl('index', [
                'berkas_id' => $this->record->berkas_id,
            ]);
        } 
        if ($this->record->regular_id) {
            return \App\Filament\Resources\RegularResource::getUrl('index');
        }
        return \App\Filament\Resources\BerkasResource::getUrl('index');
    }

    protected function resolveRecord(string|int $key): EloquentModel
    {
        $model = static::getResource()::getModel();

        // Ambil record langsung dari model UTAMA (tanpa join alias 'b')
        return $model::query()->findOrFail($key);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return \App\Filament\Resources\LampiranResource::normalizeOwner($data);
    }

}
