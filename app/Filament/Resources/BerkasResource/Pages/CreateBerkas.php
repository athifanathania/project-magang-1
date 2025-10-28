<?php

namespace App\Filament\Resources\BerkasResource\Pages;

use App\Filament\Resources\BerkasResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

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
                    ->body('Dokumen yang ditambahkan sudah tersedia di tabel Event.')
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'kode_berkas' => 'Dokumen yang ditambahkan sudah tersedia di tabel Event.',
                    'detail'      => 'Dokumen yang ditambahkan sudah tersedia di tabel Event.',
                ]);
            }
            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        $rec = $this->record;

        // upload pertama saat Create biasanya tersimpan di 'berkas/tmp/...'
        $tmp = (string) ($rec->dokumen ?? '');

        if ($tmp !== '' && str_starts_with($tmp, 'berkas/tmp/')) {
            $disk   = Storage::disk('private');
            if (! $disk->exists($tmp)) {
                return;
            }

            $dir    = 'berkas/'.$rec->getKey();
            $name   = basename($tmp);
            $target = $dir.'/'.$name;

            $disk->makeDirectory($dir);
            $disk->move($tmp, $target);

            // catat ke riwayat sebagai REV00 (trait kamu sudah start dari 00)
            $rec->addVersionFromPath($target, basename($target), null, 'REV00');
        }
    }


}
