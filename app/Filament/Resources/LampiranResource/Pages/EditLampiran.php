<?php

namespace App\Filament\Resources\LampiranResource\Pages;

use App\Filament\Resources\LampiranResource;
use App\Filament\Resources\BerkasResource;
use App\Filament\Resources\RegularResource;
use App\Filament\Resources\EventCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
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

    // 1. Validasi saat Save
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return LampiranResource::normalizeOwner($data);
    }

    // 2. Custom Breadcrumb untuk Edit
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $record = $this->getRecord();
        $source = request('from'); // Tangkap parameter ?from=...

        if ($source === 'event_customer' && $record->berkas_id) {
            $url = EventCustomerResource::getUrl('edit', ['record' => $record->berkas_id]);
            $breadcrumbs[$url] = 'Kembali ke Event Customer';
        } elseif ($record->berkas_id) {
            $url = BerkasResource::getUrl('edit', ['record' => $record->berkas_id]);
            $breadcrumbs[$url] = 'Kembali ke Event';
        } elseif ($record->regular_id) {
            $url = RegularResource::getUrl('edit', ['record' => $record->regular_id]);
            $breadcrumbs[$url] = 'Kembali ke Regular';
        } else {
            $breadcrumbs['#'] = 'Dokumen Pelengkap';
        }

        $breadcrumbs[] = 'Edit';

        return $breadcrumbs;
    }

    // 3. Redirect setelah Edit selesai
    protected function getRedirectUrl(): string
    {
        $source = request('from');

        if ($source === 'event_customer' && $this->record->berkas_id) {
             return EventCustomerResource::getUrl('index');
        }

        if ($this->record->berkas_id) {
            return BerkasResource::getUrl('index');
        } 

        if ($this->record->regular_id) {
            return RegularResource::getUrl('index');
        }

        return parent::getRedirectUrl();
    }

    // 4. Notifikasi jika file kosong (Opsional)
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
}