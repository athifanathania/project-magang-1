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

    // 1. Validasi: Pastikan hanya satu owner yang terpilih (Berkas ATAU Regular)
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Fungsi normalizeOwner WAJIB ada di LampiranResource.php (sesuai diskusi sebelumnya)
        return LampiranResource::normalizeOwner($data);
    }

    // 2. Breadcrumb: Agar teks navigasi di kiri atas sesuai asal halaman
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $source = request('from'); // Menangkap parameter ?from=...
        
        $berkasId = request('berkas_id');
        $regularId = request('regular_id');

        if ($source === 'event_customer' && $berkasId) {
            // Jika datang dari Event Customer
            $url = EventCustomerResource::getUrl('edit', ['record' => $berkasId]);
            $breadcrumbs[$url] = 'Kembali ke Event Customer';
        } elseif ($berkasId) {
            // Default ke Berkas biasa
            $url = BerkasResource::getUrl('edit', ['record' => $berkasId]); 
            $breadcrumbs[$url] = 'Kembali ke Event';
        } elseif ($regularId) {
            // Jika dari Regular
            $url = RegularResource::getUrl('edit', ['record' => $regularId]);
            $breadcrumbs[$url] = 'Kembali ke Regular';
        } else {
            // Default jika akses langsung
            $breadcrumbs['#'] = 'Dokumen Pelengkap';
        }

        $breadcrumbs[] = 'Tambah';

        return $breadcrumbs;
    }

    // 3. Redirect: Setelah save mau dibawa kemana?
    protected function getRedirectUrl(): string
    {
        // Ambil ID dari request URL atau dari data record yang baru disimpan
        $berkasId  = request('berkas_id')  ?? data_get($this->record, 'berkas_id');
        $regularId = request('regular_id') ?? data_get($this->record, 'regular_id');
        $source    = request('from'); 

        // JIKA DARI EVENT CUSTOMER
        if ($source === 'event_customer' && $berkasId) {
            // Redirect ke Index Event Customer (atau Edit jika mau)
            return EventCustomerResource::getUrl('index'); 
        }

        // JIKA DARI BERKAS BIASA
        if ($berkasId) {
            // Redirect ke Edit Berkas, tab/parameter openLampiran opsional
            return BerkasResource::getUrl('index', [
                'openLampiran' => 1, // Parameter opsional jika kamu punya logika tab
                'berkas_id'    => $berkasId,
            ]);
        }

        // JIKA DARI REGULAR
        if ($regularId) {
            return RegularResource::getUrl('index');
        }

        return parent::getRedirectUrl();
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->title('Lampiran berhasil disimpan')->success();
    }
}