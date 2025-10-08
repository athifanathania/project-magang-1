<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;

class LogRegistered
{
    public function handle(Registered $event): void
    {
        activity()
            ->causedBy($event->user)
            ->event('register')
            ->withProperties([
                'ip'         => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ])
            ->log('User registered');
    }
}
