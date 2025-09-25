<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.admin.auth.login')
                ->withErrors([
                    'email' => 'Akun sudah di non-aktifkan silahkan hubungi admin untuk pengaktifan akun',
                ]);
        }

        return $next($request);
    }
}
