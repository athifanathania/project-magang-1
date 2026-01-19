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
                // Tambahkan snapshot data user saat ini
                'user_data'  => [
                    'name'       => $event->user->name,
                    'department' => $event->user->department,
                ]
            ])
            ->log('User logged in');
    }
}