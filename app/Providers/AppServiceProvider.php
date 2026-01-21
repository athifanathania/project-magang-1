<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        Relation::morphMap([
            'ImmManualMutu'       => \App\Models\ImmManualMutu::class,
            'ImmProsedur'         => \App\Models\ImmProsedur::class,
            'ImmFormulir'         => \App\Models\ImmFormulir::class,
            'ImmInstruksiStandar' => \App\Models\ImmInstruksiStandar::class,
            'ImmAuditInternal'    => \App\Models\ImmAuditInternal::class,
        ]);

        FilamentView::registerRenderHook(
            'panels::auth.login.form.after',
            fn (): string => view('auth.login-note')->render(),
        );

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