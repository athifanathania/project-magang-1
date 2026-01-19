<?php

namespace App\Filament\Resources\RegularResource\Pages;

use App\Filament\Resources\RegularResource;
use App\Filament\Resources\BerkasResource\Pages\ListBerkas;

class ListRegular extends ListBerkas
{
    protected static string $resource = RegularResource::class;

    public function mount(): void
    {
        parent::mount();

        activity()
            ->causedBy(auth()->user())
            ->event('view')
            ->withProperties([
                // Ganti label sesuai konteks halaman
                'object_label' => 'Dokumen Eksternal # Halaman Regular', 
                'url' => request()->fullUrl(),       // URL Halaman
                'ip' => request()->ip(),             // Alamat IP User
                'user_agent' => request()->userAgent()
            ])
            ->log('Melihat Halaman Daftar Regular');
            
    }
}
