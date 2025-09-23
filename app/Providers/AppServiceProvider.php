<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;   
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Teks di bawah form login admin (punyamu sudah oke)
        FilamentView::registerRenderHook(
            'panels::auth.login.form.after',
            fn (): string => view('auth.login-note')->render(),
        );

        // Pasang SQL logger hanya saat web request (bukan saat artisan command)
        if (! app()->runningInConsole()) {
            DB::listen(function ($query) {
                if (request()->is('admin/berkas*')) {
                    Log::info('[SQL berkas]', [
                        'sql'      => $query->sql,
                        'bindings' => $query->bindings,
                        'time_ms'  => $query->time,
                        'filters'  => request()->get('tableFilters'),
                    ]);
                }
            });
        }
    }
}
