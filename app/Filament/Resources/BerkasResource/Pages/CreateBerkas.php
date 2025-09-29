<?php

namespace App\Filament\Resources\BerkasResource\Pages;

use App\Filament\Resources\BerkasResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class CreateBerkas extends CreateRecord
{
    protected static string $resource = BerkasResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return static::getModel()::create($data);
        } catch (QueryException $e) {
            // Jika kena unique index
            if (Str::contains($e->getMessage(), ['Duplicate', 'unique'])) {
                Notification::make()
                    ->title('Gagal menyimpan')
                    ->body('Dokumen yang ditambahkan sudah tersedia di tabel Regular.')
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'kode_berkas' => 'Dokumen yang ditambahkan sudah tersedia di tabel Regular.',
                    'detail'      => 'Dokumen yang ditambahkan sudah tersedia di tabel Regular.',
                ]);
            }
            throw $e;
        }
    }

}
