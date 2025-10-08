<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogPageView
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // abaikan asset, debugbar, horizon, dll
        if ($request->is(['_debugbar/*','telescope*','horizon*','storage/*','livewire/*'])) {
            return $response;
        }

        // hanya GET (view)
        if ($request->method() === 'GET' && !$request->ajax()) {
            activity()
                ->event('view')
                ->withProperties([
                    'route'      => optional($request->route())->getName(),
                    'url'        => $request->fullUrl(),
                    'method'     => $request->method(),
                    'query'      => $request->query(),
                    'ip'         => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ])
                ->log('View page');
        }

        return $response;
    }
}
