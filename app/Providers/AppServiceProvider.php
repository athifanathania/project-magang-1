<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;

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
        // Teks di bawah form login admin
        FilamentView::registerRenderHook(
            'panels::auth.login.form.after',
            fn (): string => view('auth.login-note')->render(),
        );
    }
}
