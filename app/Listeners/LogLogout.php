<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function handle(Logout $event): void
    {
        activity()
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('logout')
            ->withProperties([
                'ip'         => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'user_data'  => [
                    'name'       => $event->user->name,
                    'department' => $event->user->department,
                ]
            ])
            ->log('User logged out');
    }
}