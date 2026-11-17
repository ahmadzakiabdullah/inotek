<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Public portal pages, always shown in light mode: they have no theme
     * switch, so visitors whose device prefers dark mode could not change it.
     * Keep in sync with LIGHT_ONLY_PATHS in resources/js/hooks/use-appearance.tsx.
     */
    public const LIGHT_ONLY_PATHS = ['/', 'leaderboard', 'contact', 'verify/*'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->is(...self::LIGHT_ONLY_PATHS)
            ? 'light'
            : ($request->cookie('appearance') ?? 'system');

        View::share('appearance', $appearance);

        return $next($request);
    }
}
