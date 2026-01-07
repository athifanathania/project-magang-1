<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class LogLogin
{
    public function handle(Login $event): void
    {
        activity()
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('login')
            ->withProperties([
                'ip'         => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ])
            ->log('User logged in');
    }
}
