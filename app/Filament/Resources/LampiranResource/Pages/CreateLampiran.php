<?php

namespace App\Filament\Resources\LampiranResource\Pages;

use App\Filament\Resources\LampiranResource;
use App\Filament\Resources\BerkasResource;
use App\Filament\Resources\RegularResource;
use App\Filament\Resources\EventCustomerResource; 
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateLampiran extends CreateRecord
{
    protected static string $resource = LampiranResource::class;

    public $storedPreviousUrl = null;

    public function mount(): void
    {
        $this->storedPreviousUrl = url()->previous();
        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return LampiranResource::normalizeOwner($data);
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $source = request('from');
        
        $berkasId = request('berkas_id');
        $regularId = request('regular_id');

        if ($source === 'event_customer' && $berkasId) {
            $url = EventCustomerResource::getUrl('edit', ['record' => $berkasId]);
            $breadcrumbs[$url] = 'Kembali ke Event Customer';
        } elseif ($berkasId) {
            $url = BerkasResource::getUrl('edit', ['record' => $berkasId]); 
            $breadcrumbs[$url] = 'Kembali ke Event';
        } elseif ($regularId) {
            $url = ($this->storedPreviousUrl && str_contains($this->storedPreviousUrl, 'regular')) 
                    ? $this->storedPreviousUrl 
                    : RegularResource::getUrl('index');
            $breadcrumbs[$url] = 'Kembali ke Regular';
        } else {
            $breadcrumbs['#'] = 'Dokumen Pelengkap';
        }

        $breadcrumbs[] = 'Tambah';

        return $breadcrumbs;
    }

    protected function getRedirectUrl(): string
    {
        $berkasId  = request('berkas_id')  ?? data_get($this->record, 'berkas_id');
        $regularId = request('regular_id') ?? data_get($this->record, 'regular_id');
        $source    = request('from'); 

        if ($source === 'event_customer' && $berkasId) {
            return EventCustomerResource::getUrl('index'); 
        }

        if ($berkasId) {
            return BerkasResource::getUrl('index', [
                'openLampiran' => 1, 
                'berkas_id'    => $berkasId,
            ]);
        }

        if ($regularId) {
            if ($this->storedPreviousUrl && str_contains($this->storedPreviousUrl, 'regular') && !str_contains($this->storedPreviousUrl, 'create')) {
                return $this->storedPreviousUrl;
            }
            return RegularResource::getUrl('index');
        }

        return parent::getRedirectUrl();
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Lampiran berhasil disimpan')->success();
    }
}