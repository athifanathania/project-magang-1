<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Berkas::class   => \App\Policies\BerkasPolicy::class,
        \App\Models\Lampiran::class => \App\Policies\LampiranPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('download-source', fn ($user) =>
            $user?->hasAnyRole(['Admin','Editor','Staff']) === true
        );

        Gate::define('replace-source', fn ($user) =>
            $user?->hasRole('Admin') === true
        );
    }

}
